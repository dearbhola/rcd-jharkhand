<x-app-layout :title="$section->road->code.' / '.$section->code"
              :breadcrumbs="['Roads' => route('roads.index'), $section->road->code => route('roads.show', $section->road), $section->code]">
    <x-page-header :title="$section->road->code.' / '.$section->code" :subtitle="'km '.km($section->start_chainage_m).' – '.km($section->end_chainage_m).' · '.$section->division->name.($section->subDivision ? ' / '.$section->subDivision->name : '')">
        <x-test-badge :model="$section" />
        @can('responsibility.manage')
            <a href="{{ route('responsibility.create', ['road_id' => $section->road_id, 'sections[]' => $section->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-gear me-1"></i>Change JE/AE/EE</a>
        @endcan
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">Responsible today</div>
                <ul class="list-group list-group-flush">
                    @foreach ($people as $role => $user)
                        <li class="list-group-item d-flex justify-content-between"><span class="fw-semibold">{{ $role }}</span><span>{{ $user?->name ?? '— not mapped —' }}</span></li>
                    @endforeach
                </ul>
            </div>
            <div class="card">
                <div class="card-header">Contracts covering this section</div>
                <ul class="list-group list-group-flush small">
                    @forelse ($contracts as $m)
                        <li class="list-group-item">
                            <a href="{{ route('contracts.show', $m->contract_id) }}">{{ $m->contract->contract_no }}</a> · {{ $m->contract->contractor->name }}
                            <div class="text-muted">km {{ km($m->start_chainage_m) }}–{{ km($m->end_chainage_m) }} · {{ d($m->effective_from) }} → {{ $m->effective_to ? d($m->effective_to) : 'open' }}</div>
                            <div class="text-muted">Maintenance {{ d($m->contract->maintenance_start_date) }} → {{ d($m->contract->maintenance_end_date) }}</div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">None — department workflow applies.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">Responsibility history</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Role</th><th>Officer</th><th>From</th><th>To</th><th>Remarks</th><th>Recorded by</th></tr></thead>
                        <tbody>
                        @forelse ($history as $row)
                            <tr @class(['text-muted' => $row->effective_to !== null])>
                                <td>{{ $row->role_code->value }}</td>
                                <td>{{ $row->user->name }}</td>
                                <td class="text-nowrap">{{ d($row->effective_from) }}</td>
                                <td class="text-nowrap">{{ $row->effective_to ? d($row->effective_to) : 'current' }}</td>
                                <td class="small">{{ $row->remarks }}</td>
                                <td class="small">{{ $row->creator?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted text-center">No JE/AE/EE mapped yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
