<?php

namespace App\Http\Middleware;

use App\Domain\Auth\LoginService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out users whose account was suspended/deactivated mid-session.
 */
class EnsureAccountIsActive
{
    public function __construct(private readonly LoginService $login) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            $this->login->logout($request);

            return redirect()->route('login')->with('danger', 'Your account is not active.');
        }

        return $next($request);
    }
}
