@props(['table', 'numeric' => []])
{{-- Renders an Analytics Table; the first column links to the source record when a row has 'url'. --}}
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead><tr>@foreach ($table->columns as $key => $label)<th @class(['text-end' => in_array($key, $numeric, true)])>{{ $label }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($table->rows as $row)
            <tr>
                @foreach (array_keys($table->columns) as $i => $key)
                    @php($v = $row[$key] ?? null)
                    @php($v = $v instanceof \DateTimeInterface ? $v->format('d-M-Y H:i') : $v)
                    <td @class(['text-end' => in_array($key, $numeric, true)])>
                        @if ($i === 0 && ! empty($row['url']))<a href="{{ $row['url'] }}">{{ $v }}</a>@else{{ $v ?? '—' }}@endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($table->columns) }}" class="text-center text-muted py-4">No records for these filters.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
