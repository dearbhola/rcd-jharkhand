<?php

namespace App\Domain\Reporting;

use App\Domain\Gis\LocationMatch;
use App\Domain\Gis\LocationResolver;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Field-location checks for reports (and later repairs/reviews): GPS accuracy, capture age,
 * and distance to a mapped road. GPS is device-supplied, so the server re-resolves the road
 * itself and records plausibility flags rather than trusting any road/section sent by the client.
 */
class LocationValidator
{
    public const OUTSIDE_AREA = 'You are not currently within the permitted reporting area.';

    /** Radius used to find "the nearest road" when an authorised user overrides location in test mode. */
    private const OVERRIDE_SEARCH_M = 5000;

    public function __construct(
        private readonly LocationResolver $locator,
        private readonly Settings $settings,
    ) {}

    /**
     * @return array{match: LocationMatch, flags: list<string>, override: bool}
     */
    public function validateReport(User $user, float $lat, float $lng, ?float $accuracy, Carbon $capturedAt, bool $override = false): array
    {
        $flags = $this->checkFix($accuracy, $capturedAt);
        $override = $override && $this->overrideAllowed($user);

        $radius = $this->settings->int('report.location_radius_m');
        $match = $this->locator->nearest($lat, $lng, $override ? self::OVERRIDE_SEARCH_M : $radius, $radius);

        if (! $match) {
            throw ValidationException::withMessages(['location' => self::OUTSIDE_AREA]);
        }
        if ($override) {
            $flags[] = 'location_override';
        }

        return ['match' => $match, 'flags' => $flags, 'override' => $override];
    }

    public function overrideAllowed(User $user): bool
    {
        return $this->settings->bool('location.test_override_enabled') && $user->can('report.location_override');
    }

    /** @return list<string> */
    private function checkFix(?float $accuracy, Carbon $capturedAt): array
    {
        $maxAccuracy = $this->settings->int('gps.max_accuracy_m');
        if ($accuracy === null) {
            throw ValidationException::withMessages(['accuracy' => 'GPS accuracy is missing. Enable precise location and try again.']);
        }
        if ($accuracy > $maxAccuracy) {
            throw ValidationException::withMessages(['accuracy' => sprintf(
                'GPS accuracy is ±%d m; at most ±%d m is required. Move to open sky and wait for a better fix.', (int) round($accuracy), $maxAccuracy,
            )]);
        }

        if ($capturedAt->gt(now()->addMinutes(5))) {
            throw ValidationException::withMessages(['captured_at' => 'The capture time is in the future. Check the device clock.']);
        }
        $maxAgeHours = $this->settings->int('gps.max_capture_age_hours');
        if ($capturedAt->lt(now()->subHours($maxAgeHours))) {
            throw ValidationException::withMessages(['captured_at' => "Evidence older than {$maxAgeHours} hours cannot be submitted."]);
        }

        return $capturedAt->lt(now()->subMinutes(30)) ? ['delayed_submission'] : [];
    }
}
