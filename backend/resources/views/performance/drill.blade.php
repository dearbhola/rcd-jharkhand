<x-app-layout :title="$table->title" :breadcrumbs="['Contractor performance' => route('performance.index'), \App\Domain\Performance\PerformanceService::METRICS[$metric][0]]">
    <x-page-header :title="$table->title" :subtitle="$table->subtitle"><x-export-bar /></x-page-header>
    @if ($table->filters)
        <p class="small text-muted">@foreach ($table->filters as $k => $v)<strong>{{ $k }}:</strong> {{ $v }}@if (! $loop->last) · @endif @endforeach</p>
    @endif
    <div class="card">
        <div class="card-header">{{ count($table->rows) }} record(s)</div>
        <x-data-table :table="$table" :numeric="['hours', 'attempt']" />
    </div>
</x-app-layout>
