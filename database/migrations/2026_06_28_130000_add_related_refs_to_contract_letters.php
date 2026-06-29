<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_letters', function (Blueprint $table) {
            $table->json('related_refs')->nullable()->after('reply_ref_no');
        });
    }

    public function down(): void
    {
        Schema::table('contract_letters', function (Blueprint $table) {
            $table->dropColumn('related_refs');
        });
    }
};
