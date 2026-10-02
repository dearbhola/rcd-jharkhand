<x-app-layout :title="$contract->contract_no" :breadcrumbs="['Contracts' => route('contracts.index'), $contract->contract_no]">
    <x-page-header :title="$contract->contract_no" :subtitle="$contract->name">
        <x-test-badge :model="$contract" /> <x-status-badge :status="$contract->status" />
        @can('contract.update')<a href="{{ route('contracts.edit', $contract) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>@endcan
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-sm-3">Contractor</dt><dd class="col-sm-9"><a href="{{ route('contractors.show', $contract->contractor) }}">{{ $contract->contractor->name }}</a></dd>
                        <dt class="col-sm-3">Division</dt><dd class="col-sm-3">{{ $contract->division?->name ?? '—' }}</dd>
                        <dt class="col-sm-3">Value</dt><dd class="col-sm-3">{{ $contract->contract_value ? '₹ '.number_format((float) $contract->contract_value, 2) : '—' }}</dd>
                        <dt class="col-sm-3">Agreement</dt><dd class="col-sm-3">{{ $contract->agreement_no ?? '—' }} {{ $contract->agreement_date ? '('.d($contract->agreement_date).')' : '' }}</dd>
                        <dt class="col-sm-3">Work order</dt><dd class="col-sm-3">{{ d($contract->work_order_date) }}</dd>
                        <dt class="col-sm-3">Contract period</dt><dd class="col-sm-9">{{ d($contract->start_date) }} → {{ d($contract->end_date) }}</dd>
                        @if ($contract->remarks)<dt class="col-sm-3">Remarks</dt><dd class="col-sm-9">{{ $contract->remarks }}</dd>@endif
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            @php($active = $contract->isMaintenanceActiveOn(now()))
            <div @class(['card h-100 border-2', 'border-success' => $active, 'border-secondary' => ! $active])>
                <div class="card-body">
                    <div class="small text-muted">Maintenance period</div>
                    <div class="fs-5 fw-semibold">{{ d($contract->maintenance_start_date) }} → {{ d($contract->maintenance_end_date) }}</div>
                    @if ($active)
                        <span class="badge text-bg-success mt-2">Responsible for repairs today</span>
                        <div class="small text-muted mt-1">{{ (int) now()->diffInDays($contract->maintenance_end_date) }} days remaining</div>
                    @else
                        <span class="badge text-bg-secondary mt-2">Not responsible today — department workflow</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Road coverage <span class="small text-muted fw-normal">— one contractor per chainage at any time; ended rows are kept as history</span></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Road</th><th>Section</th><th class="text-end">km</th><th>Effective</th><th>Remarks</th><th></th></tr></thead>
                <tbody>
                @forelse ($mappings as $m)
                    @php($ended = $m->effective_to && $m->effective_to->isPast())
                    <tr @class(['text-muted' => $ended])>
                        <td><a href="{{ route('roads.show', $m->road_id) }}">{{ $m->road->code }}</a></td>
                        <td>{{ $m->section?->code ?? 'Whole road / range' }}</td>
                        <td class="text-end text-nowrap">{{ km($m->start_chainage_m) }} – {{ km($m->end_chainage_m) }}</td>
                        <td class="text-nowrap small">{{ d($m->effective_from) }} → {{ $m->effective_to ? d($m->effective_to) : 'open' }}</td>
                        <td class="small">{{ $m->remarks }}</td>
                        <td class="text-end">
                            @can('contract.map')
                                @unless ($ended)
                                    <button class="btn btn-sm btn-link" data-bs-toggle="modal" data-bs-target="#end{{ $m->id }}">End</button>
                                @endunless
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">Not mapped to any road yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @can('contract.map')
            <form method="POST" action="{{ route('contracts.mappings.store', $contract) }}" class="card-footer bg-light row g-2 mx-0" novalidate>
                @csrf
                <div class="col-12 small fw-semibold">Add coverage</div>
                <x-form.select name="road_id" label="Road" :options="$roads" required col="col-md-4" />
                <div class="col-md-3">
                    <label class="form-label" for="f_road_section_id">Section</label>
                    <select id="f_road_section_id" name="road_section_id" class="form-select" data-sections-for="#f_road_id" data-url="{{ url('roads/:id/sections.json') }}" data-selected="{{ old('road_section_id') }}"></select>
                </div>
                <x-form.input name="start_km" label="From km" type="number" step="0.001" col="col-md-1" error-key="start_chainage_m" />
                <x-form.input name="end_km" label="To km" type="number" step="0.001" col="col-md-1" error-key="end_chainage_m" />
                <x-form.input name="effective_from" label="Effective from" type="date" :value="$contract->start_date?->toDateString() ?? $contract->maintenance_start_date?->toDateString()" required col="col-md-3" />
                <x-form.input name="effective_to" label="Effective to" type="date" col="col-md-3" help="Leave open while the contract covers the road." />
                <x-form.input name="remarks" label="Remarks" col="col-md-6" />
                <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus"></i> Add coverage</button></div>
                <div class="col-12 form-text mt-0">Leave km empty to cover the whole section (or whole road). Enter km to cover part of it.</div>
            </form>
        @endcan
    </div>

    @can('contract.map')
        @foreach ($mappings as $m)
            <div class="modal fade" id="end{{ $m->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('contracts.mappings.end', $m) }}" class="modal-content">
                        @csrf @method('PUT')
                        <div class="modal-header"><h5 class="modal-title">End coverage of {{ $m->road->code }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <div class="modal-body row g-2">
                            <div class="col-5"><label class="form-label required small">Last day of coverage</label><input type="date" name="effective_to" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required></div>
                            <div class="col-7"><label class="form-label required small">Reason</label><input name="reason" class="form-control form-control-sm" required minlength="5"></div>
                        </div>
                        <div class="modal-footer"><button class="btn btn-warning btn-sm">End coverage</button></div>
                    </form>
                </div>
            </div>
        @endforeach
    @endcan

    <div class="card">
        <div class="card-header">Documents</div>
        <ul class="list-group list-group-flush small">
            @forelse ($contract->documents as $doc)
                <li class="list-group-item d-flex justify-content-between">
                    <a href="{{ route('contracts.documents.download', $doc) }}"><i class="bi bi-file-earmark me-1"></i>{{ $doc->title }}</a>
                    <span class="text-muted">{{ number_format($doc->file_size / 1024) }} KB · {{ d($doc->created_at) }}</span>
                </li>
            @empty
                <li class="list-group-item text-muted">No documents.</li>
            @endforelse
        </ul>
        @can('contract.update')
            <form method="POST" action="{{ route('contracts.documents.store', $contract) }}" enctype="multipart/form-data" class="card-footer bg-light row g-2 mx-0">
                @csrf
                <div class="col-md-5"><input name="title" class="form-control form-control-sm @error('title') is-invalid @enderror" placeholder="Document title (e.g. Agreement)" required></div>
                <div class="col-md-5"><input type="file" name="file" class="form-control form-control-sm @error('file') is-invalid @enderror" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"></div>
                <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-upload"></i> Upload</button></div>
            </form>
        @endcan
    </div>
</x-app-layout>
