<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard: `permission:road.view` or `permission:report.view|report.view_all` (any of).
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();

        abort_unless($user && collect(explode('|', $permissions))->contains(fn ($p) => $user->can($p)), 403);

        return $next($request);
    }
}
