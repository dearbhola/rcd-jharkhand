<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Evidence\EvidenceRules;
use App\Domain\Evidence\EvidenceService;
use App\Domain\Reporting\DuplicateDetector;
use App\Domain\Reporting\LocationValidator;
use App\Domain\Reporting\ReportService;
use App\Domain\Reporting\ReportStatus;
use App\Domain\Workflow\WorkflowEngine;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gis\GeometryController;
use App\Http\Requests\Reports\StoreReportRequest;
use App\Models\AssetType;
use App\Models\AuditLog;
use App\Models\Contractor;
use App\Models\Division;
use App\Models\Report;
use App\Models\Road;
use App\Models\Severity;
use App\Models\SlaInstance;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowInstance;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(array_keys(ReportStatus::labels()))],
            'severity_id' => ['nullable', 'integer'],
            'asset_type_id' => ['nullable', 'integer'],
            'division_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'flag' => ['nullable', Rule::in(['possible_duplicate', 'location_override', 'responsibility_gap', 'delayed_submission'])],
            'road_id' => ['nullable', 'integer'],
            'km_from' => ['nullable', 'numeric', 'min:0'],
            'km_to' => ['nullable', 'numeric', 'min:0'],
            'contractor_id' => ['nullable', 'integer'],
            'issue_category_id' => ['nullable', 'integer'],
            'reporter' => ['nullable', 'string', 'max:100'],
            'contract' => ['nullable', 'string', 'max:50'],
        ]);

        $reports = Report::visibleTo($request->user())
            ->with(['road:id,code', 'section:id,code', 'category:id,name', 'severity:id,name,color', 'reporter:id,name', 'assetType:id,name'])
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('report_no', 'like', "%{$t}%")
                ->orWhereHas('road', fn ($r) => $r->where('code', 'like', "%{$t}%")->orWhere('name', 'like', "%{$t}%"))))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['severity_id'] ?? null, fn ($q, $v) => $q->where('severity_id', $v))
            ->when($filters['asset_type_id'] ?? null, fn ($q, $v) => $q->where('asset_type_id', $v))
            ->when($filters['division_id'] ?? null, fn ($q, $v) => $q->where('division_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<', Carbon::parse($v)->addDay()))
            ->when($filters['flag'] ?? null, fn ($q, $v) => $q->whereJsonContains('location_flags', $v))
            ->when($filters['road_id'] ?? null, fn ($q, $v) => $q->where('road_id', $v))
            ->when(isset($filters['km_from']), fn ($q) => $q->where('chainage_m', '>=', (int) round($filters['km_from'] * 1000)))
            ->when(isset($filters['km_to']), fn ($q) => $q->where('chainage_m', '<=', (int) round($filters['km_to'] * 1000)))
            ->when($filters['issue_category_id'] ?? null, fn ($q, $v) => $q->where('issue_category_id', $v))
            ->when($filters['contractor_id'] ?? null, fn ($q, $v) => $q->whereHas('currentResponsibility', fn ($r) => $r->where('contractor_id', $v)))
            ->when($filters['contract'] ?? null, fn ($q, $v) => $q->whereHas('currentResponsibility.contract', fn ($c) => $c->where('contract_no', 'like', "%{$v}%")))
            ->when($filters['reporter'] ?? null, fn ($q, $v) => $q->whereHas('reporter', fn ($u) => $u->where('name', 'like', "%{$v}%")->orWhere('mobile', $v)))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('reports.index', [
            'reports' => $reports,
            'filters' => $filters,
            'severities' => Severity::orderBy('rank')->pluck('name', 'id'),
            'assetTypes' => AssetType::orderBy('sort_order')->pluck('name', 'id'),
            'divisions' => Division::orderBy('code')->pluck('name', 'id'),
            'roads' => Road::orderBy('code')->pluck('code', 'id'),
            'contractors' => $request->user()->can('contractor.view') ? Contractor::orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    public function create(Request $request, Settings $settings, EvidenceService $evidence, LocationValidator $location): View
    {
        $types = AssetType::where('is_active', true)
            ->with(['issueCategories' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')->get();

        return view('reports.create', [
            'config' => [
                'clientUuid' => (string) Str::uuid(),
                'maxAccuracy' => $settings->int('gps.max_accuracy_m'),
                'radius' => $settings->int('report.location_radius_m'),
                'evidence' => $evidence->rules(EvidenceRules::CONTEXT_REPORT)->toArray(),
                'canOverride' => $location->overrideAllowed($request->user()),
                'map' => GeometryController::mapConfig($settings),
                'roadTypeId' => $types->firstWhere('code', AssetType::ROAD)?->id,
                'types' => $types->map(fn ($t) => [
                    'id' => $t->id, 'code' => $t->code, 'name' => $t->name,
                    'categories' => $t->issueCategories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'parent_id' => $c->parent_id]),
                ]),
                'severities' => Severity::active()->get(['id', 'name', 'color', 'rank']),
                'urls' => [
                    'preview' => route('reports.preview'),
                    'duplicates' => route('reports.duplicates'),
                    'store' => route('reports.store'),
                ],
            ],
        ]);
    }

    public function store(StoreReportRequest $request, ReportService $reports): JsonResponse|RedirectResponse
    {
        ['report' => $report, 'created' => $created] = $reports->submit($request->user(), $request->submission(), $request->file('evidence', []));

        $message = $created ? "Report {$report->report_no} submitted." : "Report {$report->report_no} was already received.";
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'report_no' => $report->report_no, 'url' => route('reports.show', $report)], $created ? 201 : 200);
        }

        return redirect()->route('reports.show', $report)->with('success', $message);
    }

    public function show(Request $request, Report $report, DuplicateDetector $duplicates, Settings $settings, WorkflowEngine $engine, LocationValidator $location, EvidenceService $evidence): View
    {
        Gate::authorize('view', $report);

        $report->load([
            'reporter:id,name,mobile', 'road', 'section', 'asset.type', 'assetType', 'category.parent', 'severity', 'division',
            'responsibilities.contractor', 'responsibilities.contract', 'responsibilities.je', 'responsibilities.ae', 'responsibilities.ee',
            'evidences' => fn ($q) => $q->where('evidenceable_type', 'report')->orderBy('id'),
            'repairAttempts' => fn ($q) => $q->with(['submitter:id,name', 'rejecter:id,name', 'contractor:id,name', 'evidences',
                'inspections' => fn ($i) => $i->with(['inspector:id,name', 'evidences'])]),
            'inspections' => fn ($q) => $q->whereNull('repair_attempt_id')->with(['inspector:id,name', 'evidences']),
            'links.linkedReport:id,report_no',
        ]);
        $user = $request->user();
        $instance = $engine->activeInstance($report);
        $actions = $engine->availableActions($report, $user);
        $instanceIds = WorkflowInstance::withoutGlobalScopes()->where('report_id', $report->id)->pluck('id');

        return view('reports.show', [
            'report' => $report,
            'responsibility' => $report->responsibilities->firstWhere('is_current', true),
            'duplicates' => $user->can('report.view_all') && ! in_array($report->status, ReportStatus::terminal(), true)
                ? $duplicates->near($report->latitude, $report->longitude, $report->issue_category_id, $report->id, $report->created_at)
                    ->filter(fn ($d) => $user->can('view', $d['report']))
                : collect(),
            'history' => $user->can('audit.view') || $user->can('report.view_all')
                ? AuditLog::where('auditable_type', 'report')->where('auditable_id', $report->id)->orderBy('id')->get()
                : collect(),
            'mapConfig' => GeometryController::mapConfig($settings),
            'instance' => $instance,
            'actions' => $actions,
            'actionConfig' => [
                'canOverride' => $location->overrideAllowed($user),
                'maxAccuracy' => $settings->int('gps.max_accuracy_m'),
                'site' => ['lat' => $report->latitude, 'lng' => $report->longitude],
                'evidence' => [
                    'repair' => $evidence->rules(EvidenceRules::CONTEXT_REPAIR)->toArray(),
                    'inspection' => $evidence->rules(EvidenceRules::CONTEXT_INSPECTION)->toArray(),
                ],
            ],
            'timeline' => WorkflowAction::with(['actor:id,name', 'fromStep:id,name,code', 'toStep:id,name,code'])
                ->whereIn('workflow_instance_id', $instanceIds)->orderBy('id')->get(),
            'holders' => $holders = ($instance ? $instance->activeAssignments()->with(['user:id,name,contractor_id', 'originalUser:id,name'])->get() : collect()),
            'reassignCandidates' => $user->can('workflow.reassign') && $holders->isNotEmpty()
                ? User::where('status', 'active')
                    ->whereHas('roles', fn ($q) => $q->where('code', $holders->first()->role_code))
                    ->when($holders->first()->role_code === 'CONTRACTOR', fn ($q) => $q->where('contractor_id', $holders->first()->user?->contractor_id))
                    ->orderBy('name')->get(['id', 'name', 'employee_code'])
                : collect(),
            'sla' => $instance ? SlaInstance::where('workflow_instance_id', $instance->id)->whereNull('completed_at')->first() : null,
            'slaHistory' => SlaInstance::where('report_id', $report->id)
                ->with(['escalations.rule:id,action,notify_role_code,level', 'responsibleUser:id,name'])->orderBy('id')->get(),
        ]);
    }
}
