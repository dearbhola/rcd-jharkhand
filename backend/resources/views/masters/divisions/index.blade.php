<x-app-layout title="Divisions" :breadcrumbs="['Masters', 'Divisions']">
    <x-page-header title="Divisions & sub-divisions">
        @can('division.manage')
            <a href="{{ route('divisions.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New division</a>
        @endcan
    </x-page-header>

    @forelse ($divisions as $division)
        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <span class="fw-semibold">{{ $division->name }}</span> <code class="ms-1">{{ $division->code }}</code>
                    <x-test-badge :model="$division" />
                    @unless ($division->is_active)<span class="badge text-bg-secondary">Inactive</span>@endunless
                    <div class="small text-muted fw-normal">{{ $division->district?->name }} · {{ $division->roads_count }} roads</div>
                </div>
                @can('division.manage')
                    <div class="d-flex gap-2">
                        <a href="{{ route('sub-divisions.create', $division) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus"></i> Sub-division</a>
                        <a href="{{ route('divisions.edit', $division) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>
                    </div>
                @endcan
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Sub-division</th><th>Code</th><th class="text-end">Road sections</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($division->subDivisions as $sub)
                        <tr>
                            <td>{{ $sub->name }} <x-test-badge :model="$sub" /></td>
                            <td><code>{{ $sub->code }}</code></td>
                            <td class="text-end">{{ $sub->road_sections_count }}</td>
                            <td>{!! $sub->is_active ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' !!}</td>
                            <td class="text-end">
                                @can('division.manage')<a href="{{ route('sub-divisions.edit', $sub) }}" class="btn btn-sm btn-link">Edit</a>@endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted text-center">No sub-divisions.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-muted text-center">No divisions yet.</div></div>
    @endforelse
</x-app-layout>
