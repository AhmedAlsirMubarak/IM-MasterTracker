<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The master grid, year dropdown and shifted counts all filter project_wells by
 * project + year / previous_year and sort by well_number. These indexes keep
 * those queries fast as projects grow.
 */
return new class extends Migration
{
    private array $indexes = [
        'pw_project_year_idx'          => ['project_id', 'year'],
        'pw_project_previous_year_idx' => ['project_id', 'previous_year'],
        'pw_project_well_number_idx'   => ['project_id', 'well_number'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $name => $columns) {
            if (!Schema::hasIndex('project_wells', $name)) {
                Schema::table('project_wells', fn (Blueprint $table) => $table->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->indexes) as $name) {
            if (Schema::hasIndex('project_wells', $name)) {
                Schema::table('project_wells', fn (Blueprint $table) => $table->dropIndex($name));
            }
        }
    }
};
