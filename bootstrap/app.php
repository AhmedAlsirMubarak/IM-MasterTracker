<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role'   => \App\Http\Middleware\RoleMiddleware::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson(),
        );

        // Database errors carry the host, database name and full query. They are
        // still logged, but the user only ever sees a plain message.
        $exceptions->render(function (\Illuminate\Database\QueryException $e, Request $request) {
            $duplicate = ($e->errorInfo[1] ?? null) === 1062;
            $message   = $duplicate
                ? 'This record already exists.'
                : 'Something went wrong while saving. Please try again.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], $duplicate ? 409 : 500);
            }

            return back()->withInput()->withErrors(['general' => $message]);
        });
    })->create();
