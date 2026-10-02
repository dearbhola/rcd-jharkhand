<x-page-header :title="'Welcome, '.$user->name" :subtitle="$role->label().' dashboard · updated '.$d['generated_at']->format('H:i')">
    @if (count($available) > 1)
        <div class="btn-group btn-group-sm" role="group" aria-label="Switch dashboard">
            @foreach ($available as $r)
                <a href="{{ route('dashboard', ['as' => $r->value]) }}" class="btn {{ $r === $role ? 'btn-primary' : 'btn-outline-primary' }}">{{ $r->value === 'SUPER_ADMIN' ? 'Admin' : str_replace('_', ' ', ucfirst(strtolower($r->value))) }}</a>
            @endforeach
        </div>
    @endif
    @can('report.create')
        <a href="{{ route('reports.create') }}" class="btn btn-success btn-sm"><i class="bi bi-camera me-1"></i>Report damage</a>
    @endcan
</x-page-header>
