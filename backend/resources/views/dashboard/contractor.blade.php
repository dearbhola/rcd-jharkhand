<x-app-layout title="Dashboard" :breadcrumbs="['Dashboard']">
    @include('dashboard._header')
    <p class="text-muted small mb-3">{{ $d['contractor']?->name }} · {{ $d['active_contracts'] }} contract(s) in maintenance</p>
    <div class="row g-3 mb-3">
        @foreach ($d['buckets'] as [$label, $value, $icon, $tone, $status])
            <div class="col-6 col-md-4 col-xl-3"><x-stat-tile :label="$label" :value="$value" :icon="$icon" :tone="$value ? $tone : 'secondary'" :href="$status ? route('reports.index', ['status' => $status]) : null" /></div>
        @endforeach
    </div>
    <div class="row g-3">
        <div class="col-lg-8">@include('dashboard._tasks', ['tasks' => $d['tasks'], 'title' => 'Assigned repairs'])</div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Recent rejections</div>
                <div class="list-group list-group-flush small">
                    @forelse ($d['recent_rejections'] as $a)
                        <a href="{{ route('reports.show', $a->report_id) }}" class="list-group-item list-group-item-action">
                            <strong>{{ $a->report?->report_no }}</strong> · attempt {{ $a->attempt_no }} · {{ $a->rejected_stage }}
                            <div class="text-danger">{{ \Illuminate\Support\Str::limit($a->rejection_reason, 90) }}</div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted">No rejections.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
