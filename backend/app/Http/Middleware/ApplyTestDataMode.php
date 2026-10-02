<?php

namespace App\Http\Middleware;

use App\Support\TestDataMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the user's "include test data" choice. Users without the permission
 * always get the environment default (exclude in production).
 */
class ApplyTestDataMode
{
    public const SESSION_KEY = 'include_test_data';

    public function __construct(private readonly TestDataMode $mode) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $request->hasSession() && $request->session()->has(self::SESSION_KEY) && $user->can('testdata.include')) {
            $this->mode->include((bool) $request->session()->get(self::SESSION_KEY));
        } elseif ($user && ! $user->can('testdata.include') && app()->isProduction()) {
            $this->mode->include(false);
        }

        return $next($request);
    }
}
