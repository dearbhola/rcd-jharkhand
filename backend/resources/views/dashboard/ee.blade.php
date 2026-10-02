@php($steps = $d['tasks']->groupBy(fn ($t) => $t['assignment']->step->code))
<x-app-layout title="Dashboard" :breadcrumbs="['Dashboard']">
    <x-slot:head>@vite('resources/js/dashboard/charts.js')</x-slot:head>
    @include('dashboard._header')
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-4 col-xxl"><x-stat-tile label="Pending final approvals" :value="($steps['EE_APPROVAL'] ?? collect())->count()" icon="clipboard-check" tone="info" :href="route('tasks.index')" /></div>
        <div class="col-6 col-lg-4 col-xxl"><x-stat-tile label="Approved (30 d)" :value="$d['approved_30d']" icon="check2-circle" tone="success" :href="route('reports.index', ['status' => 'CLOSED'])" /></div>
        <div class="col-6 col-lg-4 col-xxl"><x-stat-tile label="Rejected by EE (30 d)" :value="$d['ee_rejected_30d']" icon="x-octagon" tone="danger" /></div>
        <div class="col-6 col-lg-4 col-xxl"><x-stat-tile label="Overdue in division" :value="$d['overdue']" icon="stopwatch" :tone="$d['overdue'] ? 'danger' : 'secondary'" /></div>
        <div class="col-6 col-lg-4 col-xxl"><x-stat-tile label="Open in division" :value="$d['open']" icon="exclamation-triangle" tone="warning" :href="route('reports.index')" /></div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">Reported vs closed — last 6 months
                    <button class="btn btn-sm btn-link p-0" type="button" data-bs-toggle="collapse" data-bs-target="#trendTable" aria-expanded="false">Table view</button></div>
                <div class="card-body viz-root">
                    <div style="height: 240px"><canvas id="trendChart" data-trend='@json($d['trend'])' role="img" aria-label="Bar chart of reports reported and closed per month"></canvas></div>
                    <div class="collapse" id="trendTable">
                        <table class="table table-sm small mt-3 mb-0">
                            <thead><tr><th>Month</th><th class="text-end">Reported</th><th class="text-end">Closed</th></tr></thead>
                            <tbody>@foreach ($d['trend'] as $row)<tr><td>{{ $row['month'] }}</td><td class="text-end">{{ $row['reported'] }}</td><td class="text-end">{{ $row['closed'] }}</td></tr>@endforeach</tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">@include('dashboard._status-bars', ['byStatus' => $d['by_status']])</div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-lg-8">@include('dashboard._tasks', ['tasks' => $d['tasks']])</div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Open by severity</div>
                <ul class="list-group list-group-flush small">
                    @forelse ($d['by_severity'] as $s)
                        <li class="list-group-item d-flex justify-content-between"><span><span class="badge" style="background: {{ $s->color }}">{{ $s->name }}</span></span><strong>{{ $s->n }}</strong></li>
                    @empty
                        <li class="list-group-item text-muted">No open reports.</li>
                    @endforelse
                </ul>
                <div class="card-footer bg-white"><a href="{{ route('map.index') }}" class="small"><i class="bi bi-map" aria-hidden="true"></i> Road condition map</a></div>
            </div>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">Contractors in division
                    @if (Route::has('performance.index'))<a href="{{ route('performance.index') }}" class="small">Performance</a>@endif</div>
                <div class="table-responsive">
                    <table class="table table-sm small mb-0 align-middle">
                        <thead><tr><th>Contractor</th><th class="text-end">Open</th><th class="text-end">Overdue</th><th class="text-end">Rejected</th><th class="text-end">Closed</th></tr></thead>
                        <tbody>
                        @forelse ($d['contractors'] as $c)
                            <tr>
                                <td><a href="{{ route('contractors.show', $c['contractor']) }}">{{ $c['contractor']->name }}</a></td>
                                <td class="text-end">{{ $c['open'] }}</td>
                                <td class="text-end {{ $c['overdue'] ? 'text-danger fw-semibold' : '' }}">{{ $c['overdue'] }}</td>
                                <td class="text-end">{{ $c['rejections'] }}</td>
                                <td class="text-end">{{ $c['closed'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted text-center">No contractor work yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
