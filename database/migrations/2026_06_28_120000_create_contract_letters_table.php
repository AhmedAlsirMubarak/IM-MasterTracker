<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('ctp_number', 50)->nullable();
            $table->string('reference_no', 200);
            $table->date('submission_date');
            $table->text('description')->nullable();
            $table->enum('direction', ['outgoing', 'incoming'])->default('outgoing');
            $table->string('reply_ref_no', 200)->nullable();
            $table->boolean('required_action')->default(false);
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contract_id', 'direction']);
            $table->index('reference_no');
            $table->index('reply_ref_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_letters');
    }
};
