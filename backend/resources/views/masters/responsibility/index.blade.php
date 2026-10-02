<x-app-layout title="Responsibility mapping" :breadcrumbs="['Masters', 'Responsibility']">
    <x-page-header title="JE / AE / EE responsibility" subtitle="Permanent road-section mapping as of {{ d(now()) }}. Leave/delegation does not change this mapping.">
        @can('responsibility.manage')<a href="{{ route('responsibility.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-person-gear me-1"></i>Assign officer</a>@endcan
    </x-page-header>

    <form method="GET" class="filter-bar row g-2 mb-3" data-autosubmit>
        <div class="col-6 col-md-3">
            <select name="division_id" id="f_division_id" class="form-select form-select-sm" aria-label="Division">
                <option value="">All divisions</option>
                @foreach ($divisions as $id => $name)<option value="{{ $id }}" @selected(($filters['division_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="sub_division_id" data-depends-on="#f_division_id" class="form-select form-select-sm" aria-label="Sub-division">
                <option value="">All sub-divisions</option>
                @foreach ($subDivisions as $sub)<option value="{{ $sub->id }}" data-parent="{{ $sub->division_id }}" @selected(($filters['sub_division_id'] ?? '') == $sub->id)>{{ $sub->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="road_id" class="form-select form-select-sm" aria-label="Road">
                <option value="">All roads</option>
                @foreach ($roads as $id => $code)<option value="{{ $id }}" @selected(($filters['road_id'] ?? '') == $id)>{{ $code }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2"><input name="officer" value="{{ $filters['officer'] ?? '' }}" class="form-control form-control-sm" placeholder="Officer name / code"></div>
        <div class="col-6 col-md-2">
            <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox" name="unmapped" value="1" id="unmapped" @checked($filters['unmapped'] ?? false) onchange="this.form.requestSubmit()">
                <label class="form-check-label small" for="unmapped">Incomplete mapping only</label>
            </div>
        </div>
        <div class="col-6 col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-fill" title="Filter"><i class="bi bi-search"></i></button>
            <a href="{{ route('responsibility.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Road</th><th>Section</th><th class="text-end">km</th><th>JE</th><th>AE</th><th>EE</th><th></th></tr></thead>
                <tbody>
                @forelse ($sections as $s)
                    @php($rows = $current[$s->id] ?? collect())
                    <tr>
                        <td><a href="{{ route('roads.show', $s->road_id) }}">{{ $s->road->code }}</a></td>
                        <td><a href="{{ route('road-sections.show', $s) }}">{{ $s->code }}</a></td>
                        <td class="text-end text-nowrap">{{ km($s->start_chainage_m) }} – {{ km($s->end_chainage_m) }}</td>
                        @foreach (['JE', 'AE', 'EE'] as $role)
                            @php($row = $rows->get($role))
                            <td class="small">
                                @if ($row)
                                    {{ $row->user->name }}<div class="text-muted">since {{ d($row->effective_from) }}</div>
                                @else
                                    <span class="badge text-bg-warning">not mapped</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="text-end text-nowrap">
                            <a href="{{ route('road-sections.show', $s) }}" class="btn btn-sm btn-link px-1">History</a>
                            @can('responsibility.manage')
                                <a href="{{ route('responsibility.create', ['road_id' => $s->road_id, 'sections[]' => $s->id]) }}" class="btn btn-sm btn-link px-1">Change</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No sections match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($sections->hasPages())<div class="card-footer bg-white">{{ $sections->links() }}</div>@endif
    </div>
</x-app-layout>
