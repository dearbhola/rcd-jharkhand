<?php

namespace App\Domain\Reporting;

use Illuminate\Support\Carbon;

/**
 * What the reporter's device sends. Road, section, chainage, contractor and officers are NOT
 * part of it: the server resolves those from the GPS position.
 */
final class ReportSubmission
{
    public function __construct(
        public readonly string $clientUuid,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?float $accuracyM,
        public readonly Carbon $capturedAt,
        public readonly ?Carbon $gpsFixAt,
        public readonly int $assetTypeId,
        public readonly int $issueCategoryId,
        public readonly int $severityId,
        public readonly ?string $description,
        public readonly bool $locationOverride = false,
        public readonly string $source = 'web',
    ) {}
}
