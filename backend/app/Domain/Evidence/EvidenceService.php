<?php

namespace App\Domain\Evidence;

use App\Domain\Gis\GeoMath;
use App\Jobs\ProcessEvidence;
use App\Models\Evidence;
use App\Models\Report;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates and stores field evidence. The original file is stored unmodified with its
 * SHA-256; a watermarked display copy and thumbnail are produced by a queued job.
 */
class EvidenceService
{
    /** EXIF position further than this from the reported GPS is flagged. */
    private const EXIF_MISMATCH_M = 250;

    public function __construct(
        private readonly MediaInspector $inspector,
        private readonly Settings $settings,
    ) {}

    public function rules(string $context): EvidenceRules
    {
        return EvidenceRules::for($context, $this->settings);
    }

    /**
     * Validate a batch for a context. Returns per-file facts used when storing.
     *
     * @param  list<UploadedFile>  $files
     * @return list<array{file: UploadedFile, kind: string, mime: string, sha256: string, duration: ?float}>
     */
    public function validateBatch(string $context, array $files, string $field = 'evidence'): array
    {
        $rules = $this->rules($context);
        $checked = [];
        $errors = [];
        $hashes = [];

        foreach (array_values($files) as $i => $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                $errors["{$field}.{$i}"] = 'The file failed to upload. Please retry.';

                continue;
            }

            $mime = $this->inspector->mimeType($file->getRealPath());
            $kind = in_array($mime, $rules->imageMimes, true) ? Evidence::KIND_PHOTO
                : (in_array($mime, $rules->videoMimes, true) ? Evidence::KIND_VIDEO : null);
            $sizeKb = (int) ceil($file->getSize() / 1024);

            if ($kind === null) {
                $errors["{$field}.{$i}"] = "File type {$mime} is not allowed.";

                continue;
            }
            if ($kind === Evidence::KIND_PHOTO && $sizeKb > $rules->maxImageKb) {
                $errors["{$field}.{$i}"] = "Photo exceeds {$rules->maxImageKb} KB.";

                continue;
            }
            if ($kind === Evidence::KIND_VIDEO && $sizeKb > $rules->maxVideoKb) {
                $errors["{$field}.{$i}"] = "Video exceeds {$rules->maxVideoKb} KB.";

                continue;
            }

            $duration = $kind === Evidence::KIND_VIDEO ? $this->inspector->videoDuration($file->getRealPath()) : null;
            if ($duration !== null && $duration > $rules->maxVideoSeconds + 0.5) {
                $errors["{$field}.{$i}"] = "Video is longer than {$rules->maxVideoSeconds} seconds.";

                continue;
            }

            $sha = hash_file('sha256', $file->getRealPath());
            if (isset($hashes[$sha])) {
                $errors["{$field}.{$i}"] = 'The same file was attached twice.';

                continue;
            }
            $hashes[$sha] = true;

            $checked[] = ['file' => $file, 'kind' => $kind, 'mime' => $mime, 'sha256' => $sha, 'duration' => $duration];
        }

        $photos = count(array_filter($checked, fn ($c) => $c['kind'] === Evidence::KIND_PHOTO));
        $videos = count($checked) - $photos;

        if ($photos < $rules->minPhotos) {
            $errors[$field] = "At least {$rules->minPhotos} photo(s) required.";
        } elseif ($photos > $rules->maxPhotos) {
            $errors[$field] = "At most {$rules->maxPhotos} photo(s) allowed.";
        } elseif ($videos > $rules->maxVideos) {
            $errors[$field] = "At most {$rules->maxVideos} video(s) allowed.";
        } elseif ($rules->videoRequired && $videos === 0) {
            $errors[$field] = 'A video is required.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $checked;
    }

    /**
     * Store one validated file. Call inside the owning transaction; processing is queued after commit.
     *
     * @param  array{file: UploadedFile, kind: string, mime: string, sha256: string, duration: ?float}  $checked
     * @param  array{lat: float, lng: float, accuracy: ?float, captured_at: Carbon, client_uuid?: ?string, device_id?: ?int}  $capture
     */
    public function store(array $checked, Report $report, Model $owner, User $uploader, array $capture): Evidence
    {
        $disk = config('rcd.evidence_disk');
        $uuid = (string) Str::uuid();
        $ext = $checked['file']->guessExtension() ?: ($checked['kind'] === Evidence::KIND_PHOTO ? 'jpg' : 'mp4');
        $dir = sprintf('original/%s/%s', $capture['captured_at']->format('Y/m'), $report->id);
        $path = Storage::disk($disk)->putFileAs($dir, $checked['file'], "{$uuid}.{$ext}");

        $exif = $this->inspector->exif($checked['file']->getRealPath(), $checked['mime']);
        $flags = [];

        if ($exif['lat'] !== null && $exif['lng'] !== null
            && GeoMath::distance($exif['lat'], $exif['lng'], $capture['lat'], $capture['lng']) > self::EXIF_MISMATCH_M) {
            $flags[] = 'exif_gps_mismatch';
        }
        if ($exif['taken_at'] && abs(Carbon::parse($exif['taken_at'])->diffInMinutes($capture['captured_at'])) > 60) {
            $flags[] = 'exif_time_mismatch';
        }
        if (Evidence::withoutGlobalScopes()->where('sha256', $checked['sha256'])->where('report_id', '!=', $report->id)->exists()) {
            $flags[] = 'duplicate_elsewhere';
        }
        if ($checked['kind'] === Evidence::KIND_VIDEO && $checked['duration'] === null) {
            $flags[] = 'duration_unverified';
        }

        $evidence = Evidence::create([
            'client_uuid' => $capture['client_uuid'] ?? null,
            'report_id' => $report->id,
            'evidenceable_type' => $owner->getMorphClass(),
            'evidenceable_id' => $owner->getKey(),
            'kind' => $checked['kind'],
            'disk' => $disk,
            'original_path' => $path,
            'mime_type' => $checked['mime'],
            'file_size' => $checked['file']->getSize(),
            'duration_s' => $checked['duration'] !== null ? (int) round($checked['duration']) : null,
            'sha256' => $checked['sha256'],
            'latitude' => $capture['lat'],
            'longitude' => $capture['lng'],
            'gps_accuracy_m' => $capture['accuracy'],
            'captured_at' => $capture['captured_at'],
            'device_id' => $capture['device_id'] ?? null,
            'uploaded_by' => $uploader->id,
            'processing_status' => 'pending',
            'exif' => array_filter($exif, fn ($v) => $v !== null) ?: null,
            'flags' => $flags ?: null,
            'is_test' => $report->is_test,
        ]);

        ProcessEvidence::dispatch($evidence->id)->afterCommit();

        return $evidence;
    }
}
