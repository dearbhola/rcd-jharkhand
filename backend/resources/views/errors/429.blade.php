<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>429 · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="d-grid align-items-center" style="min-height:100vh">
<div class="text-center p-4">
    <div class="display-4 fw-bold text-primary">429</div>
    <p class="lead">Too many requests. Please wait a moment and try again.</p>
    <a href="{{ url('/') }}" class="btn btn-primary"><i class="bi bi-house me-1"></i>Go to dashboard</a>
</div>
</body>
</html>
