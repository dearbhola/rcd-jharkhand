@props(['cancel', 'label' => 'Save'])
<div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ $label }}</button>
    <a href="{{ $cancel }}" class="btn btn-outline-secondary">Cancel</a>
</div>
