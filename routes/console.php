<?php

use App\Models\ImportSession;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('imports:prune {--hours=24 : Expire uploads left in the mapping step longer than this}', function () {
    $hours = (int) $this->option('hours');

    // Uploads the user never confirmed
    $abandoned = ImportSession::where('status', 'mapping')
        ->where('created_at', '<', now()->subHours($hours))
        ->get();

    foreach ($abandoned as $session) {
        $session->update([
            'status'    => 'failed',
            'error_log' => 'Abandoned — file expired',
        ]);
    }

    // Any file not needed by a session that is still in progress
    $inUse = ImportSession::whereIn('status', ['mapping', 'pending', 'processing'])
        ->pluck('stored_path')
        ->all();

    $deleted = 0;
    $bytes   = 0;
    foreach (Storage::files('imports') as $path) {
        // Skip in-use files and anything uploaded in the last hour
        if (in_array($path, $inUse, true) || Storage::lastModified($path) > now()->subHour()->timestamp) {
            continue;
        }
        $bytes += Storage::size($path);
        Storage::delete($path);
        $deleted++;
    }

    $this->info(sprintf(
        'Expired %d abandoned session(s); deleted %d file(s), freed %.1f MB.',
        $abandoned->count(), $deleted, $bytes / 1048576
    ));
})->purpose('Delete leftover import files');

Schedule::command('imports:prune')->daily();

// spatie/laravel-backup: clean old backups first, then take a fresh DB-only backup, then check health.
Schedule::command('backup:clean')->daily()->at('01:30');
Schedule::command('backup:run --only-db')->daily()->at('02:00');
Schedule::command('backup:monitor')->daily()->at('03:00');
