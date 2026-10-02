<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $table->title }}</title>
    <style>
        @page { margin: 18mm 12mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 15px; margin: 0 0 2px; color: #0b2a4a; }
        .sub { color: #555; margin-bottom: 6px; }
        .meta { font-size: 8.5px; color: #444; margin-bottom: 8px; }
        .test { border: 1.5px solid #6f42c1; color: #6f42c1; font-weight: bold; padding: 4px 6px; margin-bottom: 8px; text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0b2a4a; color: #fff; text-align: left; padding: 4px; font-size: 8.5px; }
        td { border-bottom: 1px solid #ddd; padding: 3px 4px; vertical-align: top; }
        tr:nth-child(even) td { background: #f6f7f9; }
        .num { text-align: right; }
        .notes { margin-top: 8px; font-size: 8px; color: #555; }
        footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5px; color: #777; }
    </style>
</head>
<body>
<footer>RCD Road &amp; Asset Monitoring System · generated {{ now()->format('d-M-Y H:i T') }}</footer>
<h1>{{ $table->title }}</h1>
@if ($table->subtitle)<div class="sub">{{ $table->subtitle }}</div>@endif
@if ($table->includesTestData)<div class="test">TEST DATA INCLUDED — NOT AN OFFICIAL REPORT</div>@endif
@if ($table->filters)
    <div class="meta">@foreach ($table->filters as $k => $v)<strong>{{ $k }}:</strong> {{ $v }}@if (! $loop->last) · @endif @endforeach</div>
@endif
<table>
    <thead><tr>@foreach ($table->columns as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse ($table->matrix() as $row)
        <tr>@foreach ($row as $cell)<td class="{{ is_numeric($cell) ? 'num' : '' }}">{{ $cell }}</td>@endforeach</tr>
    @empty
        <tr><td colspan="{{ count($table->columns) }}">No records.</td></tr>
    @endforelse
    </tbody>
</table>
@if ($table->notes)<div class="notes">@foreach ($table->notes as $n)<div>{{ $n }}</div>@endforeach</div>@endif
</body>
</html>
