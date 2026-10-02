@php($activeOptions = [1 => 'Active', 0 => 'Inactive'])
<x-app-layout title="Categories" :breadcrumbs="['Masters', 'Categories']">
    <x-page-header title="Categories & lookups" subtitle="Configurable asset types, damage categories, severities and road categories. Deactivate instead of deleting." />

    <ul class="nav nav-tabs mb-3">
        @foreach (['assets' => 'Asset types & damage categories', 'severities' => 'Severities', 'roads' => 'Road categories'] as $key => $label)
            <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="?tab={{ $key }}">{{ $label }}</a></li>
        @endforeach
    </ul>

    @if ($tab === 'assets')
        @foreach ($assetTypes as $type)
            <div class="card mb-3">
                <form method="POST" action="{{ route('asset-types.update', $type) }}" class="card-header row g-2 align-items-center mx-0">
                    @csrf @method('PUT')
                    <div class="col-md-3"><input name="name" value="{{ $type->name }}" class="form-control form-control-sm fw-semibold" aria-label="Asset type name"></div>
                    <div class="col-md-2"><input name="code" value="{{ $type->code }}" class="form-control form-control-sm" aria-label="Code"></div>
                    <div class="col-md-2">
                        <select name="geometry_kind" class="form-select form-select-sm" aria-label="Geometry">
                            <option value="point" @selected($type->geometry_kind === 'point')>Point</option>
                            <option value="line" @selected($type->geometry_kind === 'line')>Line</option>
                        </select>
                    </div>
                    <div class="col-md-1"><input name="sort_order" value="{{ $type->sort_order }}" class="form-control form-control-sm" aria-label="Order" title="Order"></div>
                    <div class="col-md-2">
                        <select name="is_active" class="form-select form-select-sm" aria-label="Status">
                            @foreach ($activeOptions as $v => $l)<option value="{{ $v }}" @selected((int) $type->is_active === $v)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2 text-end"><button class="btn btn-sm btn-outline-primary">Save type</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th style="width:35%">Damage category</th><th>Code</th><th>Parent</th><th>Order</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @php($parents = $type->issueCategories->pluck('name', 'id'))
                        @foreach ($type->issueCategories as $category)
                            @foreach ([$category, ...$category->children] as $item)
                                @php($fid = 'ic'.$item->id)
                                <tr>
                                        <td>
                                            <form id="{{ $fid }}" method="POST" action="{{ route('issue-categories.update', $item) }}">@csrf @method('PUT')</form>
                                            <div class="d-flex align-items-center gap-1">
                                                @if ($item->parent_id)<i class="bi bi-arrow-return-right text-muted ms-2"></i>@endif
                                                <input form="{{ $fid }}" name="name" value="{{ $item->name }}" class="form-control form-control-sm" aria-label="Name">
                                            </div>
                                        </td>
                                        <td><input form="{{ $fid }}" name="code" value="{{ $item->code }}" class="form-control form-control-sm" aria-label="Code"></td>
                                        <td>
                                            <select form="{{ $fid }}" name="parent_id" class="form-select form-select-sm" aria-label="Parent">
                                                <option value="">— top level —</option>
                                                @foreach ($parents as $pid => $pname)
                                                    @continue($pid === $item->id)
                                                    <option value="{{ $pid }}" @selected($item->parent_id === $pid)>{{ $pname }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td style="width:80px"><input form="{{ $fid }}" name="sort_order" value="{{ $item->sort_order }}" class="form-control form-control-sm" aria-label="Order"></td>
                                        <td>
                                            <select form="{{ $fid }}" name="is_active" class="form-select form-select-sm" aria-label="Status">
                                                @foreach ($activeOptions as $v => $l)<option value="{{ $v }}" @selected((int) $item->is_active === $v)>{{ $l }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="text-end"><button form="{{ $fid }}" class="btn btn-sm btn-link">Save</button></td>
                                </tr>
                            @endforeach
                        @endforeach
                        @php($fid = 'icnew'.$type->id)
                        <tr class="table-light">
                                <td><form id="{{ $fid }}" method="POST" action="{{ route('issue-categories.store', $type) }}">@csrf<input type="hidden" name="is_active" value="1"></form><input form="{{ $fid }}" name="name" class="form-control form-control-sm" placeholder="New category name" required aria-label="New category"></td>
                                <td><input form="{{ $fid }}" name="code" class="form-control form-control-sm" placeholder="(auto)" aria-label="Code"></td>
                                <td>
                                    <select form="{{ $fid }}" name="parent_id" class="form-select form-select-sm" aria-label="Parent">
                                        <option value="">— top level —</option>
                                        @foreach ($parents as $pid => $pname)<option value="{{ $pid }}">{{ $pname }}</option>@endforeach
                                    </select>
                                </td>
                                <td><input form="{{ $fid }}" name="sort_order" value="99" class="form-control form-control-sm" aria-label="Order"></td>
                                <td><span class="small text-muted">Active</span></td>
                                <td class="text-end"><button form="{{ $fid }}" class="btn btn-sm btn-primary"><i class="bi bi-plus"></i> Add</button></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <div class="card">
            <div class="card-header">New asset type</div>
            <form method="POST" action="{{ route('asset-types.store') }}" class="card-body row g-2">
                @csrf
                <div class="col-md-4"><input name="name" class="form-control form-control-sm" placeholder="Name" required aria-label="Name"></div>
                <div class="col-md-3"><input name="code" class="form-control form-control-sm" placeholder="Code (auto)" aria-label="Code"></div>
                <div class="col-md-2">
                    <select name="geometry_kind" class="form-select form-select-sm" aria-label="Geometry"><option value="point">Point</option><option value="line">Line</option></select>
                </div>
                <input type="hidden" name="is_active" value="1">
                <div class="col-md-3"><button class="btn btn-sm btn-primary"><i class="bi bi-plus"></i> Add asset type</button></div>
            </form>
        </div>
    @elseif ($tab === 'severities')
        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Name</th><th>Code</th><th>Rank</th><th>Colour</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach ([...$severities, new \App\Models\Severity(['is_active' => true, 'color' => '#6c757d'])] as $severity)
                        @php($fid = 'sev'.($severity->id ?? 'new'))
                        <tr @class(['table-light' => ! $severity->exists])>
                                <td><form id="{{ $fid }}" method="POST" action="{{ $severity->exists ? route('severities.update', $severity) : route('severities.store') }}">@csrf @if ($severity->exists) @method('PUT') @endif</form><input form="{{ $fid }}" name="name" value="{{ $severity->name }}" class="form-control form-control-sm" placeholder="New severity" required aria-label="Name"></td>
                                <td><input form="{{ $fid }}" name="code" value="{{ $severity->code }}" class="form-control form-control-sm" placeholder="(auto)" aria-label="Code"></td>
                                <td style="width:90px"><input form="{{ $fid }}" name="rank" value="{{ $severity->rank }}" type="number" min="1" class="form-control form-control-sm" required aria-label="Rank" title="Higher = more severe"></td>
                                <td style="width:90px"><input form="{{ $fid }}" name="color" type="color" value="{{ $severity->color }}" class="form-control form-control-sm form-control-color" aria-label="Colour"></td>
                                <td>
                                    <select form="{{ $fid }}" name="is_active" class="form-select form-select-sm" aria-label="Status">
                                        @foreach ($activeOptions as $v => $l)<option value="{{ $v }}" @selected((int) $severity->is_active === $v)>{{ $l }}</option>@endforeach
                                    </select>
                                </td>
                                <td class="text-end"><button form="{{ $fid }}" class="btn btn-sm {{ $severity->exists ? 'btn-link' : 'btn-primary' }}">{{ $severity->exists ? 'Save' : 'Add' }}</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">Severity drives SLA and escalation rules. Rank must be unique; higher means more severe.</div>
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Name</th><th>Code</th><th>Order</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach ([...$roadCategories, new \App\Models\RoadCategory(['is_active' => true])] as $category)
                        @php($fid = 'rc'.($category->id ?? 'new'))
                        <tr @class(['table-light' => ! $category->exists])>
                                <td><form id="{{ $fid }}" method="POST" action="{{ $category->exists ? route('road-categories.update', $category) : route('road-categories.store') }}">@csrf @if ($category->exists) @method('PUT') @endif</form><input form="{{ $fid }}" name="name" value="{{ $category->name }}" class="form-control form-control-sm" placeholder="New road category" required aria-label="Name"></td>
                                <td><input form="{{ $fid }}" name="code" value="{{ $category->code }}" class="form-control form-control-sm" placeholder="(auto)" aria-label="Code"></td>
                                <td style="width:90px"><input form="{{ $fid }}" name="sort_order" value="{{ $category->sort_order }}" class="form-control form-control-sm" aria-label="Order"></td>
                                <td>
                                    <select form="{{ $fid }}" name="is_active" class="form-select form-select-sm" aria-label="Status">
                                        @foreach ($activeOptions as $v => $l)<option value="{{ $v }}" @selected((int) $category->is_active === $v)>{{ $l }}</option>@endforeach
                                    </select>
                                </td>
                                <td class="text-end"><button form="{{ $fid }}" class="btn btn-sm {{ $category->exists ? 'btn-link' : 'btn-primary' }}">{{ $category->exists ? 'Save' : 'Add' }}</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-app-layout>
