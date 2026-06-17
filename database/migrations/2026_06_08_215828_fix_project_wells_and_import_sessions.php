<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // created_by was NOT NULL with no default — bulk imports have no live user context
        Schema::table('project_wells', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->change();
        });

        // TEXT (65 KB) overflows when logging errors for large imports; use LONGTEXT
        Schema::table('import_sessions', function (Blueprint $table) {
            $table->longText('error_log')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_wells', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable(false)->change();
        });

        Schema::table('import_sessions', function (Blueprint $table) {
            $table->text('error_log')->nullable()->change();
        });
    }
};
