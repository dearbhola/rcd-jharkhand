<?php

namespace App\Http\Controllers\Analytics;

use App\Domain\Analytics\Exporter;
use App\Domain\Analytics\Table;
use App\Domain\Analytics\TableExport;
use App\Domain\Performance\CompletionReportService;
use App\Domain\Performance\PerformanceService;
use App\Domain\Reporting\ReportStatus;
use App\Http\Controllers\Concerns\HandlesExports;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contract completion report (§39): on screen, PDF and Excel.
 */
class ContractCompletionController extends Controller
{
    use HandlesExports;

    public function show(Request $request, Contract $contract, CompletionReportService $reports): Response|View
    {
        [$format, $test] = $this->exportMode($request);
        if ($format) {
            $contract = Contract::withoutGlobalScopes()->findOrFail($contract->id);
            abort_if($contract->is_test && ! $test, 404, 'Test contract — tick "include test data" to export it.');
        }

        $data = $reports->build($contract) + ['includesTest' => $test];
        $name = 'completion-'.Str::slug($contract->contract_no).'-'.now()->format('Ymd');

        return match ($format) {
            'pdf' => Pdf::loadView('performance.completion-pdf', $data)->setPaper('a4')->download("{$name}.pdf"),
            'xlsx' => Excel::download($this->workbook($data), "{$name}.xlsx"),
            'csv' => app(Exporter::class)->download($this->tasksTable($data), 'csv', $name),
            default => view('performance.completion', $data),
        };
    }

    private function summaryTable(array $d): Table
    {
        $c = $d['contract'];
        $rows = [
            ['item' => 'Contract', 'value' => $c->contract_no.($c->name ? ' — '.$c->name : '')],
            ['item' => 'Contractor', 'value' => $c->contractor->name],
            ['item' => 'Agreement', 'value' => trim(($c->agreement_no ?? '').' '.($c->agreement_date ? d($c->agreement_date) : ''))],
            ['item' => 'Maintenance period', 'value' => d($c->maintenance_start_date).' – '.d($c->maintenance_end_date)],
            ['item' => 'Roads covered', 'value' => $d['coverage']->pluck('road.code')->unique()->join(', ')],
            ['item' => 'Final status', 'value' => $d['final_status']],
        ];
        foreach (PerformanceService::METRICS as $key => [$label]) {
            $rows[] = ['item' => $label, 'value' => $d['metrics'][$key]];
        }

        return new Table('Contract completion report', ['item' => 'Item', 'value' => 'Value'], $rows,
            ['Contract' => $c->contract_no], $d['includesTest'], [$d['disclaimer']]);
    }

    private function tasksTable(array $d): Table
    {
        $rows = $d['tasks']->map(fn ($r) => [
            'report' => $r->report_no, 'road' => $r->road?->code.' / '.$r->section?->code.' km '.km($r->chainage_m),
            'category' => $r->category?->name, 'severity' => $r->severity?->name, 'reported' => $r->created_at,
            'attempts' => $r->repairAttempts->count(), 'rejected_at' => $r->repairAttempts->where('outcome', 'rejected')->pluck('rejected_stage')->join(', '),
            'status' => ReportStatus::labels()[$r->status][0] ?? $r->status, 'closed' => $r->closed_at,
        ])->all();

        return new Table('Repair tasks — '.$d['contract']->contract_no, ['report' => 'Report', 'road' => 'Road / section / km', 'category' => 'Damage',
            'severity' => 'Severity', 'reported' => 'Reported', 'attempts' => 'Attempts', 'rejected_at' => 'Rejected at', 'status' => 'Status', 'closed' => 'Closed'],
            $rows, ['Contract' => $d['contract']->contract_no], $d['includesTest']);
    }

    private function workbook(array $d): Export
    {
        $sheets = [
            new TableExport($this->summaryTable($d)),
            new TableExport($this->tasksTable($d)),
            new TableExport(new Table('Inspections', ['date' => 'Date', 'report' => 'Report', 'attempt' => 'Attempt', 'stage' => 'Stage', 'by' => 'Inspector', 'decision' => 'Decision', 'comment' => 'Comment'],
                $d['inspections']->map(fn ($i) => ['date' => $i->inspected_at, 'report' => $i->report?->report_no, 'attempt' => $i->repairAttempt?->attempt_no,
                    'stage' => $i->stage, 'by' => $i->inspector?->name, 'decision' => $i->decision, 'comment' => $i->comment])->all())),
        ];

        return new class($sheets) implements Export, WithMultipleSheets
        {
            public function __construct(private readonly array $sheets) {}

            public function sheets(): array
            {
                return $this->sheets;
            }
        };
    }
}
