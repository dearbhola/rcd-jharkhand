@props(['label', 'value', 'icon' => 'circle', 'tone' => 'primary', 'href' => null, 'hint' => null])
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if ($href) href="{{ $href }}" @endif class="card stat-card h-100 text-decoration-none {{ $href ? 'stat-link' : '' }}">
    <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 bg-{{ $tone }}-subtle text-{{ $tone }} p-3" aria-hidden="true"><i class="bi bi-{{ $icon }} fs-4"></i></div>
        <div>
            <div class="stat-value text-body">{{ is_numeric($value) ? number_format($value) : $value }}</div>
            <div class="stat-label">{{ $label }}</div>
            @if ($hint)<div class="small text-muted">{{ $hint }}</div>@endif
        </div>
    </div>
</{{ $tag }}>
