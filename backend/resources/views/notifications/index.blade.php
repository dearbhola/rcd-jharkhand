@php($icons = ['task.assigned' => 'inbox', 'task.reassigned' => 'arrow-left-right', 'report.status' => 'info-circle', 'sla.approaching' => 'alarm', 'sla.breached' => 'exclamation-triangle', 'sla.escalated' => 'exclamation-octagon', 'repair.approved' => 'check2-circle', 'delegation.started' => 'people', 'delegation.ended' => 'people'])
<x-app-layout title="Notifications" :breadcrumbs="['Notifications']">
    <x-page-header title="Notifications" :subtitle="$unreadCount.' unread'">
        @if ($unreadCount)
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2-all"></i> Mark all as read</button></form>
        @endif
    </x-page-header>
    <ul class="nav nav-pills mb-3">
        @foreach (['unread' => 'Unread', 'read' => 'Read', 'all' => 'All'] as $key => $label)
            <li class="nav-item"><a class="nav-link {{ $filter === $key ? 'active' : '' }}" href="?filter={{ $key }}">{{ $label }}{{ $key === 'unread' && $unreadCount ? ' ('.$unreadCount.')' : '' }}</a></li>
        @endforeach
    </ul>
    <div class="card">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $n)
                @php($event = $n->data['event'] ?? '')
                <a href="{{ route('notifications.open', $n->id) }}" @class(['list-group-item list-group-item-action d-flex gap-3', 'fw-semibold' => ! $n->read_at])>
                    <i @class(['bi fs-5 bi-'.($icons[$event] ?? 'bell'), 'text-danger' => str_starts_with($event, 'sla.') && $event !== 'sla.approaching', 'text-warning' => $event === 'sla.approaching', 'text-primary' => ! str_starts_with($event, 'sla.')])></i>
                    <div class="flex-grow-1">
                        <div>{{ $n->data['message'] ?? '' }}</div>
                        <div class="small text-muted fw-normal">{{ $n->created_at->diffForHumans() }} · {{ d($n->created_at, true) }}</div>
                    </div>
                    @unless ($n->read_at)<span class="badge text-bg-primary align-self-center">new</span>@endunless
                </a>
            @empty
                <div class="list-group-item text-center text-muted py-5"><i class="bi bi-bell-slash fs-2 d-block mb-2"></i>No {{ $filter === 'all' ? '' : $filter }} notifications.</div>
            @endforelse
        </div>
        @if ($notifications->hasPages())<div class="card-footer bg-white">{{ $notifications->links() }}</div>@endif
    </div>
</x-app-layout>
