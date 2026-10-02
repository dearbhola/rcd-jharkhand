<?php

namespace Database\Seeders;

use App\Models\EscalationRule;
use App\Models\Severity;
use App\Models\SlaRule;
use Illuminate\Database\Seeder;

/**
 * Starting SLA/escalation values for admins to adjust. Seeded only when no
 * rules exist, so edits are never overwritten.
 */
class SlaSeeder extends Seeder
{
    /** hours per stage per severity code */
    private const HOURS = [
        SlaRule::STAGE_VALIDATION => ['CRITICAL' => 6, 'HIGH' => 12, 'MEDIUM' => 24, 'LOW' => 48],
        SlaRule::STAGE_RESPONSE => ['CRITICAL' => 4, 'HIGH' => 12, 'MEDIUM' => 24, 'LOW' => 48],
        SlaRule::STAGE_REPAIR => ['CRITICAL' => 24, 'HIGH' => 72, 'MEDIUM' => 168, 'LOW' => 336],
        SlaRule::STAGE_JE_REVIEW => ['CRITICAL' => 12, 'HIGH' => 24, 'MEDIUM' => 48, 'LOW' => 72],
        SlaRule::STAGE_AE_REVIEW => ['CRITICAL' => 12, 'HIGH' => 24, 'MEDIUM' => 48, 'LOW' => 72],
        SlaRule::STAGE_EE_APPROVAL => ['CRITICAL' => 24, 'HIGH' => 48, 'MEDIUM' => 72, 'LOW' => 96],
    ];

    public function run(): void
    {
        if (! SlaRule::exists()) {
            $severities = Severity::pluck('id', 'code');

            foreach (self::HOURS as $stage => $bySeverity) {
                // Stage fallback (no severity) uses the MEDIUM value.
                SlaRule::create(['stage' => $stage, 'hours' => $bySeverity['MEDIUM']]);

                foreach ($bySeverity as $code => $hours) {
                    SlaRule::create(['stage' => $stage, 'severity_id' => $severities[$code], 'hours' => $hours]);
                }
            }
        }

        if (! EscalationRule::exists()) {
            // Reminder before due → escalate to AE on breach → escalate to EE on further breach.
            EscalationRule::create(['trigger' => EscalationRule::TRIGGER_BEFORE_DUE, 'offset_hours' => 4, 'level' => 0, 'action' => EscalationRule::ACTION_REMIND, 'notify_assignee' => true]);
            EscalationRule::create(['trigger' => EscalationRule::TRIGGER_AFTER_DUE, 'offset_hours' => 0, 'level' => 1, 'action' => EscalationRule::ACTION_ESCALATE, 'notify_role_code' => 'AE', 'notify_assignee' => true]);
            EscalationRule::create(['trigger' => EscalationRule::TRIGGER_AFTER_DUE, 'offset_hours' => 24, 'level' => 2, 'action' => EscalationRule::ACTION_ESCALATE, 'notify_role_code' => 'EE', 'notify_assignee' => true]);
        }
    }
}
