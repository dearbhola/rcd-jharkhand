<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetType;
use App\Models\EscalationRule;
use App\Models\IssueCategory;
use App\Models\Severity;
use App\Models\SlaRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * SLA rules (hours per stage, optionally per asset type / category / severity) and escalation rules.
 * Running timers keep the hours they started with; edits apply to new timers.
 */
class SlaController extends Controller
{
    public const STAGES = [
        SlaRule::STAGE_VALIDATION => 'JE validation',
        SlaRule::STAGE_RESPONSE => 'Contractor response (start repair)',
        SlaRule::STAGE_REPAIR => 'Repair',
        SlaRule::STAGE_JE_REVIEW => 'JE field review',
        SlaRule::STAGE_AE_REVIEW => 'AE field review',
        SlaRule::STAGE_EE_APPROVAL => 'EE approval',
    ];

    public function index(): View
    {
        return view('admin.sla.index', [
            'stages' => self::STAGES,
            'rules' => SlaRule::with(['assetType:id,name', 'issueCategory:id,name', 'severity:id,name,color,rank'])->get()
                ->sortBy([['stage', 'asc'], [fn ($r) => $r->specificity(), 'asc'], [fn ($r) => $r->severity?->rank ?? 0, 'asc']])
                ->groupBy('stage'),
            'escalations' => EscalationRule::orderBy('level')->orderBy('trigger')->get(),
            'assetTypes' => AssetType::orderBy('sort_order')->pluck('name', 'id'),
            'categories' => IssueCategory::with('assetType:id,name')->where('is_active', true)->orderBy('asset_type_id')->get(),
            'severities' => Severity::orderBy('rank')->pluck('name', 'id'),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        SlaRule::create($this->validateRule($request));

        return $this->back('SLA rule added.');
    }

    public function updateRule(Request $request, SlaRule $rule): RedirectResponse
    {
        $data = $request->validate([
            'hours' => ['required', 'numeric', 'min:0.25', 'max:8760'],
            'is_active' => ['required', 'boolean'],
        ]);
        $rule->update($data);

        return $this->back('SLA rule updated. Running timers keep their original due time.');
    }

    public function storeEscalation(Request $request): RedirectResponse
    {
        EscalationRule::create($this->validateEscalation($request));

        return $this->back('Escalation rule added.');
    }

    public function updateEscalation(Request $request, EscalationRule $rule): RedirectResponse
    {
        $rule->update($this->validateEscalation($request));

        return $this->back('Escalation rule updated.');
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()->route('admin.sla.index')->with('success', $message);
    }

    private function validateRule(Request $request): array
    {
        $data = $request->validate([
            'stage' => ['required', Rule::in(array_keys(self::STAGES))],
            'asset_type_id' => ['nullable', 'integer', Rule::exists('asset_types', 'id')],
            'issue_category_id' => ['nullable', 'integer', Rule::exists('issue_categories', 'id')],
            'severity_id' => ['nullable', 'integer', Rule::exists('severities', 'id')],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:8760'],
        ]);

        if ($data['issue_category_id'] ?? null) {
            $category = IssueCategory::find($data['issue_category_id']);
            $data['asset_type_id'] ??= $category->asset_type_id;
            if ((int) $data['asset_type_id'] !== $category->asset_type_id) {
                throw ValidationException::withMessages(['issue_category_id' => 'The category belongs to a different asset type.']);
            }
        }

        $duplicate = SlaRule::where('stage', $data['stage'])->where('is_active', true)
            ->where('asset_type_id', $data['asset_type_id'] ?? null)
            ->where('issue_category_id', $data['issue_category_id'] ?? null)
            ->where('severity_id', $data['severity_id'] ?? null)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['hours' => 'An active rule with exactly these conditions exists. Edit it instead.']);
        }

        return $data + ['is_active' => true];
    }

    private function validateEscalation(Request $request): array
    {
        $data = $request->validate([
            'stage' => ['nullable', Rule::in(array_keys(self::STAGES))],
            'trigger' => ['required', Rule::in([EscalationRule::TRIGGER_BEFORE_DUE, EscalationRule::TRIGGER_AFTER_DUE])],
            'offset_hours' => ['required', 'numeric', 'min:0', 'max:8760'],
            'level' => ['required', 'integer', 'min:0', 'max:9'],
            'action' => ['required', Rule::in([EscalationRule::ACTION_REMIND, EscalationRule::ACTION_ESCALATE])],
            'notify_role_code' => ['nullable', Rule::in(['JE', 'AE', 'EE'])],
            'notify_assignee' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($data['action'] === EscalationRule::ACTION_ESCALATE && ! $data['notify_role_code'] && ! $data['notify_assignee']) {
            throw ValidationException::withMessages(['notify_role_code' => 'An escalation must notify someone.']);
        }

        return $data;
    }
}
