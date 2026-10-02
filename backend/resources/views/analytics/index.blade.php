@php($groups = ['period' => ['Period reports', 'calendar3'], 'group' => ['Breakdowns', 'diagram-3'], 'sla' => ['SLA', 'stopwatch'], 'rejections' => ['Quality', 'x-octagon'], 'reopenings' => ['Quality', 'arrow-counterclockwise']])
<x-app-layout title="Reports & exports" :breadcrumbs="['Reports & exports']">
    <x-page-header title="Reports & exports" subtitle="Official reports exclude test data. Each can be exported to CSV, Excel or PDF." />
    <div class="row g-3">
        @foreach ($types as $key => [$label, $kind])
            <div class="col-sm-6 col-lg-4 col-xxl-3">
                <a href="{{ route('analytics.show', $key) }}" class="card stat-link h-100 text-decoration-none">
                    <div class="card-body d-flex gap-3 align-items-center">
                        <i class="bi bi-{{ $groups[$kind][1] }} fs-4 text-primary" aria-hidden="true"></i>
                        <div><div class="fw-semibold text-body">{{ $label }}</div><div class="small text-muted">{{ $groups[$kind][0] }}</div></div>
                    </div>
                </a>
            </div>
        @endforeach
        @can('performance.view')
            <div class="col-sm-6 col-lg-4 col-xxl-3">
                <a href="{{ route('performance.index') }}" class="card stat-link h-100 text-decoration-none">
                    <div class="card-body d-flex gap-3 align-items-center"><i class="bi bi-graph-up fs-4 text-primary" aria-hidden="true"></i>
                        <div><div class="fw-semibold text-body">Contractor performance report</div><div class="small text-muted">Performance</div></div></div>
                </a>
            </div>
        @endcan
    </div>
</x-app-layout>
