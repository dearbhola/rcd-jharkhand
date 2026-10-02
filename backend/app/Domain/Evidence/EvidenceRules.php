<?php

namespace App\Domain\Evidence;

use App\Support\Settings;

/**
 * Evidence limits for a context (report | repair | inspection), all from system settings.
 */
final class EvidenceRules
{
    public const CONTEXT_REPORT = 'report';

    public const CONTEXT_REPAIR = 'repair';

    public const CONTEXT_INSPECTION = 'inspection';

    /** @param list<string> $imageMimes @param list<string> $videoMimes */
    public function __construct(
        public readonly int $minPhotos,
        public readonly int $maxPhotos,
        public readonly int $maxVideos,
        public readonly bool $videoRequired,
        public readonly int $maxImageKb,
        public readonly int $maxVideoKb,
        public readonly int $maxVideoSeconds,
        public readonly array $imageMimes,
        public readonly array $videoMimes,
        public readonly int $imageQuality,
        public readonly int $imageMaxDimension,
    ) {}

    public static function for(string $context, Settings $settings): self
    {
        return new self(
            minPhotos: $settings->int("evidence.{$context}.min_photos"),
            maxPhotos: $settings->int("evidence.{$context}.max_photos"),
            maxVideos: $settings->int("evidence.{$context}.max_videos"),
            videoRequired: $settings->bool("evidence.{$context}.video_required"),
            maxImageKb: $settings->int('evidence.max_image_kb'),
            maxVideoKb: $settings->int('evidence.max_video_kb'),
            maxVideoSeconds: $settings->int('evidence.max_video_seconds'),
            imageMimes: $settings->get('evidence.image_mimes'),
            videoMimes: $settings->get('evidence.video_mimes'),
            imageQuality: $settings->int('evidence.image_quality'),
            imageMaxDimension: $settings->int('evidence.image_max_dimension'),
        );
    }

    /** @return array<string, mixed> for the browser */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
