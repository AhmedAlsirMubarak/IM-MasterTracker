<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create contracts table (mirrors old projects concept)
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contract_no', 100)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'archived', 'closed'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Add contract_id to projects (nullable first so we can populate)
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('contract_id')->nullable()->after('id')->constrained('contracts')->cascadeOnDelete();
        });

        // 3. Seed: create one contract per existing project and link them
        $projects = DB::table('projects')->whereNull('deleted_at')->get();
        foreach ($projects as $project) {
            $contractId = DB::table('contracts')->insertGetId([
                'name'        => $project->name,
                'contract_no' => $project->contract_no,
                'description' => $project->description,
                'status'      => $project->status ?? 'active',
                'created_by'  => $project->created_by,
                'created_at'  => $project->created_at,
                'updated_at'  => $project->updated_at,
            ]);
            DB::table('projects')->where('id', $project->id)->update(['contract_id' => $contractId]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['contract_id']);
            $table->dropColumn('contract_id');
        });
        Schema::dropIfExists('contracts');
    }
};
