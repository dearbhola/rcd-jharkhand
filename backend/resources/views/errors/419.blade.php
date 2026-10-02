<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>419 · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="d-grid align-items-center" style="min-height:100vh">
<div class="text-center p-4">
    <div class="display-4 fw-bold text-primary">419</div>
    <p class="lead">Your session has expired. Please go back and try again.</p>
    <a href="{{ url('/') }}" class="btn btn-primary"><i class="bi bi-house me-1"></i>Go to dashboard</a>
</div>
</body>
</html>
