<?php

namespace App\Jobs;

use App\Models\Evidence;
use App\Support\Settings;
use App\Support\TestDataMode;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Builds the watermarked display copy and thumbnail for one evidence file.
 * The original is never modified. The watermark repeats facts already stored as data;
 * it is a convenience for viewers, not the record.
 */
class ProcessEvidence implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly int $evidenceId) {}

    public function handle(Settings $settings, TestDataMode $testData): void
    {
        $evidence = $testData->withTestData(fn () => Evidence::with(['report.road', 'uploader'])->find($this->evidenceId));
        if (! $evidence || $evidence->processing_status === 'done') {
            return;
        }

        try {
            $evidence->kind === Evidence::KIND_PHOTO
                ? $this->processPhoto($evidence, $settings)
                : $this->processVideo($evidence);
        } catch (Throwable $e) {
            Log::error('Evidence processing failed', ['evidence_id' => $evidence->id, 'error' => $e->getMessage()]);
            $evidence->update(['processing_status' => 'failed']);

            throw $e;
        }
    }

    /** @return list<string> */
    public static function watermarkLines(Evidence $evidence): array
    {
        $report = $evidence->report;
        $at = $evidence->captured_at->copy()->setTimezone(config('app.timezone'));

        return array_values(array_filter([
            'RCD ROAD MONITORING'.($evidence->is_test ? '  [TEST]' : ''),
            'Report: '.$report->report_no,
            'Date: '.$at->format('d-M-Y').'   Time: '.$at->format('H:i:s T'),
            sprintf('Lat: %.6f   Long: %.6f%s', $evidence->latitude, $evidence->longitude,
                $evidence->gps_accuracy_m !== null ? sprintf('   ±%.0f m', $evidence->gps_accuracy_m) : ''),
            $report->road ? 'Road: '.$report->road->code.'   Chainage: '.km($report->chainage_m, true) : null,
            'By: '.$evidence->uploader->name,
        ]));
    }

    private function processPhoto(Evidence $evidence, Settings $settings): void
    {
        $disk = Storage::disk($evidence->disk);
        $manager = ImageManager::gd();
        $font = config('rcd.watermark_font');
        $quality = $settings->int('evidence.image_quality');

        $image = $manager->read($disk->get($evidence->original_path))->orient();
        $image->scaleDown($settings->int('evidence.image_max_dimension'), $settings->int('evidence.image_max_dimension'));

        $lines = self::watermarkLines($evidence);
        $size = max(14, (int) round($image->width() / 55));
        $lineHeight = (int) round($size * 1.45);
        $pad = (int) round($size * 0.8);
        $boxHeight = $pad * 2 + $lineHeight * count($lines);

        $image->drawRectangle(0, $image->height() - $boxHeight, function ($r) use ($image, $boxHeight) {
            $r->size($image->width(), $boxHeight);
            $r->background('rgba(0, 0, 0, 0.55)');
        });
        foreach ($lines as $i => $line) {
            $y = $image->height() - $boxHeight + $pad + $lineHeight * $i + $size;
            $image->text($line, $pad, $y, function (FontFactory $f) use ($font, $size, $i) {
                $f->filename($font);
                $f->size($i === 0 ? $size * 1.1 : $size);
                $f->color($i === 0 ? '#ffc107' : '#ffffff');
            });
        }

        $base = $this->derivedBase($evidence);
        $disk->put("{$base}-display.jpg", (string) $image->toJpeg($quality));

        $thumb = $manager->read($disk->get($evidence->original_path))->orient()->coverDown(360, 270);
        $disk->put("{$base}-thumb.jpg", (string) $thumb->toJpeg(75));

        $evidence->update([
            'display_path' => "{$base}-display.jpg",
            'thumbnail_path' => "{$base}-thumb.jpg",
            'width' => $image->width(),
            'height' => $image->height(),
            'processing_status' => 'done',
        ]);
    }

    /**
     * Video: burn the watermark and grab a thumbnail with ffmpeg when it works on this server.
     * Otherwise the original is kept as-is and the item is flagged; the player overlays the metadata.
     */
    private function processVideo(Evidence $evidence): void
    {
        $disk = Storage::disk($evidence->disk);
        if (! method_exists($disk, 'path') || ! $this->ffmpegWorks()) {
            $evidence->update([
                'processing_status' => 'skipped',
                'flags' => array_values(array_unique([...($evidence->flags ?? []), 'video_not_processed'])),
            ]);

            return;
        }

        $base = $this->derivedBase($evidence);
        $src = $disk->path($evidence->original_path);
        $text = str_replace([':', "'"], ['\\:', ''], implode('\n', self::watermarkLines($evidence)));
        $filter = sprintf("drawtext=fontfile=%s:text='%s':fontcolor=white:fontsize=h/40:box=1:boxcolor=black@0.55:boxborderw=12:x=12:y=h-th-12",
            config('rcd.watermark_font'), $text);

        (new Process([config('rcd.ffmpeg.ffmpeg'), '-y', '-i', $src, '-vf', $filter, '-c:a', 'copy', $disk->path("{$base}-display.mp4")]))->setTimeout(300)->mustRun();
        (new Process([config('rcd.ffmpeg.ffmpeg'), '-y', '-i', $src, '-ss', '00:00:01', '-frames:v', '1', '-vf', 'scale=360:-1', $disk->path("{$base}-thumb.jpg")]))->setTimeout(60)->mustRun();

        $evidence->update(['display_path' => "{$base}-display.mp4", 'thumbnail_path' => "{$base}-thumb.jpg", 'processing_status' => 'done']);
    }

    private function derivedBase(Evidence $evidence): string
    {
        return 'derived/'.pathinfo(str_replace('original/', '', $evidence->original_path), PATHINFO_DIRNAME).'/'.pathinfo($evidence->original_path, PATHINFO_FILENAME);
    }

    private function ffmpegWorks(): bool
    {
        try {
            return (new Process([config('rcd.ffmpeg.ffmpeg'), '-version']))->setTimeout(5)->run() === 0;
        } catch (Throwable) {
            return false;
        }
    }
}
