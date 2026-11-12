<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit rows written through AuditLog::create() never set created_at (it was not
 * fillable), so MySQL filled it with its own local time instead of UTC. Those rows
 * are the ones whose model_type is a plain table name rather than a model class.
 * Shift them back to UTC so every audit row uses the same clock.
 */
return new class extends Migration
{
    private array $types = ['project_wells', 'custom', 'import_session', 'sap_tracking', 'dc_tracking'];

    public function up(): void
    {
        $this->shift(-1);
    }

    public function down(): void
    {
        $this->shift(1);
    }

    private function shift(int $direction): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        $offset = (int) DB::selectOne('SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS s')->s;
        if ($offset === 0) return;

        DB::table('audit_logs')
            ->whereIn('model_type', $this->types)
            ->update(['created_at' => DB::raw('created_at + INTERVAL ' . ($direction * $offset) . ' SECOND')]);
    }
};
