@props(['status'])
@php($map = ['active' => 'success', 'suspended' => 'warning', 'inactive' => 'secondary', 'blacklisted' => 'danger'])
<span class="badge text-bg-{{ $map[$status] ?? 'secondary' }} text-capitalize">{{ str_replace('_', ' ', $status) }}</span>
