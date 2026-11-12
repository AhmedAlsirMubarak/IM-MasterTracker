<?php

namespace App\Providers;

use App\Models\SapTracking;
use App\Models\DcTracking;
use App\Observers\SapTrackingObserver;
use App\Observers\DcTrackingObserver;
use Illuminate\Support\ServiceProvider;
use Carbon\Carbon;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('role', \App\Http\Middleware\RoleMiddleware::class);
    }

    public function boot(): void
    {
        SapTracking::observe(SapTrackingObserver::class);
        DcTracking::observe(DcTrackingObserver::class);

        // Record every sign-in, including ones restored from a "remember me" cookie
        Event::listen(Login::class, function (Login $event) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        });

        // Add human-readable timestamps to audit log API responses
        \App\Models\AuditLog::retrieved(function ($log) {
            $log->append('created_at_human');
        });
    }
}
