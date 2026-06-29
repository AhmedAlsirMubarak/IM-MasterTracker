<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','manager','sap_admin','dc','im_lead','viewer') NOT NULL DEFAULT 'dc'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','manager','sap_admin','dc','im_lead') NOT NULL DEFAULT 'dc'");
    }
};
