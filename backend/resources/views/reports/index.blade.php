<x-app-layout title="Reports" :breadcrumbs="['Reports']">
    <x-page-header title="Damage reports">
        @can('report.create')<a href="{{ route('reports.create') }}" class="btn btn-success btn-sm"><i class="bi bi-camera me-1"></i>Report damage</a>@endcan
    </x-page-header>

    <form method="GET" class="filter-bar row g-2 mb-3" data-autosubmit>
        <div class="col-md-3"><input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Report no., road code or name"></div>
        <div class="col-6 col-md-2">
            <select name="status" class="form-select form-select-sm" aria-label="Status">
                <option value="">Any status</option>
                @foreach (\App\Domain\Reporting\ReportStatus::labels() as $code => [$label])<option value="{{ $code }}" @selected(($filters['status'] ?? '') === $code)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="severity_id" class="form-select form-select-sm" aria-label="Severity">
                <option value="">Any severity</option>
                @foreach ($severities as $id => $name)<option value="{{ $id }}" @selected(($filters['severity_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="asset_type_id" class="form-select form-select-sm" aria-label="Asset type">
                <option value="">Any asset</option>
                @foreach ($assetTypes as $id => $name)<option value="{{ $id }}" @selected(($filters['asset_type_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select name="flag" class="form-select form-select-sm" aria-label="Flag">
                <option value="">Any flags</option>
                @foreach (['possible_duplicate' => 'Possible duplicate', 'responsibility_gap' => 'Missing JE/AE/EE mapping', 'delayed_submission' => 'Delayed submission', 'location_override' => 'Test location override'] as $v => $l)
                    <option value="{{ $v }}" @selected(($filters['flag'] ?? '') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select name="division_id" class="form-select form-select-sm" aria-label="Division">
                <option value="">All divisions</option>
                @foreach ($divisions as $id => $name)<option value="{{ $id }}" @selected(($filters['division_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="road_id" class="form-select form-select-sm" aria-label="Road">
                <option value="">All roads</option>
                @foreach ($roads as $id => $code)<option value="{{ $id }}" @selected(($filters['road_id'] ?? '') == $id)>{{ $code }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2 d-flex gap-1">
            <input name="km_from" value="{{ $filters['km_from'] ?? '' }}" type="number" step="0.001" min="0" class="form-control form-control-sm" placeholder="km from" aria-label="Chainage from (km)">
            <input name="km_to" value="{{ $filters['km_to'] ?? '' }}" type="number" step="0.001" min="0" class="form-control form-control-sm" placeholder="km to" aria-label="Chainage to (km)">
        </div>
        @if ($contractors->isNotEmpty())
            <div class="col-6 col-md-3">
                <select name="contractor_id" class="form-select form-select-sm" aria-label="Contractor">
                    <option value="">Any contractor</option>
                    @foreach ($contractors as $id => $name)<option value="{{ $id }}" @selected(($filters['contractor_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2"><input name="contract" value="{{ $filters['contract'] ?? '' }}" class="form-control form-control-sm" placeholder="Contract no."></div>
        @endif
        <div class="col-6 col-md-2"><input name="reporter" value="{{ $filters['reporter'] ?? '' }}" class="form-control form-control-sm" placeholder="Reporter name / mobile"></div>
        <div class="col-6 col-md-2"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm" title="From"></div>
        <div class="col-6 col-md-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm" title="To"></div>
        <div class="col-6 col-md-2 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-fill"><i class="bi bi-search"></i> Filter</button>
            <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Report</th><th>Location</th><th>Damage</th><th>Severity</th><th>Status</th><th>Reported</th></tr></thead>
                <tbody>
                @forelse ($reports as $r)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('reports.show', $r) }}" class="fw-semibold">{{ $r->report_no }}</a> <x-test-badge :model="$r" />
                            @if (in_array('possible_duplicate', $r->location_flags ?? [], true))<i class="bi bi-files text-warning" title="Possible duplicate"></i>@endif</td>
                        <td class="small">{{ $r->road?->code }} / {{ $r->section?->code }}<div class="text-muted">km {{ km($r->chainage_m) }}</div></td>
                        <td class="small">{{ $r->category->name }}<div class="text-muted">{{ $r->assetType->name }}</div></td>
                        <td><x-severity-badge :severity="$r->severity" /></td>
                        <td><x-report-status :status="$r->status" /></td>
                        <td class="small text-nowrap">{{ d($r->created_at, true) }}<div class="text-muted">{{ $r->reporter->name }}</div></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No reports to show.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($reports->hasPages())<div class="card-footer bg-white">{{ $reports->links() }}</div>@endif
    </div>
</x-app-layout>
