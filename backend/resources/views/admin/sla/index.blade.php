@php($activeOptions = [1 => 'Active', 0 => 'Inactive'])
<x-app-layout title="SLA & escalation" :breadcrumbs="['Administration', 'SLA & escalation']">
    <x-page-header title="SLA & escalation rules" subtitle="The most specific active rule wins: category > asset type > severity > stage default. Running timers keep the hours they started with." />

    <div class="row g-3">
        <div class="col-12 order-2">
            @foreach ($stages as $stage => $label)
                <div class="card mb-3">
                    <div class="card-header">{{ $label }} <code class="small">{{ $stage }}</code></div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Applies to</th><th style="width:120px">Hours</th><th style="width:130px">Status</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($rules[$stage] ?? [] as $rule)
                                @php($fid = 'sla'.$rule->id)
                                <tr @class(['text-muted' => ! $rule->is_active])>
                                    <td class="small">
                                        <form id="{{ $fid }}" method="POST" action="{{ route('admin.sla.rules.update', $rule) }}">@csrf @method('PUT')</form>
                                        @if (! $rule->asset_type_id && ! $rule->issue_category_id && ! $rule->severity_id)
                                            <strong>Default</strong>
                                        @else
                                            {{ collect([$rule->assetType?->name, $rule->issueCategory?->name])->filter()->join(' › ') }}
                                            @if ($rule->severity)<span class="badge" style="background:{{ $rule->severity->color }}">{{ $rule->severity->name }}</span>@endif
                                        @endif
                                    </td>
                                    <td><input form="{{ $fid }}" name="hours" type="number" step="0.25" min="0.25" value="{{ $rule->hours + 0 }}" class="form-control form-control-sm" aria-label="Hours"></td>
                                    <td>
                                        <select form="{{ $fid }}" name="is_active" class="form-select form-select-sm" aria-label="Status">
                                            @foreach ($activeOptions as $v => $l)<option value="{{ $v }}" @selected((int) $rule->is_active === $v)>{{ $l }}</option>@endforeach
                                        </select>
                                    </td>
                                    <td class="text-end"><button form="{{ $fid }}" class="btn btn-sm btn-link">Save</button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            <div class="card">
                <div class="card-header">Add a specific SLA rule</div>
                <form method="POST" action="{{ route('admin.sla.rules.store') }}" class="card-body row g-2" novalidate>
                    @csrf
                    <x-form.select name="stage" label="Stage" :options="$stages" required col="col-md-4" />
                    <x-form.select name="asset_type_id" label="Asset type" :options="$assetTypes" placeholder="Any" col="col-md-4" />
                    <div class="col-md-4">
                        <label class="form-label" for="f_issue_category_id">Category</label>
                        <select id="f_issue_category_id" name="issue_category_id" class="form-select @error('issue_category_id') is-invalid @enderror">
                            <option value="">Any</option>
                            @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->assetType->name }} › {{ $c->name }}</option>@endforeach
                        </select>
                        @error('issue_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <x-form.select name="severity_id" label="Severity" :options="$severities" placeholder="Any" col="col-md-4" />
                    <x-form.input name="hours" label="Hours" type="number" step="0.25" min="0.25" required col="col-md-4" />
                    <div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary w-100"><i class="bi bi-plus"></i> Add rule</button></div>
                </form>
            </div>
        </div>

        <div class="col-12 order-1">
            <div class="card">
                <div class="card-header">Escalation rules</div>
                <div class="card-body small text-muted border-bottom">Checked every 5 minutes. <em>Before due</em> sends a reminder ahead of the deadline;
                    <em>after due</em> records the breach and escalates. Recipients: the current task holders and/or the mapped JE/AE/EE (their stand-ins if on leave).</div>
                <div class="list-group list-group-flush">
                    @foreach ([...$escalations, new \App\Models\EscalationRule(['trigger' => 'after_due', 'offset_hours' => 0, 'level' => 1, 'action' => 'escalate', 'notify_assignee' => true, 'is_active' => true])] as $e)
                        <form method="POST" action="{{ $e->exists ? route('admin.sla.escalations.update', $e) : route('admin.sla.escalations.store') }}" @class(['list-group-item', 'bg-light' => ! $e->exists, 'text-muted' => $e->exists && ! $e->is_active])>
                            @csrf @if ($e->exists) @method('PUT') @endif
                            @unless ($e->exists)<div class="small fw-semibold mb-2">New escalation rule</div>@endunless
                            <div class="row g-2 align-items-center small">
                                <div class="col-sm-3 col-lg-2">
                                    <select name="trigger" class="form-select form-select-sm" aria-label="When">
                                        <option value="before_due" @selected($e->trigger === 'before_due')>Before due</option>
                                        <option value="after_due" @selected($e->trigger === 'after_due')>After due</option>
                                    </select>
                                </div>
                                <div class="col-sm-3">
                                    <div class="input-group input-group-sm">
                                        <input name="offset_hours" type="number" step="0.25" min="0" value="{{ $e->offset_hours + 0 }}" class="form-control" aria-label="Hours">
                                        <span class="input-group-text">h</span>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <select name="action" class="form-select form-select-sm" aria-label="Action">
                                        <option value="remind" @selected($e->action === 'remind')>Remind</option>
                                        <option value="escalate" @selected($e->action === 'escalate')>Escalate</option>
                                    </select>
                                </div>
                                <div class="col-sm-3">
                                    <select name="notify_role_code" class="form-select form-select-sm" aria-label="Escalate to">
                                        <option value="">No officer</option>
                                        @foreach (['JE', 'AE', 'EE'] as $r)<option value="{{ $r }}" @selected($e->notify_role_code === $r)>to {{ $r }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <select name="stage" class="form-select form-select-sm" aria-label="Stage">
                                        <option value="">All stages</option>
                                        @foreach ($stages as $code => $label)<option value="{{ $code }}" @selected($e->stage === $code)>{{ $label }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-sm-3">
                                    <select name="notify_assignee" class="form-select form-select-sm" aria-label="Also notify task holder">
                                        <option value="1" @selected($e->notify_assignee)>+ task holder</option>
                                        <option value="0" @selected(! $e->notify_assignee)>officer only</option>
                                    </select>
                                </div>
                                <div class="col-sm-2">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" title="Escalation level">L</span>
                                        <input name="level" type="number" min="0" max="9" value="{{ $e->level }}" class="form-control" aria-label="Level">
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <select name="is_active" class="form-select form-select-sm" aria-label="Status">
                                        @foreach ($activeOptions as $v => $l)<option value="{{ $v }}" @selected((int) $e->is_active === $v)>{{ $l }}</option>@endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="text-end mt-1"><button class="btn btn-sm {{ $e->exists ? 'btn-link' : 'btn-primary' }}">{{ $e->exists ? 'Save' : 'Add rule' }}</button></div>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
