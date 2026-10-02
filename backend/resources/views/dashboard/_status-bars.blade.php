{{-- Open reports by stage: one series → single hue, value labelled on every bar, no legend needed. --}}
@php($max = max(1, max($byStatus)))
<div class="card h-100">
    <div class="card-header">{{ $title ?? 'Open reports by stage' }}</div>
    <div class="card-body">
        @foreach ($byStatus as $status => $n)
            @php([$label] = \App\Domain\Reporting\ReportStatus::labels()[$status])
            <a href="{{ route('reports.index', ['status' => $status]) }}" class="d-flex align-items-center gap-2 mb-2 text-decoration-none text-body status-bar-row" title="{{ $label }}: {{ $n }}">
                <span class="small text-secondary text-truncate" style="width: 42%">{{ $label }}</span>
                <span class="flex-grow-1 bg-body-tertiary rounded" style="height: 14px">
                    <span class="d-block h-100 rounded-end" style="width: {{ $n ? max(2, round($n / $max * 100)) : 0 }}%; background: var(--viz-series-1)"></span>
                </span>
                <span class="small fw-semibold text-end" style="width: 2.5rem">{{ $n }}</span>
            </a>
        @endforeach
    </div>
</div>
