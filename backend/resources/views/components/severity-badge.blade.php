@props(['severity'])
@if ($severity)
    <span class="badge" style="background: {{ $severity->color }}">{{ $severity->name }}</span>
@endif
