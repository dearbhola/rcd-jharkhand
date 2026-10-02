<?php

namespace App\Http\Controllers\Analytics;

use App\Domain\Analytics\AnalyticsService;
use App\Domain\Analytics\Exporter;
use App\Http\Controllers\Concerns\HandlesExports;
use App\Http\Controllers\Controller;
use App\Models\Division;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reports & exports (§40).
 */
class AnalyticsController extends Controller
{
    use HandlesExports;

    public function index(): View
    {
        return view('analytics.index', ['types' => AnalyticsService::TYPES]);
    }

    public function show(Request $request, string $type, AnalyticsService $analytics, Exporter $exporter): Response|View
    {
        abort_unless(isset(AnalyticsService::TYPES[$type]), 404);
        [$format, $test] = $this->exportMode($request);
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'division_id' => ['nullable', 'integer'],
        ]);
        $filters += $this->defaultPeriod($type, $filters);

        $table = $analytics->build($type, $filters, $test);
        if ($format) {
            return $exporter->download($table, $format, $type.'-report');
        }

        return view('analytics.show', [
            'table' => $table, 'type' => $type, 'types' => AnalyticsService::TYPES, 'filters' => $filters,
            'divisions' => Division::orderBy('code')->pluck('name', 'id'),
        ]);
    }

    /** Sensible default windows so period reports don't scan everything. */
    private function defaultPeriod(string $type, array $filters): array
    {
        if (! empty($filters['from']) || ! empty($filters['to'])) {
            return [];
        }

        return match ($type) {
            'daily' => ['from' => now()->subDays(30)->toDateString(), 'to' => now()->toDateString()],
            'weekly' => ['from' => now()->subWeeks(12)->startOfWeek()->toDateString(), 'to' => now()->toDateString()],
            'monthly' => ['from' => now()->subMonths(11)->startOfMonth()->toDateString(), 'to' => now()->toDateString()],
            default => [],
        };
    }
}
