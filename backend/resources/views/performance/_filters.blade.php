<form method="GET" class="filter-bar row g-2 mb-3" data-autosubmit>
    @isset($contractors)
        @unless ($hideContractor ?? false)
            <div class="col-6 col-md-2">
                <select name="contractor_id" class="form-select form-select-sm" aria-label="Contractor">
                    <option value="">All contractors</option>
                    @foreach ($contractors as $id => $name)<option value="{{ $id }}" @selected(($input['contractor_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
        @endunless
    @endisset
    <div class="col-6 col-md-2">
        <select name="contract_id" class="form-select form-select-sm" aria-label="Contract">
            <option value="">All contracts</option>
            @foreach ($contracts as $id => $no)<option value="{{ $id }}" @selected(($input['contract_id'] ?? '') == $id)>{{ $no }}</option>@endforeach
        </select>
    </div>
    <div class="col-6 col-md-2">
        <select name="division_id" class="form-select form-select-sm" aria-label="Division">
            <option value="">All divisions</option>
            @foreach ($divisions as $id => $name)<option value="{{ $id }}" @selected(($input['division_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
        </select>
    </div>
    <div class="col-6 col-md-2">
        <select name="road_id" class="form-select form-select-sm" aria-label="Road">
            <option value="">All roads</option>
            @foreach ($roads as $id => $code)<option value="{{ $id }}" @selected(($input['road_id'] ?? '') == $id)>{{ $code }}</option>@endforeach
        </select>
    </div>
    <div class="col-6 col-md-2">
        <select name="fy" class="form-select form-select-sm" aria-label="Financial year">
            <option value="">All years</option>
            @foreach ($years as $fy)<option value="{{ $fy }}" @selected(($input['fy'] ?? '') === $fy)>FY {{ $fy }}</option>@endforeach
        </select>
    </div>
    <div class="col-6 col-md-2">
        <select name="period" class="form-select form-select-sm" aria-label="Maintenance period">
            <option value="">Any period</option>
            <option value="maintenance" @selected(($input['period'] ?? '') === 'maintenance')>Contract's maintenance period</option>
        </select>
    </div>
    <div class="col-md-12 d-flex gap-2 flex-wrap">
        <input type="date" name="from" value="{{ $input['from'] ?? '' }}" class="form-control form-control-sm" style="max-width: 170px" title="Reported from" aria-label="From">
        <input type="date" name="to" value="{{ $input['to'] ?? '' }}" class="form-control form-control-sm" style="max-width: 170px" title="Reported to" aria-label="To">
        <button class="btn btn-sm btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i> Apply</button>
        <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        <span class="small text-muted align-self-center">Period: {{ $scope->periodLabel ?? 'all time' }} (by date reported)</span>
    </div>
</form>
