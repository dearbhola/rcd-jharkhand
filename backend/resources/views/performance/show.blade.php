@php($short = ['tasks_total' => 'Tasks', 'tasks_completed' => 'Approved', 'tasks_open' => 'Open', 'tasks_overdue' => 'Overdue', 'sla_compliance' => 'SLA %',
    'avg_repair_hours' => 'Avg. repair (h)', 'reopened_repairs' => 'Reopened', 'rejections' => 'Rejections', 'repeat_defects' => 'Repeats'])
@php($defs = \App\Domain\Performance\PerformanceService::METRICS + ['rejections' => ['Rejections', 'attempts', 'JE · AE · EE']])
@php($rejCell = function ($row, $q) {
    return collect(['JE' => 'rejections_je', 'AE' => 'rejections_ae', 'EE' => 'rejections_ee'])
        ->map(fn ($k, $label) => '<a href="'.e(route('performance.drill', ['metric' => $k] + $q)).'" title="'.$label.' rejections">'.$label.' '.($row[$k] ?? 0).'</a>')
        ->join(' · ');
})

@php($fmt = fn ($k, $v) => $v === null ? '—' : $v.($defs[$k][1] === 'percent' ? '%' : ($defs[$k][1] === 'hours' ? ' h' : '')))
<x-app-layout :title="$contractor->name" :breadcrumbs="['Contractor performance' => route('performance.index'), $contractor->name]">
    <x-page-header :title="$contractor->name" subtitle="Performance from system records — click any figure to see the records behind it.">
        <a href="{{ route('contractors.show', $contractor) }}" class="btn btn-sm btn-outline-primary">Contractor profile</a>
        <x-export-bar />
    </x-page-header>
    @include('performance._filters', ['hideContractor' => true])
    @foreach ([
        'Tasks' => ['tasks_total', 'tasks_completed', 'tasks_open', 'tasks_overdue'],
        'Timeliness' => ['sla_compliance', 'sla_breaches', 'avg_response_hours', 'avg_repair_hours'],
        'Quality' => ['repair_attempts', 'reopened_repairs', 'rejections_je', 'rejections_ae', 'rejections_ee', 'repeat_defects'],
        'Contracts' => ['contracts_total', 'contracts_active', 'contracts_completed', 'roads_maintained'],
    ] as $group => $keys)
        <h2 class="h6 text-uppercase text-muted mt-3">{{ $group }}</h2>
        <div class="row g-3">
            @foreach ($keys as $k)
                <div class="col-6 col-md-3 col-xl-2">
                    <a href="{{ route('performance.drill', ['metric' => $k] + $query) }}" class="card stat-card stat-link h-100 text-decoration-none" title="{{ $defs[$k][2] }}">
                        <div class="card-body">
                            <div class="stat-value text-body">{{ $fmt($k, $metrics[$k]) }}</div>
                            <div class="stat-label">{{ $defs[$k][0] }}</div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="card mt-4">
        <div class="card-header">By contract</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 small">
                <thead><tr><th>Contract</th><th>Maintenance period</th>@foreach ($summary as $m)<th class="text-end" title="{{ $defs[$m][2] }}">{{ $short[$m] ?? $defs[$m][0] }}</th>@endforeach<th></th></tr></thead>
                <tbody>
                @foreach ($perContract as $row)
                    @php($c = $row['contract'])
                    <tr>
                        <td><a href="{{ route('contracts.show', $c) }}">{{ $c->contract_no }}</a></td>
                        <td class="text-nowrap">{{ d($c->maintenance_start_date) }} – {{ d($c->maintenance_end_date) }}</td>
                        @foreach ($summary as $m)
                            <td class="text-end text-nowrap">
                                @if ($m === 'rejections'){!! $rejCell($row, ['contractor_id' => $contractor->id, 'contract_id' => $c->id]) !!}
                                @else<a href="{{ route('performance.drill', ['metric' => $m, 'contractor_id' => $contractor->id, 'contract_id' => $c->id]) }}">{{ $fmt($m, $row[$m]) }}</a>@endif
                            </td>
                        @endforeach
                        <td><a href="{{ route('contracts.completion', $c) }}" class="text-nowrap">Completion report</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
