<x-app-layout title="Dashboard" :breadcrumbs="['Dashboard']">
    @include('dashboard._header')
    <div class="row g-3 mb-3">
        @foreach ($d['masters'] as [$label, $value, $icon, $route])
            <div class="col-6 col-md-4 col-xl-2"><x-stat-tile :label="$label" :value="$value" :icon="$icon" :href="route($route)" /></div>
        @endforeach
    </div>
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-stat-tile label="Open reports" :value="$d['open']" icon="exclamation-triangle" tone="warning" :href="route('reports.index')" /></div>
        <div class="col-6 col-xl-3"><x-stat-tile label="Overdue stages" :value="$d['overdue']" icon="stopwatch" :tone="$d['overdue'] ? 'danger' : 'secondary'" /></div>
        <div class="col-6 col-xl-3"><x-stat-tile label="Reports blocked by missing mapping" :value="$d['gaps']" icon="diagram-3" :tone="$d['gaps'] ? 'danger' : 'secondary'" :href="route('reports.index', ['flag' => 'responsibility_gap'])" /></div>
        <div class="col-6 col-xl-3"><x-stat-tile label="Sections with incomplete JE/AE/EE" :value="$d['unmapped_sections']" icon="person-exclamation" :tone="$d['unmapped_sections'] ? 'warning' : 'secondary'" :href="route('responsibility.index', ['unmapped' => 1])" /></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-4">@include('dashboard._status-bars', ['byStatus' => $d['by_status']])</div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Data & system health</div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between"><a href="{{ route('roads.index') }}">Active roads without geometry</a><strong class="{{ $d['roads_without_geometry'] ? 'text-warning' : '' }}">{{ $d['roads_without_geometry'] }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between">Failed external notifications (7 d)<strong class="{{ $d['failed_deliveries'] ? 'text-danger' : '' }}">{{ $d['failed_deliveries'] }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><a href="{{ route('admin.settings.index') }}">System settings</a><i class="bi bi-chevron-right" aria-hidden="true"></i></li>
                    <li class="list-group-item d-flex justify-content-between"><a href="{{ route('admin.sla.index') }}">SLA &amp; escalation rules</a><i class="bi bi-chevron-right" aria-hidden="true"></i></li>
                </ul>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">Recent activity <a href="{{ route('admin.audit.index') }}" class="small">Audit log</a></div>
                <ul class="list-group list-group-flush small">
                    @foreach ($d['recent_audit'] as $log)
                        <li class="list-group-item"><code>{{ $log->action }}</code> · {{ $log->user_name }} <span class="text-muted">· {{ $log->created_at->diffForHumans() }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
