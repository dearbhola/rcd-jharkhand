<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for features switched by a boolean system setting: `feature:auth.sms_otp_enabled`.
 * Disabled features answer 404 so they are invisible.
 */
class EnsureFeatureEnabled
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(Request $request, Closure $next, string $settingKey): Response
    {
        abort_unless((bool) $this->settings->get($settingKey, false), 404);

        return $next($request);
    }
}
