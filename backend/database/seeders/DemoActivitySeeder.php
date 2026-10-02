<?php

namespace Database\Seeders;

use App\Domain\Gis\LineString;
use App\Domain\Reporting\ReportService;
use App\Domain\Reporting\ReportSubmission;
use App\Domain\Sla\EscalationService;
use App\Domain\Workflow\TransitionInput;
use App\Domain\Workflow\WorkflowEngine;
use App\Models\AssetType;
use App\Models\IssueCategory;
use App\Models\Report;
use App\Models\Road;
use App\Models\Severity;
use App\Models\User;
use App\Support\TestDataMode;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Demo history (non-production, all is_test): ~2½ months of reports driven through the real
 * ReportService and WorkflowEngine with simulated time, so dashboards, SLA, escalations and
 * contractor performance have genuine data. Every step is performed by the actual task holder.
 */
class DemoActivitySeeder extends Seeder
{
    private const REPORTS = 36;

    private string $tmp;

    private int $imageNo = 0;

    public function __construct(
        private readonly ReportService $reports,
        private readonly WorkflowEngine $engine,
        private readonly EscalationService $escalations,
    ) {}

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo activity must not be seeded in production.');
        }

        app(TestDataMode::class)->include(true);
        $this->tmp = storage_path('app/demo-seed-tmp');
        @mkdir($this->tmp, 0775, true);
        mt_srand(4242);

        $realNow = Carbon::now();
        $citizens = User::whereHas('roles', fn ($q) => $q->where('code', 'CITIZEN'))->get();
        $staff = User::whereHas('roles', fn ($q) => $q->where('code', 'RCD_STAFF'))->get();
        $roadType = AssetType::where('code', AssetType::ROAD)->firstOrFail();
        $categories = IssueCategory::where('asset_type_id', $roadType->id)->where('parent_id', null)->get();
        $severities = Severity::orderBy('rank')->get();
        $roads = Road::with('currentGeometry')->where('code', '<=', 'RCD-017')->orderBy('code')->get();

        try {
            for ($i = 0; $i < self::REPORTS; $i++) {
                $scenario = $i % 12;
                $start = $realNow->copy()->subDays(mt_rand(3, 75))->setTime(mt_rand(7, 16), mt_rand(0, 59));
                if (in_array($scenario, [3, 5, 7], true)) {
                    $start = $realNow->copy()->subHours(mt_rand(6, 60)); // recent, still open
                }
                Carbon::setTestNow($start);

                $road = $scenario === 8 ? $roads->whereIn('code', ['RCD-015', 'RCD-016', 'RCD-017'])->random() : $roads->where('code', '<=', 'RCD-014')->random();
                $reporter = $scenario % 4 === 0 && $staff->isNotEmpty() ? $staff->random() : $citizens->random();
                $report = $this->file($reporter, $road, $categories->random(), $severities->random());
                if (! $report) {
                    continue;
                }

                $this->play($report, $scenario, $realNow);
            }
        } finally {
            Carbon::setTestNow();
            array_map('unlink', glob($this->tmp.'/*.jpg') ?: []);
            @rmdir($this->tmp);
        }

        // Record breaches / escalations for work still open today.
        $this->escalations->scan();
    }

    private function play(Report $report, int $scenario, Carbon $realNow): void
    {
        // [action, hours after previous step, extra input]
        $closeOnTime = [['validate', 3], ['acknowledge', 5], ['submit_repair', 30], ['accept', 10], ['accept', 12], ['approve', 20]];
        $script = match ($scenario) {
            0, 6 => $closeOnTime,
            1 => [['validate', 2], ['acknowledge', 6], ['submit_repair', 40], ['reject', 8, 'Patch edges not sealed; water seepage visible'],
                ['submit_repair', 30], ['accept', 6], ['accept', 18], ['approve', 30]],
            2 => [['validate', 8], ['acknowledge', 20], ['submit_repair', 200], ['accept', 30], ['reject', 40, 'Compaction inadequate, surface uneven'],
                ['submit_repair', 60], ['accept', 20], ['accept', 30], ['approve', 50]],
            3 => [],                                                                   // awaiting validation
            4 => [['validate', 4], ['acknowledge', 30]],                               // repair running late
            5 => [['validate', 2]],                                                    // assigned, contractor not started
            7 => [['validate', 3], ['acknowledge', 4], ['submit_repair', 20]],         // awaiting JE inspection
            8 => [['validate', 6]],                                                    // department route
            9 => [['invalidate', 5, 'Not an RCD road defect — private approach road']],
            10 => [['validate', 4], ['acknowledge', 10], ['submit_repair', 50], ['accept', 12], ['accept', 20], ['reject', 30, 'Work not to specification; redo full depth'], ['acknowledge', 40]],
            11 => [['validate', 5], ['acknowledge', 8], ['submit_repair', 45], ['accept', 14], ['accept', 26]], // with EE
        };

        foreach ($script as $step) {
            [$action, $hours] = $step;
            $at = Carbon::now()->addMinutes((int) round($hours * 60 * (0.7 + mt_rand(0, 60) / 100)));
            if ($at->gt($realNow)) {
                break; // never act in the future
            }
            Carbon::setTestNow($at);
            if (! $this->act($report, $action, $step[2] ?? null)) {
                break;
            }
        }
    }

    private function act(Report $report, string $action, ?string $reason): bool
    {
        $instance = $this->engine->activeInstance($report);
        $holder = $instance?->activeAssignments()->with('user')->first()?->user;
        if (! $holder) {
            return false;
        }

        $needsPhotos = in_array($action, ['submit_repair', 'accept', 'reject'], true) && ! ($action === 'reject' && $holder->hasRole('EE'));
        $files = $needsPhotos ? array_map(fn () => $this->image(), range(1, $action === 'submit_repair' ? 2 : 1)) : [];

        $this->engine->transition($report, $action, $holder, new TransitionInput(
            comment: $reason ?? match ($action) {
                'validate' => 'Verified', 'approve' => 'Approved', default => null
            },
            latitude: $report->latitude + 0.00002,
            longitude: $report->longitude,
            accuracyM: (float) mt_rand(4, 12),
            capturedAt: now(),
            files: $files,
            repairDescription: $action === 'submit_repair' ? 'Area cut to shape, cleaned, tack coat applied, bituminous mix laid and compacted' : null,
        ), $instance->version);

        return true;
    }

    private function file(User $reporter, Road $road, IssueCategory $category, Severity $severity): ?Report
    {
        $line = LineString::fromGeoJson($road->currentGeometry->geojson);
        [$lng, $lat] = $line->pointAt(mt_rand(300, max(400, (int) $line->length() - 300)));

        $result = $this->reports->submit($reporter, new ReportSubmission(
            clientUuid: (string) Str::uuid(),
            latitude: $lat,
            longitude: $lng,
            accuracyM: (float) mt_rand(4, 15),
            capturedAt: now()->subMinutes(mt_rand(1, 10)),
            gpsFixAt: now()->subMinutes(mt_rand(1, 10)),
            assetTypeId: $category->asset_type_id,
            issueCategoryId: $category->id,
            severityId: $severity->id,
            description: collect(['Deep pothole in carriageway', 'Edge broken near shoulder', 'Cracks spreading across lane', 'Water logging after rain', null])->random(),
        ), [$this->image(), $this->image()]);

        return $result['report'];
    }

    /** Small, unique JPEG (unique bytes avoid "same photo in another report" flags). */
    private function image(): UploadedFile
    {
        $n = ++$this->imageNo;
        $img = imagecreatetruecolor(640, 480);
        imagefill($img, 0, 0, imagecolorallocate($img, 90 + $n % 60, 95 + ($n * 7) % 50, 100 + ($n * 13) % 40));
        imagefilledellipse($img, 320 + mt_rand(-80, 80), 260 + mt_rand(-60, 60), mt_rand(140, 260), mt_rand(80, 160), imagecolorallocate($img, 35, 35, 35));
        imagestring($img, 2, 8, 8, "demo #{$n}", imagecolorallocate($img, 255, 255, 255));
        $path = "{$this->tmp}/demo{$n}.jpg";
        imagejpeg($img, $path, 80);
        imagedestroy($img);

        return new UploadedFile($path, "demo{$n}.jpg", 'image/jpeg', null, true);
    }
}
