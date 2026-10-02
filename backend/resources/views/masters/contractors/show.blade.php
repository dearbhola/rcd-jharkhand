<x-app-layout :title="$contractor->name" :breadcrumbs="['Contractors' => route('contractors.index'), $contractor->name]">
    <x-page-header :title="$contractor->name" :subtitle="$contractor->code">
        <x-test-badge :model="$contractor" /> <x-status-badge :status="$contractor->status" />
        @can('contract.create')<a href="{{ route('contracts.create', ['contractor_id' => $contractor->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus me-1"></i>New contract</a>@endcan
        @can('contractor.update')<a href="{{ route('contractors.edit', $contractor) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>@endcan
    </x-page-header>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5">Registration</dt><dd class="col-7">{{ $contractor->registration_no ?? '—' }}</dd>
                        <dt class="col-5">PAN</dt><dd class="col-7">{{ $contractor->pan ?? '—' }}</dd>
                        <dt class="col-5">GSTIN</dt><dd class="col-7">{{ $contractor->gstin ?? '—' }}</dd>
                        <dt class="col-5">Contact</dt><dd class="col-7">{{ $contractor->contact_person ?? '—' }}</dd>
                        <dt class="col-5">Mobile</dt><dd class="col-7">{{ $contractor->mobile ?? '—' }}</dd>
                        <dt class="col-5">Email</dt><dd class="col-7">{{ $contractor->email ?? '—' }}</dd>
                        <dt class="col-5">Address</dt><dd class="col-7">{{ $contractor->address ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
            <div class="card">
                <div class="card-header">User accounts</div>
                <ul class="list-group list-group-flush small">
                    @forelse ($contractor->users as $user)
                        <li class="list-group-item d-flex justify-content-between">
                            @can('user.view')<a href="{{ route('admin.users.show', $user) }}">{{ $user->name }}</a>@else {{ $user->name }} @endcan
                            <x-status-badge :status="$user->status" />
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No user accounts. Create one under Users with the Contractor role.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">Contracts</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>Contract</th><th>Maintenance period</th><th>Status</th><th class="text-end">Value (₹)</th></tr></thead>
                        <tbody>
                        @forelse ($contractor->contracts as $contract)
                            <tr>
                                <td><a href="{{ route('contracts.show', $contract) }}">{{ $contract->contract_no }}</a><div class="small text-muted">{{ $contract->name }}</div></td>
                                <td class="small text-nowrap">{{ d($contract->maintenance_start_date) }} → {{ d($contract->maintenance_end_date) }}
                                    @if ($contract->isMaintenanceActiveOn(now()))<span class="badge text-bg-success">active</span>@endif</td>
                                <td><x-status-badge :status="$contract->status" /></td>
                                <td class="text-end">{{ $contract->contract_value ? number_format((float) $contract->contract_value, 2) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted text-center">No contracts.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
