<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_cvs', function (Blueprint $table) {
            $table->string('reference_no', 200)->nullable()->after('ctp_number');
            $table->date('submission_date')->nullable()->after('reference_no');
            $table->enum('direction', ['outgoing', 'incoming'])->default('outgoing')->after('submission_date');
            $table->string('reply_ref_no', 200)->nullable()->after('direction');
            $table->json('related_refs')->nullable()->after('reply_ref_no');
        });

        // Migrate existing rows: the old single-row shape (letter_no + reply_letter_no)
        // becomes an outgoing letter, and — if it already had a reply — a linked incoming reply row.
        foreach (DB::table('contract_cvs')->get() as $row) {
            DB::table('contract_cvs')->where('id', $row->id)->update([
                'reference_no'    => $row->letter_no,
                'submission_date' => $row->sent_date,
                'direction'       => 'outgoing',
                'approval_status' => 'pending',
                'validity_value'  => null,
                'validity_unit'   => null,
            ]);

            if ($row->reply_letter_no) {
                DB::table('contract_cvs')->insert([
                    'contract_id'      => $row->contract_id,
                    'ctp_number'       => $row->ctp_number,
                    // these two still have NOT NULL constraints until the column drop below
                    'letter_no'        => $row->reply_letter_no,
                    'sent_date'        => $row->reply_date ?? $row->sent_date,
                    'reference_no'     => $row->reply_letter_no,
                    'submission_date'  => $row->reply_date ?? $row->sent_date,
                    'description'      => $row->description,
                    'direction'        => 'incoming',
                    'reply_ref_no'     => $row->letter_no,
                    'person_name'      => $row->person_name,
                    'position'         => $row->position,
                    'approval_status'  => $row->approval_status,
                    'validity_value'   => $row->validity_value,
                    'validity_unit'    => $row->validity_unit,
                    'remarks'          => $row->remarks,
                    'hde_remarks'      => $row->hde_remarks,
                    'created_by'       => $row->created_by,
                    'created_at'       => $row->created_at,
                    'updated_at'       => $row->updated_at,
                ]);
            }
        }

        Schema::table('contract_cvs', function (Blueprint $table) {
            $table->string('reference_no', 200)->nullable(false)->change();
            $table->date('submission_date')->nullable(false)->change();
            $table->dropColumn(['letter_no', 'sent_date', 'reply_letter_no', 'reply_date']);
            $table->index('reference_no');
            $table->index('reply_ref_no');
            $table->index(['contract_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::table('contract_cvs', function (Blueprint $table) {
            $table->string('letter_no', 200)->nullable()->after('ctp_number');
            $table->date('sent_date')->nullable()->after('letter_no');
            $table->string('reply_letter_no', 200)->nullable()->after('position');
            $table->date('reply_date')->nullable()->after('reply_letter_no');
        });

        foreach (DB::table('contract_cvs')->where('direction', 'outgoing')->get() as $row) {
            $reply = DB::table('contract_cvs')->where('reply_ref_no', $row->reference_no)->first();

            DB::table('contract_cvs')->where('id', $row->id)->update([
                'letter_no'       => $row->reference_no,
                'sent_date'       => $row->submission_date,
                'reply_letter_no' => $reply->reference_no ?? null,
                'reply_date'      => $reply->submission_date ?? null,
                'approval_status' => $reply->approval_status ?? 'pending',
                'validity_value'  => $reply->validity_value ?? null,
                'validity_unit'   => $reply->validity_unit ?? null,
                'remarks'         => $reply->remarks ?? $row->remarks,
                'hde_remarks'     => $reply->hde_remarks ?? $row->hde_remarks,
            ]);
        }

        DB::table('contract_cvs')->where('direction', 'incoming')->delete();

        Schema::table('contract_cvs', function (Blueprint $table) {
            $table->dropColumn(['reference_no', 'submission_date', 'direction', 'reply_ref_no', 'related_refs']);
        });
    }
};
