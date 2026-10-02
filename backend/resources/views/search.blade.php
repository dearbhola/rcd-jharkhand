<x-app-layout title="Search" :breadcrumbs="['Search']">
    <x-page-header title="Search" subtitle="Report numbers, road codes or names, asset codes, contractors, contract numbers, users — or a road and km, e.g. “RCD-005 14.2”." />
    <form method="GET" action="{{ route('search') }}" class="mb-3">
        <div class="input-group">
            <input name="q" value="{{ $q }}" class="form-control form-control-lg" placeholder="Search…" autofocus aria-label="Search">
            <button class="btn btn-primary px-4"><i class="bi bi-search" aria-hidden="true"></i> Search</button>
        </div>
    </form>

    @if ($chainageHit)
        <a href="{{ $chainageHit['url'] }}" class="card card-body mb-3 text-decoration-none border-primary">
            <span><i class="bi bi-signpost-2 me-1" aria-hidden="true"></i> Reports on <strong>{{ $chainageHit['road']->code }}</strong> near km <strong>{{ number_format($chainageHit['km'], 3) }}</strong> (±0.5 km)</span>
        </a>
    @endif

    @if (mb_strlen($q) >= 2 && ! $groups && ! $chainageHit)
        <div class="card card-body text-muted text-center py-5">Nothing found for “{{ $q }}”.</div>
    @endif

    <div class="row g-3">
        @foreach ($groups as $group => $rows)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">{{ $group }} <span class="badge text-bg-secondary">{{ $rows->count() }}</span></div>
                    <div class="list-group list-group-flush">
                        @foreach ($rows as [$url, $title, $subtitle, $model])
                            <a href="{{ $url }}" class="list-group-item list-group-item-action">
                                <span class="fw-semibold">{{ $title }}</span> <x-test-badge :model="$model" />
                                <div class="small text-muted">{{ $subtitle }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
