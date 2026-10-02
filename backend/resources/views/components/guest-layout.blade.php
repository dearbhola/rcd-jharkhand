@props(['title' => null])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="auth-wrap">
    <div class="card auth-card">
        <div class="card-body p-4 p-sm-5">
            <div class="text-center mb-4">
                <div class="auth-logo mb-3"><i class="bi bi-signpost-2"></i></div>
                <h1 class="h5 mb-1">RCD Road &amp; Asset Monitoring</h1>
                @if ($title)
                    <div class="text-muted small">{{ $title }}</div>
                @endif
            </div>
            @include('partials.flash')
            {{ $slot }}
        </div>
    </div>
</div>
</body>
</html>
