<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Extend the enum to include 'custom'
        DB::statement("ALTER TABLE master_column_configs MODIFY COLUMN source_table ENUM('project_wells','sap_tracking','dc_tracking','computed','custom') NOT NULL");

        Schema::table('master_column_configs', function (Blueprint $table) {
            $table->string('column_type', 20)->nullable()->after('display_label');
        });
    }

    public function down(): void
    {
        Schema::table('master_column_configs', function (Blueprint $table) {
            $table->dropColumn('column_type');
        });

        DB::statement("ALTER TABLE master_column_configs MODIFY COLUMN source_table ENUM('project_wells','sap_tracking','dc_tracking','computed') NOT NULL");
    }
};
