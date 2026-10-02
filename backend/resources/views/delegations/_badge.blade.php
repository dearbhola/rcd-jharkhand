@php($colours = ['scheduled' => 'info', 'active' => 'success', 'ended' => 'secondary', 'cancelled' => 'secondary'])
<span class="badge text-bg-{{ $colours[$status] ?? 'secondary' }} text-capitalize">{{ $status }}</span>
