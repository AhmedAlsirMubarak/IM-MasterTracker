<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE import_sessions MODIFY COLUMN target_table ENUM('project_wells','sap_tracking','dc_tracking','all') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE import_sessions MODIFY COLUMN target_table ENUM('project_wells','sap_tracking','dc_tracking') NOT NULL");
    }
};
