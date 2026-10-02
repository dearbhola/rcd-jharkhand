@php($short = ['tasks_total' => 'Tasks', 'tasks_completed' => 'Approved', 'tasks_open' => 'Open', 'tasks_overdue' => 'Overdue', 'sla_compliance' => 'SLA %',
    'avg_repair_hours' => 'Avg. repair (h)', 'reopened_repairs' => 'Reopened', 'rejections' => 'Rejections', 'repeat_defects' => 'Repeats'])
@php($defs = \App\Domain\Performance\PerformanceService::METRICS + ['rejections' => ['Rejections', 'attempts', 'Repair attempts rejected by the JE · AE · EE']])
@php($rejCell = function ($row, $q) {
    return collect(['JE' => 'rejections_je', 'AE' => 'rejections_ae', 'EE' => 'rejections_ee'])
        ->map(fn ($k, $label) => '<a href="'.e(route('performance.drill', ['metric' => $k] + $q)).'" title="'.$label.' rejections">'.$label.' '.($row[$k] ?? 0).'</a>')
        ->join(' · ');
})

<x-app-layout title="Contractor performance" :breadcrumbs="['Contractor performance']">
    <x-page-header title="Contractor performance" subtitle="Derived from workflow records. Every figure opens the records behind it.">
        <x-export-bar />
    </x-page-header>
    @include('performance._filters')
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 small">
                <thead>
                <tr>
                    <th>Contractor</th>
                    @foreach ($metrics as $m)<th class="text-end" title="{{ $defs[$m][2] }}">{{ $short[$m] ?? $defs[$m][0] }}</th>@endforeach
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="text-nowrap"><a href="{{ $row['url'] }}" class="fw-semibold">{{ $row['contractor']->name }}</a> <x-test-badge :model="$row['contractor']" /></td>
                        @foreach ($metrics as $m)
                            @php($v = $row[$m] ?? null)
                            <td class="text-end text-nowrap">
                                @if ($m === 'rejections')
                                    {!! $rejCell($row, ['contractor_id' => $row['contractor']->id] + $query) !!}
                                @elseif ($v === null)
                                    <span class="text-muted">—</span>
                                @else
                                    <a href="{{ route('performance.drill', ['metric' => $m, 'contractor_id' => $row['contractor']->id] + $query) }}"
                                       @class(['text-danger fw-semibold' => in_array($m, ['tasks_overdue', 'repeat_defects'], true) && $v > 0])>{{ $v }}{{ $defs[$m][1] === 'percent' ? '%' : '' }}</a>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($metrics) + 1 }}" class="text-center text-muted py-4">No contractors match.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-muted">
            Hover a heading for its definition. A repair task is a report handed to the contractor; figures cover reports made in the selected period.
            This information supports officials' review and does not award, rank or recommend contractors.
        </div>
    </div>
</x-app-layout>
