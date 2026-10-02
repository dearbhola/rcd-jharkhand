<?php

namespace App\Domain\Workflow\Guards;

use App\Domain\Gis\GeoMath;
use App\Domain\Reporting\LocationValidator;
use App\Domain\Workflow\Guard;
use App\Domain\Workflow\TransitionContext;
use App\Support\Settings;
use Illuminate\Validation\ValidationException;

/**
 * Physical presence at the report site (field review / repair submission).
 * Authorised users may override in test mode; the action is then flagged.
 */
class WithinSiteRadius implements Guard
{
    public function __construct(
        private readonly Settings $settings,
        private readonly LocationValidator $locations,
        private readonly string $radiusSetting,
        private readonly bool $onlyIfConfigured = false,
    ) {}

    public function check(TransitionContext $ctx): void
    {
        if ($ctx->isSystem()) {
            return;
        }
        if ($this->onlyIfConfigured && ! $this->settings->bool('workflow.validation_requires_location')) {
            return;
        }

        $in = $ctx->input;
        if ($in->locationOverride && $this->locations->overrideAllowed($ctx->actor)) {
            $ctx->flags[] = 'location_override';
            if ($in->hasLocation()) {
                $ctx->distanceFromSiteM = GeoMath::distance($in->latitude, $in->longitude, $ctx->report->latitude, $ctx->report->longitude);
            }

            return;
        }

        if (! $in->hasLocation() || $in->accuracyM === null) {
            throw ValidationException::withMessages(['location' => 'Your GPS location is required for this action. Enable location and try again.']);
        }

        $maxAccuracy = $this->settings->int('gps.max_accuracy_m');
        if ($in->accuracyM > $maxAccuracy) {
            throw ValidationException::withMessages(['location' => sprintf('GPS accuracy is ±%d m; at most ±%d m is required.', (int) round($in->accuracyM), $maxAccuracy)]);
        }

        $radius = $this->settings->int($this->radiusSetting);
        $distance = GeoMath::distance($in->latitude, $in->longitude, $ctx->report->latitude, $ctx->report->longitude);
        $ctx->distanceFromSiteM = $distance;

        if ($distance > $radius) {
            throw ValidationException::withMessages(['location' => sprintf(
                'You are %d m from the reported location. You must be within %d m to do this. Navigate to the site and try again.', (int) round($distance), $radius,
            )]);
        }
    }
}
