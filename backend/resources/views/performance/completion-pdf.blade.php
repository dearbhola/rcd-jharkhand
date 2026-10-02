@php($defs = \App\Domain\Performance\PerformanceService::METRICS)
@php($c = $contract)
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Completion report {{ $c->contract_no }}</title>
<style>
    @page { margin: 16mm 12mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
    h1 { font-size: 16px; color: #0b2a4a; margin: 0; } h2 { font-size: 11px; color: #0b2a4a; margin: 12px 0 4px; border-bottom: 1px solid #0b2a4a; }
    table { width: 100%; border-collapse: collapse; } th { background: #0b2a4a; color: #fff; text-align: left; padding: 3px; font-size: 8.5px; }
    td { border-bottom: 1px solid #ddd; padding: 3px; } .kv td:first-child { width: 32%; color: #555; }
    .status { padding: 6px; border: 1px solid #0b2a4a; margin: 8px 0; } .test { border: 1.5px solid #6f42c1; color: #6f42c1; font-weight: bold; padding: 4px; text-align: center; }
    .grid td { width: 25%; border: 1px solid #ddd; padding: 5px; } .big { font-size: 14px; font-weight: bold; } .small { color: #666; font-size: 8px; }
</style></head><body>
<h1>Contract completion report</h1>
<div>{{ $c->contract_no }} · {{ $c->contractor->name }} · generated {{ d($generated_at, true) }}</div>
@if ($includesTest)<div class="test">TEST DATA INCLUDED — NOT AN OFFICIAL REPORT</div>@endif
<div class="status"><strong>Final status:</strong> {{ $final_status }}</div>
<h2>Contract</h2>
<table class="kv">
    <tr><td>Name of work</td><td>{{ $c->name }}</td></tr>
    <tr><td>Contractor</td><td>{{ $c->contractor->name }}</td></tr>
    <tr><td>Division</td><td>{{ $c->division?->name }}</td></tr>
    <tr><td>Agreement</td><td>{{ $c->agreement_no }} {{ $c->agreement_date ? '('.d($c->agreement_date).')' : '' }}</td></tr>
    <tr><td>Maintenance period</td><td>{{ d($c->maintenance_start_date) }} – {{ d($c->maintenance_end_date) }}</td></tr>
</table>
<h2>Roads &amp; sections covered</h2>
<table><tr><th>Road</th><th>Section</th><th>km</th><th>Effective</th></tr>
    @foreach ($coverage as $m)<tr><td>{{ $m->road->code }} — {{ $m->road->name }}</td><td>{{ $m->section?->code ?? 'Range' }}</td><td>{{ km($m->start_chainage_m) }}–{{ km($m->end_chainage_m) }}</td><td>{{ d($m->effective_from) }} → {{ $m->effective_to ? d($m->effective_to) : 'open' }}</td></tr>@endforeach
</table>
<h2>Performance in the maintenance period</h2>
<table class="grid">
    @foreach (array_chunk(array_keys($defs), 4) as $chunk)
        <tr>@foreach ($chunk as $k)<td><div class="big">{{ $metrics[$k] === null ? '—' : $metrics[$k].($defs[$k][1] === 'percent' ? '%' : ($defs[$k][1] === 'hours' ? ' h' : '')) }}</div><div>{{ $defs[$k][0] }}</div><div class="small">{{ $defs[$k][2] }}</div></td>@endforeach</tr>
    @endforeach
</table>
<h2>Evidence summary</h2>
<table><tr><th>Stage</th><th>Type</th><th>Files</th></tr>@foreach ($evidence as $e)<tr><td>{{ $e['stage'] }}</td><td>{{ $e['kind'] }}</td><td>{{ $e['count'] }}</td></tr>@endforeach</table>
<h2>Defects ({{ $tasks->count() }})</h2>
<table><tr><th>Report</th><th>Road / km</th><th>Damage</th><th>Severity</th><th>Reported</th><th>Attempts</th><th>Status</th></tr>
    @foreach ($tasks as $r)<tr><td>{{ $r->report_no }}</td><td>{{ $r->road?->code }} km {{ km($r->chainage_m) }}</td><td>{{ $r->category?->name }}</td><td>{{ $r->severity?->name }}</td><td>{{ d($r->created_at) }}</td><td>{{ $r->repairAttempts->count() }}</td><td>{{ \App\Domain\Reporting\ReportStatus::labels()[$r->status][0] ?? $r->status }}</td></tr>@endforeach
</table>
<h2>Inspection history ({{ $inspections->count() }})</h2>
<table><tr><th>Date</th><th>Report</th><th>Attempt</th><th>Stage</th><th>Inspector</th><th>Decision</th><th>Comment</th></tr>
    @foreach ($inspections as $i)<tr><td>{{ d($i->inspected_at) }}</td><td>{{ $i->report?->report_no }}</td><td>{{ $i->repairAttempt?->attempt_no }}</td><td>{{ $i->stage }}</td><td>{{ $i->inspector?->name }}</td><td>{{ $i->decision }}</td><td>{{ $i->comment }}</td></tr>@endforeach
</table>
<p class="small" style="margin-top: 10px">{{ $disclaimer }}</p>
</body></html>
