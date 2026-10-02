<?php

namespace App\Domain\Workflow;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * What an actor submits with a workflow action. Location fields are device GPS
 * (checked against the report site by location guards).
 */
final class TransitionInput
{
    /** @param list<UploadedFile> $files */
    public function __construct(
        public readonly ?string $comment = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?float $accuracyM = null,
        public readonly ?Carbon $capturedAt = null,
        public readonly array $files = [],
        public readonly ?string $repairDescription = null,
        public readonly ?int $duplicateOfReportId = null,
        public readonly bool $locationOverride = false,
        public readonly ?string $ip = null,
    ) {}

    public static function system(string $comment): self
    {
        return new self(comment: $comment);
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
