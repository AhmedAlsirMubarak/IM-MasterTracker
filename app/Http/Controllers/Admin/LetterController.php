<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractLetter;
use Illuminate\Http\Request;

class LetterController extends Controller
{
    private function format(ContractLetter $letter): array
    {
        $elapsed   = (int) max(0, $letter->submission_date->diffInDays(now(), false));
        $status    = $elapsed >= 14 ? 'red' : ($elapsed >= 10 ? 'yellow' : 'green');

        return [
            'id'              => $letter->id,
            'contract_id'     => $letter->contract_id,
            'ctp_number'      => $letter->ctp_number,
            'reference_no'    => $letter->reference_no,
            'submission_date' => $letter->submission_date->toDateString(),
            'description'     => $letter->description,
            'direction'       => $letter->direction,
            'reply_ref_no'    => $letter->reply_ref_no,
            'related_refs'    => $letter->related_refs ?? [],
            'required_action' => $letter->required_action,
            'status'          => $letter->status,
            'remarks'         => $letter->remarks,
            'days_elapsed'    => $elapsed,
            'days_remaining'  => 14 - $elapsed,
            'countdown_status'=> $status,
            'created_by'      => $letter->createdBy ? ['id' => $letter->createdBy->id, 'name' => $letter->createdBy->name] : null,
        ];
    }

    public function index(Contract $contract)
    {
        $letters = $contract->letters()
            ->with('createdBy:id,name')
            ->orderByDesc('submission_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn($l) => $this->format($l));

        return response()->json($letters);
    }

    public function store(Request $request, Contract $contract)
    {
        $data = $request->validate([
            'ctp_number'      => 'nullable|string|max:50',
            'reference_no'    => 'required|string|max:200',
            'submission_date' => 'required|date',
            'description'     => 'nullable|string',
            'direction'       => 'required|in:outgoing,incoming',
            'reply_ref_no'    => 'nullable|string|max:200',
            'related_refs'    => 'nullable|array',
            'related_refs.*'  => 'string|max:200',
            'required_action' => 'boolean',
            'status'          => 'in:open,closed',
            'remarks'         => 'nullable|string',
        ]);

        $letter = $contract->letters()->create(array_merge($data, ['created_by' => auth()->id()]));
        $letter->load('createdBy:id,name');

        return response()->json($this->format($letter), 201);
    }

    public function update(Request $request, Contract $contract, ContractLetter $letter)
    {
        abort_if($letter->contract_id !== $contract->id, 404);

        $data = $request->validate([
            'ctp_number'      => 'nullable|string|max:50',
            'reference_no'    => 'sometimes|string|max:200',
            'submission_date' => 'sometimes|date',
            'description'     => 'nullable|string',
            'direction'       => 'sometimes|in:outgoing,incoming',
            'reply_ref_no'    => 'nullable|string|max:200',
            'related_refs'    => 'nullable|array',
            'related_refs.*'  => 'string|max:200',
            'required_action' => 'boolean',
            'status'          => 'in:open,closed',
            'remarks'         => 'nullable|string',
        ]);

        $letter->update($data);
        $letter->load('createdBy:id,name');

        return response()->json($this->format($letter));
    }

    public function destroy(Contract $contract, ContractLetter $letter)
    {
        abort_if($letter->contract_id !== $contract->id, 404);
        $letter->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    public function alerts(Contract $contract)
    {
        $alerts = $contract->letters()
            ->where('status', 'open')
            ->where('direction', 'outgoing')
            ->whereDate('submission_date', '<=', now()->subDays(10)->toDateString())
            ->with('createdBy:id,name')
            ->get()
            ->map(fn($l) => $this->format($l))
            ->values();

        return response()->json(['count' => $alerts->count(), 'letters' => $alerts]);
    }

    public function export(Contract $contract)
    {
        $letters = $contract->letters()
            ->with('createdBy:id,name')
            ->orderByDesc('submission_date')
            ->orderByDesc('id')
            ->get();

        $headers = [
            'Direction', 'CTP Number', 'Reference No', 'Submission Date', 'Description',
            'Reply Ref No', 'Related Refs', 'Required Action', 'Status', 'Remarks',
            'Days Elapsed', 'Created By',
        ];

        $rows = [$headers];

        foreach ($letters as $l) {
            $elapsed = (int) max(0, $l->submission_date->diffInDays(now(), false));

            $rows[] = [
                $l->direction === 'outgoing' ? 'Sent to PDO' : 'From PDO',
                $l->ctp_number,
                $l->reference_no,
                $l->submission_date->toDateString(),
                $l->description,
                $l->reply_ref_no,
                implode(', ', $l->related_refs ?? []),
                $l->required_action ? 'Yes' : 'No',
                $l->status,
                $l->remarks,
                $elapsed,
                $l->createdBy?->name,
            ];
        }

        $filename = 'letters-' . str($contract->name)->slug() . '-' . now()->format('Ymd') . '.csv';

        return response($this->toCsv($rows), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function toCsv(array $rows): string
    {
        $fp = fopen('php://memory', 'r+');
        foreach ($rows as $row) {
            fputcsv($fp, array_map([\App\Support\Csv::class, 'cell'], $row));
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return $csv;
    }
}
