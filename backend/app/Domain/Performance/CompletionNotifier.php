<?php

namespace App\Domain\Performance;

use App\Domain\Audit\AuditLogger;
use App\Domain\Workflow\WorkflowNotifier;
use App\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\ResponsibilityAssignment;
use App\Models\RoadSection;
use App\Models\User;
use App\Support\TestDataMode;

/**
 * §39: when a contract's maintenance period ends, its completion report is ready —
 * notify the division's EE(s) and administrators once (recorded in the audit log).
 */
class CompletionNotifier
{
    public const ACTION = 'contract.completion_report_ready';

    public function __construct(
        private readonly WorkflowNotifier $notifier,
        private readonly AuditLogger $audit,
        private readonly CompletionReportService $reports,
        private readonly TestDataMode $testData,
    ) {}

    /** @return int contracts announced */
    public function run(): int
    {
        return $this->testData->withTestData(function () {
            $announced = AuditLog::where('action', self::ACTION)->where('auditable_type', 'contract')->pluck('auditable_id');

            return Contract::with('contractor:id,name')
                ->whereNotIn('status', Contract::NON_RESPONSIBLE_STATUSES)
                ->whereDate('maintenance_end_date', '<', now()->toDateString())
                ->whereDate('maintenance_end_date', '>=', now()->subDays(30)->toDateString()) // don't flood on first run
                ->whereNotIn('id', $announced)
                ->get()
                ->each(fn (Contract $c) => $this->announce($c))
                ->count();
        });
    }

    private function announce(Contract $contract): void
    {
        $data = $this->reports->build($contract);
        $recipients = $this->recipients($contract);

        $this->notifier->system($recipients, 'contract.completed',
            "Maintenance period of {$contract->contract_no} ({$contract->contractor->name}) has ended. ".$data['final_status'].' Completion report is ready.',
            route('contracts.completion', $contract, false));

        $this->audit->log(self::ACTION, $contract, null, [
            'tasks_total' => $data['metrics']['tasks_total'], 'tasks_open' => $data['metrics']['tasks_open'],
            'notified' => $recipients->pluck('id')->all(),
        ], $data['final_status']);
    }

    private function recipients(Contract $contract)
    {
        $sectionIds = $contract->roadSections()->whereNotNull('road_section_id')->pluck('road_section_id');
        $ees = ResponsibilityAssignment::where('scope_type', ResponsibilityAssignment::SCOPE_SECTION)->whereIn('scope_id', $sectionIds)
            ->where('role_code', RoleCode::EE)->effectiveOn(now())->pluck('user_id');
        if ($ees->isEmpty() && $contract->division_id) {
            $ees = ResponsibilityAssignment::where('scope_type', ResponsibilityAssignment::SCOPE_SECTION)->where('role_code', RoleCode::EE)
                ->whereIn('scope_id', RoadSection::where('division_id', $contract->division_id)->select('id'))->effectiveOn(now())->pluck('user_id');
        }
        $admins = User::whereHas('roles', fn ($q) => $q->where('code', RoleCode::ADMIN->value))->pluck('id');

        return User::whereIn('id', $ees->merge($admins)->unique())->where('status', User::STATUS_ACTIVE)->get();
    }
}
