<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Import results in wells (not just rows), plus warnings kept apart from errors:
 * a warning means the row was imported with a cell left blank or a duplicate merged;
 * an error means the row was not imported.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_sessions', function (Blueprint $table) {
            $table->unsignedInteger('created_wells')->default(0)->after('skipped_rows');
            $table->unsignedInteger('updated_wells')->default(0)->after('created_wells');
            $table->unsignedInteger('merged_rows')->default(0)->after('updated_wells');
            $table->text('warning_log')->nullable()->after('error_log');
        });
    }

    public function down(): void
    {
        Schema::table('import_sessions', function (Blueprint $table) {
            $table->dropColumn(['created_wells', 'updated_wells', 'merged_rows', 'warning_log']);
        });
    }
};
