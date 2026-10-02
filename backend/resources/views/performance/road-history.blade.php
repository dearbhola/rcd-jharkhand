<x-app-layout :title="'History · '.$road->code" :breadcrumbs="['Roads' => route('roads.index'), $road->code => route('roads.show', $road), 'Condition history']">
    <x-page-header :title="$road->code.' · condition history'" :subtitle="$road->name" />
    <form method="GET" class="filter-bar row g-2 mb-3" data-autosubmit>
        <div class="col-6 col-md-2">
            <select name="year" class="form-select form-select-sm" aria-label="Year"><option value="">All years</option>
                @foreach ($by_year as $y)<option value="{{ $y->y }}" @selected(($filters['year'] ?? '') == $y->y)>{{ $y->y }}</option>@endforeach</select>
        </div>
        <div class="col-6 col-md-2">
            <select name="road_section_id" class="form-select form-select-sm" aria-label="Section"><option value="">All sections</option>
                @foreach ($sections as $id => $code)<option value="{{ $id }}" @selected(($filters['road_section_id'] ?? '') == $id)>{{ $code }}</option>@endforeach</select>
        </div>
        <div class="col-6 col-md-3">
            <select name="issue_category_id" class="form-select form-select-sm" aria-label="Category"><option value="">All damage types</option>
                @foreach ($categories as $id => $name)<option value="{{ $id }}" @selected(($filters['issue_category_id'] ?? '') == $id)>{{ $name }}</option>@endforeach</select>
        </div>
        <div class="col-6 col-md-2">
            <select name="severity_id" class="form-select form-select-sm" aria-label="Severity"><option value="">All severities</option>
                @foreach ($severities as $id => $name)<option value="{{ $id }}" @selected(($filters['severity_id'] ?? '') == $id)>{{ $name }}</option>@endforeach</select>
        </div>
        <div class="col-md-3">
            <select name="contractor_id" class="form-select form-select-sm" aria-label="Contractor"><option value="">All contractors</option>
                @foreach ($contractors as $id => $name)<option value="{{ $id }}" @selected(($filters['contractor_id'] ?? '') == $id)>{{ $name }}</option>@endforeach</select>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat-tile label="Reports" :value="$totals['reported']" icon="exclamation-triangle" /></div>
        <div class="col-6 col-lg-3"><x-stat-tile label="Repaired & closed" :value="$totals['closed']" icon="check2-circle" tone="success" /></div>
        <div class="col-6 col-lg-3"><x-stat-tile label="Open" :value="$totals['open']" icon="hourglass-split" tone="warning" /></div>
        <div class="col-6 col-lg-3"><x-stat-tile label="Repair attempts" :value="$totals['repairs']" icon="tools" tone="secondary" /></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card h-100"><div class="card-header">By year</div>
                <table class="table table-sm small mb-0"><thead><tr><th>Year</th><th class="text-end">Reported</th><th class="text-end">Closed</th></tr></thead>
                    <tbody>@forelse ($by_year as $y)<tr><td><a href="?year={{ $y->y }}">{{ $y->y }}</a></td><td class="text-end">{{ $y->reported }}</td><td class="text-end">{{ $y->closed }}</td></tr>@empty<tr><td colspan="3" class="text-muted">No reports.</td></tr>@endforelse</tbody></table>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card h-100"><div class="card-header">Recurring problem areas <span class="small text-muted fw-normal">({{ $hotspot_bin_m }} m bands with repeated reports)</span></div>
                <table class="table table-sm small mb-0"><thead><tr><th>Chainage</th><th class="text-end">Reports</th><th>Damage types</th><th>Last reported</th><th></th></tr></thead>
                    <tbody>@forelse ($hotspots as $h)
                        <tr><td class="text-nowrap">km {{ km($h['from_m']) }} – {{ km($h['to_m']) }}</td><td class="text-end fw-semibold">{{ $h['count'] }}</td><td>{{ $h['categories'] }}</td>
                            <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($h['last_at'])->format('d-M-Y') }}</td>
                            <td><a href="{{ route('reports.index', ['road_id' => $road->id, 'km_from' => $h['from_m'] / 1000, 'km_to' => $h['to_m'] / 1000]) }}">Reports</a></td></tr>
                    @empty<tr><td colspan="5" class="text-muted">No recurring problem areas for these filters.</td></tr>@endforelse</tbody></table>
            </div>
        </div>
    </div>

    <div class="card mb-3"><div class="card-header">Reports</div>
        <div class="table-responsive"><table class="table table-sm small mb-0 align-middle">
            <thead><tr><th>Report</th><th>Section / km</th><th>Damage</th><th>Severity</th><th>Contractor</th><th>Reported</th><th>Status</th></tr></thead>
            <tbody>@forelse ($reports as $r)<tr><td><a href="{{ route('reports.show', $r) }}">{{ $r->report_no }}</a> <x-test-badge :model="$r" /></td><td>{{ $r->section?->code }} · {{ km($r->chainage_m) }}</td>
                <td>{{ $r->category->name }}</td><td><x-severity-badge :severity="$r->severity" /></td><td>{{ $r->currentResponsibility?->contractor?->name ?? 'Department' }}</td>
                <td class="text-nowrap">{{ d($r->created_at) }}</td><td><x-report-status :status="$r->status" /></td></tr>@empty<tr><td colspan="7" class="text-muted text-center">No reports.</td></tr>@endforelse</tbody>
        </table></div>
        @if ($reports->hasPages())<div class="card-footer bg-white">{{ $reports->links() }}</div>@endif
    </div>

    <div class="row g-3">
        <div class="col-lg-6"><div class="card h-100"><div class="card-header">Repair history</div>
            <div class="table-responsive" style="max-height: 360px"><table class="table table-sm small mb-0">
                <thead><tr><th>Submitted</th><th>Report</th><th>#</th><th>Contractor</th><th>Outcome</th></tr></thead>
                <tbody>@forelse ($repairs as $a)<tr><td class="text-nowrap">{{ d($a->submitted_at) }}</td><td><a href="{{ route('reports.show', $a->report_id) }}">{{ $a->report?->report_no }}</a></td>
                    <td>{{ $a->attempt_no }}</td><td>{{ $a->contractor?->name }}</td><td class="{{ $a->outcome === 'rejected' ? 'text-danger' : '' }}">{{ str_replace('_', ' ', $a->outcome) }}{{ $a->rejected_stage ? ' ('.$a->rejected_stage.')' : '' }}</td></tr>@empty<tr><td colspan="5" class="text-muted">None.</td></tr>@endforelse</tbody>
            </table></div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-header">Inspection history</div>
            <div class="table-responsive" style="max-height: 360px"><table class="table table-sm small mb-0">
                <thead><tr><th>Date</th><th>Report</th><th>Stage</th><th>Inspector</th><th>Decision</th></tr></thead>
                <tbody>@forelse ($inspections as $i)<tr><td class="text-nowrap">{{ d($i->inspected_at) }}</td><td><a href="{{ route('reports.show', $i->report_id) }}">{{ $i->report?->report_no }}</a></td>
                    <td>{{ str_replace('_', ' ', $i->stage) }}</td><td>{{ $i->inspector?->name }}</td><td class="{{ $i->decision === 'rejected' ? 'text-danger' : '' }}">{{ $i->decision }}</td></tr>@empty<tr><td colspan="5" class="text-muted">None.</td></tr>@endforelse</tbody>
            </table></div></div></div>
    </div>
</x-app-layout>
