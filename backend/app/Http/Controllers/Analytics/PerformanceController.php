<?php

namespace App\Http\Controllers\Analytics;

use App\Domain\Analytics\Exporter;
use App\Domain\Analytics\Table;
use App\Domain\Performance\DrillDown;
use App\Domain\Performance\PerformanceScope;
use App\Domain\Performance\PerformanceService;
use App\Http\Controllers\Concerns\HandlesExports;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Contractor;
use App\Models\Division;
use App\Models\Road;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contractor performance (§37–38): comparison table, per-contractor detail, drill-down.
 */
class PerformanceController extends Controller
{
    use HandlesExports;

    /** Columns of the comparison table. */
    private const SUMMARY = ['tasks_total', 'tasks_completed', 'tasks_open', 'tasks_overdue', 'sla_compliance', 'avg_repair_hours',
        'repair_attempts', 'reopened_repairs', 'rejections_je', 'rejections_ae', 'rejections_ee', 'repeat_defects'];

    /** On-screen columns (rejections are shown together as JE · AE · EE); exports keep SUMMARY. */
    private const ON_SCREEN = ['tasks_total', 'tasks_completed', 'tasks_open', 'tasks_overdue', 'sla_compliance', 'avg_repair_hours',
        'reopened_repairs', 'rejections', 'repeat_defects'];

    public function __construct(private readonly PerformanceService $performance) {}

    public function index(Request $request, Exporter $exporter): Response|View
    {
        [$format, $test] = $this->exportMode($request);
        $input = $this->filters($request);
        $scope = PerformanceScope::fromInput($input);

        $contractors = Contractor::query()
            ->when($scope->contractorId, fn ($q, $v) => $q->whereKey($v))
            ->when($scope->divisionId, fn ($q, $v) => $q->whereHas('contracts', fn ($c) => $c->where('division_id', $v)))
            ->orderBy('name')->get(['id', 'name', 'code', 'status', 'is_test']);

        $rows = $contractors->map(fn ($c) => ['contractor' => $c, 'url' => route('performance.show', ['contractor' => $c] + $this->scopeQuery($input))]
            + $this->pick($this->performance->metrics($scope->withContractor($c->id))))->all();

        $table = new Table('Contractor performance', ['name' => 'Contractor'] + $this->headings(),
            array_map(fn ($r) => ['name' => $r['contractor']->name] + $r, $rows), $this->labels($input, $scope), $test,
            ['Derived from workflow records; not editable. Does not award, rank or recommend contractors.']);

        if ($format) {
            return $exporter->download($table, $format, 'contractor-performance');
        }

        return view('performance.index', ['rows' => $rows, 'metrics' => self::ON_SCREEN, 'input' => $input, 'scope' => $scope, 'query' => $this->scopeQuery($input)] + $this->lookups());
    }

    public function show(Request $request, Contractor $contractor, Exporter $exporter): Response|View
    {
        [$format, $test] = $this->exportMode($request);
        $input = $this->filters($request);
        $scope = PerformanceScope::fromInput($input)->withContractor($contractor->id);
        $metrics = $this->performance->metrics($scope);

        $contracts = Contract::where('contractor_id', $contractor->id)->orderByDesc('maintenance_end_date')->get();
        $perContract = $contracts->map(fn ($c) => ['contract' => $c]
            + $this->pick($this->performance->metrics(new PerformanceScope($contractor->id, $c->id, $scope->divisionId, $scope->roadId, $scope->sectionId, $scope->from, $scope->to))));

        if ($format) {
            $rows = collect(PerformanceService::METRICS)->map(fn ($m, $k) => ['metric' => $m[0], 'value' => $metrics[$k], 'meaning' => $m[2]])->values()->all();

            return $exporter->download(new Table("Contractor performance — {$contractor->name}", ['metric' => 'Metric', 'value' => 'Value', 'meaning' => 'Definition'],
                $rows, $this->labels($input, $scope), $test, ['Derived from workflow records; not editable.']), $format, 'performance-'.$contractor->code);
        }

        return view('performance.show', [
            'contractor' => $contractor, 'metrics' => $metrics, 'perContract' => $perContract, 'summary' => self::ON_SCREEN,
            'input' => $input, 'scope' => $scope, 'query' => $this->scopeQuery($input) + ['contractor_id' => $contractor->id],
        ] + $this->lookups());
    }

    public function drill(Request $request, string $metric, DrillDown $drill, Exporter $exporter): Response|View
    {
        abort_unless(isset(PerformanceService::METRICS[$metric]), 404);
        [$format, $test] = $this->exportMode($request);
        $input = $this->filters($request);
        $scope = PerformanceScope::fromInput($input);
        $table = $drill->table($metric, $scope, $test, $this->labels($input, $scope));

        if ($format) {
            return $exporter->download($table, $format, 'performance-'.$metric);
        }

        return view('performance.drill', ['table' => $table, 'metric' => $metric, 'input' => $input]);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'contractor_id' => ['nullable', 'integer'], 'contract_id' => ['nullable', 'integer'], 'division_id' => ['nullable', 'integer'],
            'road_id' => ['nullable', 'integer'], 'road_section_id' => ['nullable', 'integer'],
            'fy' => ['nullable', 'regex:/^\d{4}-\d{2}$/'], 'period' => ['nullable', Rule::in(['maintenance'])],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    private function scopeQuery(array $input): array
    {
        return array_filter($input, fn ($v) => $v !== null && $v !== '');
    }

    /** @return array<string, string> */
    private function headings(): array
    {
        return collect(self::SUMMARY)->mapWithKeys(fn ($k) => [$k => PerformanceService::METRICS[$k][0].match (PerformanceService::METRICS[$k][1]) {
            'percent' => ' (%)', 'hours' => ' (h)', default => ''
        }])->all();
    }

    private function pick(array $metrics): array
    {
        return array_intersect_key($metrics, array_flip(self::SUMMARY)); // SUMMARY includes rejections_je/ae/ee
    }

    /** @return array<string, string> */
    private function labels(array $input, PerformanceScope $s): array
    {
        return array_filter([
            'Contractor' => $s->contractorId ? Contractor::withTrashed()->find($s->contractorId)?->name : null,
            'Contract' => $s->contractId ? Contract::find($s->contractId)?->contract_no : null,
            'Division' => $s->divisionId ? Division::find($s->divisionId)?->name : null,
            'Road' => $s->roadId ? Road::find($s->roadId)?->code : null,
            'Period' => $s->periodLabel ?? 'All time',
        ]);
    }

    private function lookups(): array
    {
        return [
            'contractors' => Contractor::orderBy('name')->pluck('name', 'id'),
            'contracts' => Contract::orderBy('contract_no')->pluck('contract_no', 'id'),
            'divisions' => Division::orderBy('code')->pluck('name', 'id'),
            'roads' => Road::orderBy('code')->pluck('code', 'id'),
            'years' => PerformanceScope::financialYears(),
        ];
    }
}
