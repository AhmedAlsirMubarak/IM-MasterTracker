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
        Schema::create('sap_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_well_id')->constrained('project_wells')->cascadeOnDelete();
 
            // Dates
            $table->date('earliest_start_date')->nullable();
            $table->date('earliest_end_date')->nullable();
            $table->date('hook_up_tie_in_completed')->nullable();
            $table->date('commissioning_date')->nullable();
            $table->date('z6_creation_date')->nullable();
            $table->date('z6_awap_date')->nullable();
            $table->date('z6_appr_date')->nullable();
            $table->date('sap_upload_date')->nullable();
            $table->date('bom_linking_date')->nullable();
            $table->date('afc_submitted_date')->nullable();
            $table->date('afc_edw_completed_date')->nullable();
            $table->date('asb_edw_sent_date')->nullable();
            $table->date('inst_manufacturer_date')->nullable();
            $table->date('mech_manufacturer_date')->nullable();
            $table->date('rv_mfr_details_date')->nullable();
            $table->date('esp_req_date')->nullable();
 
            // Statuses & identifiers
            $table->string('z6_number')->nullable();
            $table->string('sap_status')->nullable();
            $table->string('upload_status')->nullable();
            $table->string('afc_document_spo_status')->nullable();
            $table->string('afc_edw_status')->nullable();
            $table->string('asb_edw_status')->nullable();
            $table->string('asb_edw_transmittal')->nullable();
            $table->string('bom_linking_status')->nullable();
            $table->string('cims_document_number')->nullable();
            $table->string('cims_upload_status')->nullable();
            $table->string('cims_transmittal_no')->nullable();
            $table->string('spir_status')->nullable();
            $table->string('im_health_check_tags')->nullable();
            $table->string('asset_register_report')->nullable();
            $table->string('sap_screenshot')->nullable();
            $table->string('elec_isolator')->nullable();
            $table->string('db_field')->nullable();
            $table->text('edw_remark')->nullable();
 
            // Computed / cached
            $table->integer('sap_kpi')->nullable()->comment('Computed: earliest_end_date - earliest_start_date in days');
 
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('sap_tracking');
    }
};
