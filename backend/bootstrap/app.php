<?php

use App\Domain\Workflow\WorkflowConflict;
use App\Http\Middleware\ApplyTestDataMode;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsurePasswordIsCurrent;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            ApplyTestDataMode::class,
        ]);

        // Test-data visibility must be decided before route-model binding loads records.
        $middleware->prependToPriorityList(SubstituteBindings::class, ApplyTestDataMode::class);

        $middleware->alias([
            'permission' => EnsurePermission::class,
            'active' => EnsureAccountIsActive::class,
            'password.current' => EnsurePasswordIsCurrent::class,
            'feature' => EnsureFeatureEnabled::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Another user acted on the task first (optimistic concurrency).
        $exceptions->render(function (WorkflowConflict $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 409)
                : back()->with('danger', $e->getMessage());
        });
    })->create();
