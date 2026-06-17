<?php

namespace App\Jobs;

use App\Models\ImportSession;
use App\Services\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 min max
    public int $tries   = 1;

    public function __construct(public ImportSession $session) {}

    public function handle(ImportService $importService): void
    {
        ini_set('memory_limit', '512M');
        $importService->execute($this->session);
    }

    public function failed(\Throwable $exception): void
    {
        $this->session->update([
            'status'    => 'failed',
            'error_log' => $exception->getMessage(),
        ]);
    }
}
