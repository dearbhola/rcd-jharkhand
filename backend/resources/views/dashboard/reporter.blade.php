<x-app-layout title="Dashboard" :breadcrumbs="['Dashboard']">
    @include('dashboard._header')
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat-tile label="Submitted" :value="$d['submitted']" icon="send" :href="route('reports.index')" /></div>
        <div class="col-6 col-lg-3"><x-stat-tile label="In progress" :value="$d['open']" icon="hourglass-split" tone="warning" /></div>
        <div class="col-6 col-lg-3"><x-stat-tile label="Repaired & closed" :value="$d['closed']" icon="check2-circle" tone="success" :href="route('reports.index', ['status' => 'CLOSED'])" /></div>
        <div class="col-6 col-lg-3"><x-stat-tile label="Not accepted" :value="$d['not_accepted']" icon="x-circle" tone="secondary" hint="invalid or duplicate" /></div>
    </div>
    <div class="card">
        <div class="card-header d-flex justify-content-between">My reports <a href="{{ route('reports.index') }}" class="small">All</a></div>
        <div class="list-group list-group-flush">
            @forelse ($d['recent'] as $r)
                <a href="{{ route('reports.show', $r) }}" class="list-group-item list-group-item-action d-flex justify-content-between flex-wrap gap-2">
                    <span><strong>{{ $r->report_no }}</strong> · {{ $r->category->name }} <x-severity-badge :severity="$r->severity" />
                        <span class="small text-muted d-block">{{ $r->road?->code }} km {{ km($r->chainage_m) }} · {{ d($r->created_at) }}</span></span>
                    <span><x-report-status :status="$r->status" /></span>
                </a>
            @empty
                <div class="list-group-item text-center text-muted py-5">You have not reported anything yet.
                    @can('report.create')<div class="mt-2"><a href="{{ route('reports.create') }}" class="btn btn-success"><i class="bi bi-camera"></i> Report road damage</a></div>@endcan</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
