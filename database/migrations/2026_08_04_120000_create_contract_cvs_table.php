<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_cvs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('ctp_number', 50)->nullable();
            $table->string('letter_no', 200);
            $table->date('sent_date');
            $table->text('description')->nullable();
            $table->string('person_name', 150);
            $table->string('position', 150)->nullable();
            $table->string('reply_letter_no', 200)->nullable();
            $table->date('reply_date')->nullable();
            $table->enum('approval_status', ['pending', 'approved_temporary', 'approved_final', 'rejected'])->default('pending');
            $table->unsignedInteger('validity_value')->nullable();
            $table->enum('validity_unit', ['days', 'months'])->nullable();
            $table->text('remarks')->nullable();
            $table->text('hde_remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('contract_id');
            $table->index('letter_no');
            $table->index('approval_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_cvs');
    }
};
