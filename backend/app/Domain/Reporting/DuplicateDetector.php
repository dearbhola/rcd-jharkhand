<?php

namespace App\Domain\Reporting;

use App\Domain\Gis\GeoMath;
use App\Models\Report;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Finds open reports near the same place, of the same category, within a time window.
 * Never deletes or merges by itself; the JE decides (merge is a workflow action).
 */
class DuplicateDetector
{
    public function __construct(private readonly Settings $settings) {}

    /** @return Collection<int, array{report: Report, distance_m: float}> */
    public function near(float $lat, float $lng, int $categoryId, ?int $excludeReportId = null, ?\DateTimeInterface $at = null): Collection
    {
        $radius = $this->settings->int('duplicate.radius_m');
        $since = ($at ? Carbon::instance($at) : now())->copy()->subHours($this->settings->int('duplicate.window_hours'));
        $box = GeoMath::bboxAround($lat, $lng, $radius);

        return Report::query()
            ->with(['severity:id,name,color', 'reporter:id,name'])
            ->where('issue_category_id', $categoryId)
            ->whereNotIn('status', ReportStatus::terminal())
            ->where('created_at', '>=', $since)
            ->whereBetween('latitude', [$box['min_lat'], $box['max_lat']])
            ->whereBetween('longitude', [$box['min_lng'], $box['max_lng']])
            ->when($excludeReportId, fn ($q, $id) => $q->whereKeyNot($id))
            ->limit(20)
            ->get()
            ->map(fn (Report $r) => ['report' => $r, 'distance_m' => GeoMath::distance($lat, $lng, $r->latitude, $r->longitude)])
            ->filter(fn ($row) => $row['distance_m'] <= $radius)
            ->sortBy('distance_m')
            ->values();
    }
}
