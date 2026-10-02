@props(['title' => null, 'breadcrumbs' => []])
@php($user = auth()->user())
@php($testMode = app(\App\Support\TestDataMode::class))
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body>
<div class="rcd-shell">
    <aside class="rcd-sidebar" id="rcdSidebar" aria-label="Main navigation">
        <a href="{{ route('dashboard') }}" class="brand">
            <span class="logo"><i class="bi bi-signpost-2"></i></span>
            <span class="lh-sm">RCD Road &amp; Asset<br><small class="fw-normal text-white-50">Monitoring System</small></span>
        </a>
        <nav class="pb-4">
            @foreach (\App\Support\Navigation::for($user) as $section)
                @if ($section['section'])
                    <div class="nav-section">{{ $section['section'] }}</div>
                @endif
                @foreach ($section['items'] as $item)
                    <a href="{{ route($item['route']) }}" @class(['nav-link', 'active' => \App\Support\Navigation::isActive($item['active'])])>
                        <i class="bi bi-{{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>
    </aside>

    <div class="rcd-main">
        @if ($testMode->includesTestData())
            <div class="test-data-banner px-3 py-1 text-center">
                <i class="bi bi-flask"></i> Test data is <strong>included</strong> in this view. Records marked
                <span class="badge badge-test">TEST</span> are not official.
            </div>
        @endif

        <header class="rcd-topbar d-flex align-items-center px-3 gap-2">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-rcd-toggle="sidebar" aria-label="Toggle menu">
                <i class="bi bi-list"></i>
            </button>

            <nav aria-label="breadcrumb" class="d-none d-md-block">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="bi bi-house"></i></a></li>
                    @foreach ($breadcrumbs as $label => $url)
                        @if (is_int($label))
                            <li class="breadcrumb-item active" aria-current="page">{{ $url }}</li>
                        @else
                            <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                        @endif
                    @endforeach
                </ol>
            </nav>

            <form method="GET" action="{{ route('search') }}" class="ms-md-3 d-none d-md-block" role="search">
                <input name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" class="form-control form-control-sm" style="width: 240px"
                       placeholder="Search reports, roads, RCD-005 14.2…" aria-label="Search">
            </form>

            <div class="ms-auto d-flex align-items-center gap-2">
                @can('testdata.include')
                    <form method="POST" action="{{ route('test-data.toggle') }}">
                        @csrf
                        <button class="btn btn-sm {{ $testMode->includesTestData() ? 'btn-outline-secondary' : 'btn-outline-primary' }}" title="Toggle test data">
                            <i class="bi bi-flask"></i>
                            <span class="d-none d-sm-inline">{{ $testMode->includesTestData() ? 'Hide' : 'Include' }} test data</span>
                        </button>
                    </form>
                @endcan

                @if (Route::has('notifications.index'))
                    <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-light position-relative" title="Notifications">
                        <i class="bi bi-bell"></i>
                        @php($unread = $user->unreadNotifications()->count())
                        @if ($unread)
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $unread > 99 ? '99+' : $unread }}</span>
                        @endif
                    </a>
                @endif

                <div class="dropdown">
                    <button class="btn btn-sm btn-light dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                        <span class="d-none d-sm-inline text-truncate" style="max-width: 160px">{{ $user->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">{{ $user->primaryRoleCode()?->label() }}</h6></li>
                        <li><a class="dropdown-item" href="{{ route('profile.show') }}"><i class="bi bi-person me-2"></i>My profile</a></li>
                        <li><a class="dropdown-item" href="{{ route('password.change') }}"><i class="bi bi-key me-2"></i>Change password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="rcd-content">
            @include('partials.flash')
            {{ $slot }}
        </main>
    </div>
</div>
{{ $scripts ?? '' }}
</body>
</html>
