<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('project_wells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
 
            // Fixed columns — Super Admin only
            $table->string('contract_no')->nullable();
            $table->string('scope')->nullable();
            $table->date('hua_date')->nullable();
            $table->string('well_id')->nullable()->index();
            $table->string('well_number')->nullable();
            $table->string('well_type')->nullable();
            $table->string('ramz_id')->nullable();
            $table->year('year')->nullable();
            $table->string('responsible_asset')->nullable();
 
            // Master-level columns — Manager editable
            $table->string('cims_upload_status')->nullable();
            $table->string('spir_status')->nullable();
            $table->string('asb_edw_status')->nullable();
            $table->string('e_pad_closeout')->nullable();
            $table->string('rlmu_status')->nullable();
 
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
 
            $table->unique(['project_id', 'well_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_wells');
    }
};
