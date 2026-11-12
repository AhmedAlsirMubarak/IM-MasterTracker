<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sap_tracking', function (Blueprint $table) {
            // "JCC Actual Date Approved by PDO" — drives the Actual Countdown (JCC + 90 − today)
            $table->date('jcc_actual_date')->nullable()->after('commissioning_date');
        });
    }

    public function down(): void
    {
        Schema::table('sap_tracking', function (Blueprint $table) {
            $table->dropColumn('jcc_actual_date');
        });
    }
};
