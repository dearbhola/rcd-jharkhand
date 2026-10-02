@props(['title', 'subtitle' => null])
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="page-title">{{ $title }}</h1>
        @if ($subtitle)
            <div class="text-muted small">{{ $subtitle }}</div>
        @endif
    </div>
    @if (trim($slot))
        <div class="d-flex flex-wrap gap-2">{{ $slot }}</div>
    @endif
</div>
