<?php

namespace Tests\Support;

use App\Domain\Workflow\WorkflowEngine;
use App\Models\Report;
use App\Models\User;
use Illuminate\Testing\TestResponse;

trait WorkflowHelper
{
    use ReportsHelper;

    protected function fileReport(User $reporter, string $roadCode = 'RCD-005', int $chainage = 3000, array $overrides = []): Report
    {
        $this->actingAs($reporter)->post('/reports', $this->reportPayload($this->road($roadCode), $chainage, $overrides), ['Accept' => 'application/json'])->assertSuccessful();

        return Report::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    protected function version(Report $report): int
    {
        return app(WorkflowEngine::class)->activeInstance($report)->version;
    }

    /** Perform an action standing at the report site (offset metres east) with evidence when given. */
    protected function act(User $user, Report $report, string $action, array $data = [], float $offsetM = 5, ?int $version = null): TestResponse
    {
        $lngPerM = 1 / (111195 * cos(deg2rad($report->latitude)));

        return $this->actingAs($user)->post("/reports/{$report->id}/actions/{$action}", [
            'version' => $version ?? $this->version($report),
            'latitude' => $report->latitude,
            'longitude' => $report->longitude + $offsetM * $lngPerM,
            'accuracy' => 6,
            ...$data,
        ], ['Accept' => 'application/json']);
    }

    protected function photos(int $n = 2, int $seed = 0): array
    {
        return array_map(fn ($i) => $this->photo("e{$seed}_{$i}.jpg", 900 + $seed * 10 + $i), range(1, $n));
    }

    protected function holders(Report $report): array
    {
        return app(WorkflowEngine::class)->activeInstance($report)?->activeAssignments()->pluck('user_id')->sort()->values()->all() ?? [];
    }
}
