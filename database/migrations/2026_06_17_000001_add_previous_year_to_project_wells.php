<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_wells', function (Blueprint $table) {
            // Tracks the year a well was in before it was shifted during import
            $table->smallInteger('previous_year')->nullable()->after('year');
        });
    }

    public function down(): void
    {
        Schema::table('project_wells', function (Blueprint $table) {
            $table->dropColumn('previous_year');
        });
    }
};
