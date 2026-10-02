@foreach (['success' => 'check-circle', 'warning' => 'exclamation-triangle', 'danger' => 'x-octagon', 'info' => 'info-circle'] as $type => $icon)
    @if (session($type))
        <div class="alert alert-{{ $type }} alert-dismissible fade show d-flex gap-2" role="alert">
            <i class="bi bi-{{ $icon }}"></i>
            <div>{{ session($type) }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach
@if ($errors->any() && ! ($hideErrorSummary ?? false))
    <div class="alert alert-danger" role="alert">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-octagon me-1"></i>Please correct the following:</div>
        <ul class="mb-0 small">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
