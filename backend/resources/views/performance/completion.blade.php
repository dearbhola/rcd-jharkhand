@php($defs = \App\Domain\Performance\PerformanceService::METRICS)
@php($c = $contract)
@php($q = ['contractor_id' => $c->contractor_id, 'contract_id' => $c->id, 'period' => 'maintenance'])
<x-app-layout :title="'Completion report '.$c->contract_no" :breadcrumbs="['Contracts' => route('contracts.index'), $c->contract_no => route('contracts.show', $c), 'Completion report']">
    <x-page-header title="Contract completion report" :subtitle="$c->contract_no.' · '.$c->contractor->name"><x-export-bar /></x-page-header>
    <div @class(['alert', 'alert-success' => $ended && $metrics['tasks_open'] === 0, 'alert-warning' => $ended && $metrics['tasks_open'] > 0, 'alert-info' => ! $ended])>
        <strong>Final status:</strong> {{ $final_status }}</div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card h-100"><div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5">Contract</dt><dd class="col-7">{{ $c->contract_no }} <x-test-badge :model="$c" /><div class="text-muted">{{ $c->name }}</div></dd>
                    <dt class="col-5">Contractor</dt><dd class="col-7">{{ $c->contractor->name }}</dd>
                    <dt class="col-5">Division</dt><dd class="col-7">{{ $c->division?->name ?? '—' }}</dd>
                    <dt class="col-5">Agreement</dt><dd class="col-7">{{ $c->agreement_no ?? '—' }} {{ $c->agreement_date ? '('.d($c->agreement_date).')' : '' }}</dd>
                    <dt class="col-5">Contract period</dt><dd class="col-7">{{ d($c->start_date) }} – {{ d($c->end_date) }}</dd>
                    <dt class="col-5">Maintenance period</dt><dd class="col-7"><strong>{{ d($c->maintenance_start_date) }} – {{ d($c->maintenance_end_date) }}</strong></dd>
                    <dt class="col-5">Value</dt><dd class="col-7">{{ $c->contract_value ? '₹ '.number_format((float) $c->contract_value, 2) : '—' }}</dd>
                </dl>
            </div></div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">Roads &amp; sections covered</div>
                <div class="table-responsive"><table class="table table-sm small mb-0">
                    <thead><tr><th>Road</th><th>Section</th><th class="text-end">km</th><th>Effective</th></tr></thead>
                    <tbody>@foreach ($coverage as $m)<tr><td>{{ $m->road->code }} — {{ $m->road->name }}</td><td>{{ $m->section?->code ?? 'Range' }}</td>
                        <td class="text-end text-nowrap">{{ km($m->start_chainage_m) }}–{{ km($m->end_chainage_m) }}</td><td class="text-nowrap">{{ d($m->effective_from) }} → {{ $m->effective_to ? d($m->effective_to) : 'open' }}</td></tr>@endforeach</tbody>
                </table></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach (['tasks_total' => 'Total defects', 'tasks_completed' => 'Repairs completed', 'tasks_open' => 'Still open', 'sla_compliance' => 'SLA compliance',
            'sla_breaches' => 'SLA breaches', 'avg_repair_hours' => 'Avg. repair time', 'rejections_je' => 'JE rejections', 'rejections_ae' => 'AE rejections',
            'rejections_ee' => 'EE rejections', 'reopened_repairs' => 'Reopened repairs', 'repeat_defects' => 'Repeat defects', 'repair_attempts' => 'Repair attempts'] as $k => $label)
            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('performance.drill', ['metric' => $k] + $q) }}" class="card stat-card stat-link h-100 text-decoration-none">
                    <div class="card-body"><div class="stat-value text-body">{{ $metrics[$k] === null ? '—' : $metrics[$k].($defs[$k][1] === 'percent' ? '%' : ($defs[$k][1] === 'hours' ? ' h' : '')) }}</div>
                        <div class="stat-label">{{ $label }}</div></div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card h-100"><div class="card-header">Evidence summary</div>
                <table class="table table-sm small mb-0"><thead><tr><th>Stage</th><th>Type</th><th class="text-end">Files</th></tr></thead>
                    <tbody>@forelse ($evidence as $e)<tr><td>{{ $e['stage'] }}</td><td>{{ $e['kind'] }}</td><td class="text-end">{{ $e['count'] }}</td></tr>@empty<tr><td colspan="3" class="text-muted">None</td></tr>@endforelse</tbody></table>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card h-100"><div class="card-header">Inspection history ({{ $inspections->count() }})</div>
                <div class="table-responsive" style="max-height: 320px"><table class="table table-sm small mb-0">
                    <thead><tr><th>Date</th><th>Report</th><th>Attempt</th><th>Stage</th><th>Inspector</th><th>Decision</th></tr></thead>
                    <tbody>@forelse ($inspections as $i)<tr><td class="text-nowrap">{{ d($i->inspected_at) }}</td><td><a href="{{ route('reports.show', $i->report_id) }}">{{ $i->report?->report_no }}</a></td>
                        <td>{{ $i->repairAttempt?->attempt_no }}</td><td>{{ str_replace('_', ' ', $i->stage) }}</td><td>{{ $i->inspector?->name }}</td>
                        <td class="{{ $i->decision === 'rejected' ? 'text-danger' : 'text-success' }}">{{ $i->decision }}</td></tr>@empty<tr><td colspan="6" class="text-muted">No inspections.</td></tr>@endforelse</tbody>
                </table></div>
            </div>
        </div>
    </div>

    <div class="card mb-3"><div class="card-header">Defects reported during the maintenance period ({{ $tasks->count() }})</div>
        <div class="table-responsive"><table class="table table-sm small mb-0 align-middle">
            <thead><tr><th>Report</th><th>Road / km</th><th>Damage</th><th>Severity</th><th>Reported</th><th class="text-end">Attempts</th><th>Status</th></tr></thead>
            <tbody>@forelse ($tasks as $r)<tr><td><a href="{{ route('reports.show', $r) }}">{{ $r->report_no }}</a></td><td>{{ $r->road?->code }} km {{ km($r->chainage_m) }}</td>
                <td>{{ $r->category?->name }}</td><td>{{ $r->severity?->name }}</td><td class="text-nowrap">{{ d($r->created_at) }}</td><td class="text-end">{{ $r->repairAttempts->count() }}</td>
                <td><x-report-status :status="$r->status" /></td></tr>@empty<tr><td colspan="7" class="text-muted text-center">No defects reported.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
    <p class="small text-muted"><i class="bi bi-info-circle" aria-hidden="true"></i> {{ $disclaimer }} Generated {{ d($generated_at, true) }}.</p>
</x-app-layout>
