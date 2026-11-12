<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pins "JCC Actual Date" and "Actual Countdown" right after "Countdown 90 days"
 * in every existing project's master grid, shifting the later columns along.
 */
return new class extends Migration
{
    private array $newColumns = [
        ['source_table' => 'sap_tracking', 'source_column' => 'jcc_actual_date',       'display_label' => 'JCC Actual Date',  'is_locked' => false],
        ['source_table' => 'computed',     'source_column' => 'actual_countdown_days', 'display_label' => 'Actual Countdown', 'is_locked' => true],
    ];

    public function up(): void
    {
        foreach (DB::table('projects')->pluck('id') as $projectId) {
            $missing = array_values(array_filter($this->newColumns, fn ($col) => !DB::table('master_column_configs')
                ->where('project_id', $projectId)
                ->where('source_table', $col['source_table'])
                ->where('source_column', $col['source_column'])
                ->exists()));

            if (!$missing) {
                continue;
            }

            $countdown = DB::table('master_column_configs')
                ->where('project_id', $projectId)
                ->where('source_table', 'computed')
                ->where('source_column', 'countdown_days')
                ->first();

            $after = $countdown?->display_order
                ?? (int) DB::table('master_column_configs')->where('project_id', $projectId)->max('display_order');

            DB::table('master_column_configs')
                ->where('project_id', $projectId)
                ->where('display_order', '>', $after)
                ->increment('display_order', count($missing));

            foreach ($missing as $i => $col) {
                DB::table('master_column_configs')->insert(array_merge($col, [
                    'project_id'    => $projectId,
                    'display_order' => $after + $i + 1,
                    'is_pinned'     => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->newColumns as $col) {
            DB::table('master_column_configs')
                ->where('source_table', $col['source_table'])
                ->where('source_column', $col['source_column'])
                ->delete();
        }
    }
};
