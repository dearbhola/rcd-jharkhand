<x-app-layout title="Dashboard" :breadcrumbs="['Dashboard']">
    @include('dashboard._header')
    @php($steps = $d['tasks']->groupBy(fn ($t) => $t['assignment']->step->code))
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-4 col-xxl"><x-stat-tile label="Pending AE reviews" :value="($steps['AE_REVIEW'] ?? collect())->count()" icon="search" tone="info" :href="route('tasks.index')" /></div>
        <div class="col-6 col-lg-4 col-xxl"><x-stat-tile label="Forwarded to EE" :value="$d['forwarded_to_ee']" icon="send-check" :href="route('reports.index', ['status' => 'EE_APPROVAL'])" /></div>
        <div class="col-6 col-lg-4 col-xxl"><x-stat-tile label="Rejected by me (30 d)" :value="$d['rejected_by_me']" icon="x-octagon" tone="danger" /></div>
        <div class="col-6 col-lg-4 col-xxl"><x-stat-tile label="Overdue in jurisdiction" :value="$d['overdue']" icon="stopwatch" :tone="$d['overdue'] ? 'danger' : 'secondary'" /></div>
        <div class="col-6 col-lg-4 col-xxl"><x-stat-tile label="Open in jurisdiction" :value="$d['open']" icon="exclamation-triangle" tone="warning" :href="route('reports.index')" /></div>
    </div>
    <div class="row g-3">
        <div class="col-xxl-6">@include('dashboard._tasks', ['tasks' => $d['tasks']])</div>
        <div class="col-md-6 col-xxl-3">@include('dashboard._roads', ['myRoads' => $d['my_roads']])</div>
        <div class="col-md-6 col-xxl-3">@include('dashboard._status-bars', ['byStatus' => $d['by_status']])</div>
    </div>
</x-app-layout>
