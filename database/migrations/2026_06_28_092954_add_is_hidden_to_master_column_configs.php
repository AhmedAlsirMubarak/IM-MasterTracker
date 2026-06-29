<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_column_configs', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('is_locked');
        });
    }

    public function down(): void
    {
        Schema::table('master_column_configs', function (Blueprint $table) {
            $table->dropColumn('is_hidden');
        });
    }
};
