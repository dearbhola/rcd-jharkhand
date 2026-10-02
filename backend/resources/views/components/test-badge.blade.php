@props(['model'])
@if ($model?->is_test)
    <span class="badge badge-test" title="Test record — excluded from official reports">TEST</span>
@endif
