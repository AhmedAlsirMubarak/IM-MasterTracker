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
        Schema::create('dc_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_well_id')->constrained('project_wells')->cascadeOnDelete();
 
            $table->string('well_time_document_status')->nullable();
            $table->string('afc_upload_status')->nullable();
            $table->date('final_afc_upload_date')->nullable();
            $table->text('afc_document_remarks')->nullable();
            $table->date('earliest_start_date')->nullable();
            $table->date('earliest_end_date')->nullable();
            $table->string('rlmu_upload_status')->nullable();
            $table->date('final_rlmu_upload_date')->nullable();
            $table->date('asb_receive_date')->nullable();
            $table->string('asb_document_status')->nullable();
            $table->date('asb_document_submission_date')->nullable();
            $table->string('asb_tr_no')->nullable();
            $table->string('document_im_health_check')->nullable();
 
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('dc_tracking');
    }
};
