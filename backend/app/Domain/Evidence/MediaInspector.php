<?php

namespace App\Domain\Evidence;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Reads facts from uploaded media without trusting the client: real MIME type (magic bytes),
 * EXIF GPS/time for photos, and duration for videos (when ffprobe is available).
 */
class MediaInspector
{
    private ?bool $ffprobeWorks = null;

    public function mimeType(string $path): string
    {
        return (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
    }

    /** @return array{lat: ?float, lng: ?float, taken_at: ?string, make: ?string, model: ?string} */
    public function exif(string $path, string $mime): array
    {
        $empty = ['lat' => null, 'lng' => null, 'taken_at' => null, 'make' => null, 'model' => null];
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $empty;
        }

        $data = @exif_read_data($path, null, true) ?: [];
        $gps = $data['GPS'] ?? [];

        return [
            'lat' => $this->gpsDecimal($gps['GPSLatitude'] ?? null, $gps['GPSLatitudeRef'] ?? 'N'),
            'lng' => $this->gpsDecimal($gps['GPSLongitude'] ?? null, $gps['GPSLongitudeRef'] ?? 'E'),
            'taken_at' => isset($data['EXIF']['DateTimeOriginal']) ? $this->exifDate($data['EXIF']['DateTimeOriginal']) : null,
            'make' => isset($data['IFD0']['Make']) ? trim((string) $data['IFD0']['Make']) : null,
            'model' => isset($data['IFD0']['Model']) ? trim((string) $data['IFD0']['Model']) : null,
        ];
    }

    /** Video duration in seconds, or null when it cannot be determined (ffprobe missing/broken). */
    public function videoDuration(string $path): ?float
    {
        if (! $this->ffprobeAvailable()) {
            return null;
        }

        $process = new Process([config('rcd.ffmpeg.ffprobe'), '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', $path]);
        $process->setTimeout(20)->run();

        return $process->isSuccessful() && is_numeric(trim($process->getOutput())) ? (float) trim($process->getOutput()) : null;
    }

    public function ffprobeAvailable(): bool
    {
        if ($this->ffprobeWorks !== null) {
            return $this->ffprobeWorks;
        }

        try {
            $process = new Process([config('rcd.ffmpeg.ffprobe'), '-version']);
            $process->setTimeout(5)->run();
            $this->ffprobeWorks = $process->isSuccessful();
        } catch (Throwable) {
            $this->ffprobeWorks = false;
        }

        if (! $this->ffprobeWorks) {
            Log::warning('ffprobe is not available; video duration cannot be verified server-side.');
        }

        return $this->ffprobeWorks;
    }

    private function gpsDecimal(mixed $parts, string $ref): ?float
    {
        if (! is_array($parts) || count($parts) < 3) {
            return null;
        }
        $f = function ($v) {
            [$n, $d] = array_pad(explode('/', (string) $v), 2, 1);

            return (float) $d ? (float) $n / (float) $d : 0.0;
        };
        $value = $f($parts[0]) + $f($parts[1]) / 60 + $f($parts[2]) / 3600;

        return in_array(strtoupper($ref), ['S', 'W'], true) ? -$value : $value;
    }

    private function exifDate(string $value): ?string
    {
        try {
            return Carbon::createFromFormat('Y:m:d H:i:s', $value, config('app.timezone'))?->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }
}
