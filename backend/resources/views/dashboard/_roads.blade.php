<div class="card h-100">
    <div class="card-header d-flex justify-content-between">My roads <span class="small text-muted">{{ $myRoads['section_count'] }} section(s)</span></div>
    <div class="list-group list-group-flush small">
        @forelse ($myRoads['roads']->take(8) as $row)
            <a href="{{ route('roads.show', $row['road']) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
                <span><strong>{{ $row['road']->code }}</strong> {{ $row['road']->name }}</span>
                <span class="text-muted text-nowrap">{{ $row['sections'] }} sec · {{ $row['km'] }} km</span>
            </a>
        @empty
            <div class="list-group-item text-muted">No road sections are mapped to you.</div>
        @endforelse
        @if ($myRoads['roads']->count() > 8)<div class="list-group-item text-muted">+ {{ $myRoads['roads']->count() - 8 }} more</div>@endif
    </div>
    <div class="card-footer bg-white"><a href="{{ route('map.index') }}" class="small"><i class="bi bi-map" aria-hidden="true"></i> Open map</a></div>
</div>
