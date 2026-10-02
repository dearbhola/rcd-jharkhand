@props(['status'])
@php([$label, $colour] = \App\Domain\Reporting\ReportStatus::labels()[$status] ?? [$status, 'secondary'])
<span class="badge text-bg-{{ $colour }}">{{ $label }}</span>
