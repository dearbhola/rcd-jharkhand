<?php

namespace App\Domain\Performance;

use App\Domain\Reporting\ReportStatus;
use App\Models\Inspection;
use App\Models\RepairAttempt;
use App\Models\Report;
use App\Models\Road;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;

/**
 * Road condition history (§36): reports by year, repair and inspection history, and
 * recurring problem areas (chainage bands with repeated reports).
 */
class RoadHistoryService
{
    public function __construct(private readonly Settings $settings) {}

    /** @param array<string, mixed> $filters year, issue_category_id, severity_id, contractor_id, road_section_id */
    public function build(Road $road, array $filters): array
    {
        $reports = $this->reports($road, $filters);
        $ids = (clone $reports)->pluck('reports.id');
        $bin = max(100, $this->settings->int('performance.hotspot_bin_m'));
        $min = max(2, $this->settings->int('performance.hotspot_min_reports'));

        $byYear = (clone $reports)->selectRaw('YEAR(reports.created_at) AS y, COUNT(*) AS reported, SUM(reports.status = ?) AS closed', [ReportStatus::CLOSED])
            ->groupBy('y')->orderByDesc('y')->get();

        $hotspots = (clone $reports)
            ->join('issue_categories as ic', 'ic.id', '=', 'reports.issue_category_id')
            ->selectRaw('FLOOR(reports.chainage_m / ?) AS band, COUNT(*) AS n, GROUP_CONCAT(DISTINCT ic.name ORDER BY ic.name SEPARATOR \', \') AS categories, MAX(reports.created_at) AS last_at', [$bin])
            ->groupBy('band')->havingRaw('COUNT(*) >= ?', [$min])->orderByDesc('n')->get()
            ->map(fn ($h) => ['from_m' => (int) $h->band * $bin, 'to_m' => ((int) $h->band + 1) * $bin, 'count' => (int) $h->n,
                'categories' => $h->categories, 'last_at' => $h->last_at]);

        return [
            'road' => $road,
            'by_year' => $byYear,
            'reports' => (clone $reports)->with(['section:id,code', 'category:id,name', 'severity:id,name,color', 'currentResponsibility.contractor:id,name'])
                ->latest('reports.created_at')->paginate(25, ['reports.*'])->withQueryString(),
            'repairs' => RepairAttempt::with(['report:id,report_no,chainage_m', 'contractor:id,name', 'rejecter:id,name'])
                ->whereIn('report_id', $ids)->latest('submitted_at')->limit(50)->get(),
            'inspections' => Inspection::with(['report:id,report_no', 'inspector:id,name'])
                ->whereIn('report_id', $ids)->latest('inspected_at')->limit(50)->get(),
            'hotspots' => $hotspots,
            'hotspot_bin_m' => $bin,
            'totals' => [
                'reported' => $ids->count(),
                'closed' => (clone $reports)->where('reports.status', ReportStatus::CLOSED)->count(),
                'open' => (clone $reports)->whereNotIn('reports.status', ReportStatus::terminal())->count(),
                'repairs' => RepairAttempt::whereIn('report_id', $ids)->count(),
            ],
        ];
    }

    private function reports(Road $road, array $f): Builder
    {
        return Report::query()->where('reports.road_id', $road->id)
            ->when($f['year'] ?? null, fn ($q, $y) => $q->whereYear('reports.created_at', $y))
            ->when($f['issue_category_id'] ?? null, fn ($q, $v) => $q->where('reports.issue_category_id', $v))
            ->when($f['severity_id'] ?? null, fn ($q, $v) => $q->where('reports.severity_id', $v))
            ->when($f['road_section_id'] ?? null, fn ($q, $v) => $q->where('reports.road_section_id', $v))
            ->when($f['contractor_id'] ?? null, fn ($q, $v) => $q->whereHas('responsibilities', fn ($r) => $r->where('contractor_id', $v)));
    }
}
