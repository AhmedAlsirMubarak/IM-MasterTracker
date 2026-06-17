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
       // Which columns are pinned to the master view per project
        Schema::create('master_column_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('source_table', ['project_wells', 'sap_tracking', 'dc_tracking', 'computed']);
            $table->string('source_column');
            $table->string('display_label');
            $table->integer('display_order')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false)->comment('Fixed columns cannot be un-pinned');
            $table->foreignId('pinned_by')->nullable()->constrained('users');
            $table->timestamps();
 
            $table->unique(['project_id', 'source_table', 'source_column'], 'mcc_project_source_unique');
        });
 
        // Configurable dropdown options per column
        Schema::create('column_status_options', function (Blueprint $table) {
            $table->id();
            $table->string('source_table');
            $table->string('source_column');
            $table->string('option_value');
            $table->string('option_label');
            $table->string('option_color', 7)->default('#888780')->comment('Hex color for status dot');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
 
        // Full audit trail for every field change
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->foreignId('project_well_id')->nullable()->constrained('project_wells');
            $table->string('field_name');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
 
            $table->index(['model_type', 'model_id']);
            $table->index(['project_well_id', 'field_name']);
            $table->index('user_id');
        });
 
        // Comments per cell — separate from values
        Schema::create('cell_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_well_id')->constrained('project_wells')->cascadeOnDelete();
            $table->string('source_table');
            $table->string('source_column');
            $table->text('comment_text');
            $table->foreignId('commented_by')->constrained('users');
            $table->timestamps();
 
            $table->index(['project_well_id', 'source_table', 'source_column']);
        });
 
        // Excel import sessions
        Schema::create('import_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('original_filename');
            $table->string('stored_path');
            $table->enum('target_table', ['project_wells', 'sap_tracking', 'dc_tracking']);
            $table->enum('status', ['pending', 'mapping', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('column_mapping')->nullable()->comment('{"Excel Col" => "db_column"}');
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('skipped_rows')->default(0);
            $table->text('error_log')->nullable();
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('import_sessions');
        Schema::dropIfExists('cell_comments');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('column_status_options');
        Schema::dropIfExists('master_column_configs');
    }
};