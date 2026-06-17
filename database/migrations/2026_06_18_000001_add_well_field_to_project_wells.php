<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_wells', function (Blueprint $table) {
            $table->string('well_field')->nullable()->after('well_type');
        });

        // Shift everything with display_order > 6 up by 1 to make room for well_field at order 7.
        // (well_location was at 7 and will move to 8, etc.)
        DB::table('master_column_configs')
            ->where('display_order', '>', 6)
            ->increment('display_order');

        $now      = now();
        $projects = DB::table('master_column_configs')->distinct()->pluck('project_id');

        $rows = $projects->map(fn($pid) => [
            'project_id'    => $pid,
            'source_table'  => 'project_wells',
            'source_column' => 'well_field',
            'display_label' => 'Field',
            'display_order' => 7,
            'is_pinned'     => true,
            'is_locked'     => true,
            'created_at'    => $now,
            'updated_at'    => $now,
        ])->all();

        if (!empty($rows)) {
            DB::table('master_column_configs')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        DB::table('master_column_configs')
            ->where('source_column', 'well_field')
            ->where('source_table', 'project_wells')
            ->delete();

        DB::table('master_column_configs')
            ->where('display_order', '>', 7)
            ->decrement('display_order');

        Schema::table('project_wells', function (Blueprint $table) {
            $table->dropColumn('well_field');
        });
    }
};
