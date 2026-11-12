<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking sheets can list the same Well ID on more than one row with different
 * data. Imports now keep each row as its own well (flagged as a duplicate ID),
 * so a Well ID no longer has to be unique within a project.
 *
 * The plain index is added before the unique one is dropped, so the project
 * foreign key always has an index to use.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasIndex('project_wells', 'pw_project_well_id_idx')) {
            Schema::table('project_wells', fn (Blueprint $table) => $table->index(['project_id', 'well_id'], 'pw_project_well_id_idx'));
        }

        if (Schema::hasIndex('project_wells', 'project_wells_project_id_well_id_unique')) {
            Schema::table('project_wells', fn (Blueprint $table) => $table->dropUnique('project_wells_project_id_well_id_unique'));
        }

        Schema::table('import_sessions', function (Blueprint $table) {
            $table->unsignedInteger('duplicate_rows')->default(0)->after('merged_rows');
        });
    }

    public function down(): void
    {
        Schema::table('import_sessions', function (Blueprint $table) {
            $table->dropColumn('duplicate_rows');
        });

        // Fails if a project now holds repeated Well IDs — remove those first.
        Schema::table('project_wells', fn (Blueprint $table) => $table->unique(['project_id', 'well_id']));

        if (Schema::hasIndex('project_wells', 'pw_project_well_id_idx')) {
            Schema::table('project_wells', fn (Blueprint $table) => $table->dropIndex('pw_project_well_id_idx'));
        }
    }
};
