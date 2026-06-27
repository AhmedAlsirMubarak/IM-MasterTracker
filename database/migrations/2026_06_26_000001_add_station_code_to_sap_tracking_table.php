<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sap_tracking', function (Blueprint $table) {
            if (!Schema::hasColumn('sap_tracking', 'station_code')) {
                $table->string('station_code')->nullable()->after('earliest_end_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sap_tracking', function (Blueprint $table) {
            if (Schema::hasColumn('sap_tracking', 'station_code')) {
                $table->dropColumn('station_code');
            }
        });
    }
};
