<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $projects = DB::table('projects')->pluck('id');

        foreach ($projects as $projectId) {
            $exists = DB::table('master_column_configs')
                ->where('project_id', $projectId)
                ->where('source_table', 'sap_tracking')
                ->where('source_column', 'station_code')
                ->exists();

            if ($exists) {
                continue;
            }

            $earliestEnd = DB::table('master_column_configs')
                ->where('project_id', $projectId)
                ->where('source_table', 'sap_tracking')
                ->where('source_column', 'earliest_end_date')
                ->first();

            $displayOrder = ($earliestEnd?->display_order ?? 0) + 1;

            DB::table('master_column_configs')->insert([
                'project_id' => $projectId,
                'source_table' => 'sap_tracking',
                'source_column' => 'station_code',
                'display_label' => 'Station Code',
                'display_order' => $displayOrder,
                'is_pinned' => true,
                'is_locked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('master_column_configs')
            ->where('source_table', 'sap_tracking')
            ->where('source_column', 'station_code')
            ->delete();
    }
};
