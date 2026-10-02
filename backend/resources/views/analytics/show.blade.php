<x-app-layout :title="$table->title" :breadcrumbs="['Reports & exports' => route('analytics.index'), $table->title]">
    <x-page-header :title="$table->title"><x-export-bar /></x-page-header>
    <form method="GET" class="filter-bar row g-2 mb-3">
        <div class="col-md-3">
            <select name="type" class="form-select form-select-sm" aria-label="Report" onchange="location.href='{{ url('analytics') }}/' + this.value + location.search">
                @foreach ($types as $key => [$label])<option value="{{ $key }}" @selected($key === $type)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm" aria-label="From" title="Reported from"></div>
        <div class="col-6 col-md-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm" aria-label="To" title="Reported to"></div>
        <div class="col-md-3">
            <select name="division_id" class="form-select form-select-sm" aria-label="Division"><option value="">All divisions</option>
                @foreach ($divisions as $id => $name)<option value="{{ $id }}" @selected(($filters['division_id'] ?? '') == $id)>{{ $name }}</option>@endforeach</select>
        </div>
        <div class="col-md-2 d-flex gap-1"><button class="btn btn-sm btn-primary flex-fill">Apply</button><a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary">Reset</a></div>
    </form>
    <div class="card">
        <div class="card-header d-flex justify-content-between"><span>{{ count($table->rows) }} row(s)</span>
            @if ($table->includesTestData)<span class="badge badge-test">includes TEST data</span>@endif</div>
        <x-data-table :table="$table" :numeric="['reported', 'open', 'closed', 'invalid', 'overdue', 'rejections', 'avg_close_hours', 'timers', 'completed', 'on_time', 'breached', 'avg_hours', 'attempts']" />
        @if ($table->notes)<div class="card-footer bg-white small text-muted">{{ implode(' ', $table->notes) }}</div>@endif
    </div>
</x-app-layout>
