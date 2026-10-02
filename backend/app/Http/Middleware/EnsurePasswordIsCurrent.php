<?php

namespace App\Http\Middleware;

use App\Domain\Auth\PasswordPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces staff to change an administrator-set or expired password before continuing.
 */
class EnsurePasswordIsCurrent
{
    public function __construct(private readonly PasswordPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $request->routeIs('password.change', 'password.change.update', 'logout') && $this->policy->mustChange($user)) {
            return redirect()->route('password.change')->with('warning', 'Please set a new password to continue.');
        }

        return $next($request);
    }
}
