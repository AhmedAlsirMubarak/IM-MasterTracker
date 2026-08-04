<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractCv;
use Illuminate\Http\Request;

class CvLetterController extends Controller
{
    private function format(ContractCv $cv): array
    {
        return [
            'id'                => $cv->id,
            'contract_id'       => $cv->contract_id,
            'ctp_number'        => $cv->ctp_number,
            'reference_no'      => $cv->reference_no,
            'submission_date'   => $cv->submission_date->toDateString(),
            'description'       => $cv->description,
            'direction'         => $cv->direction,
            'reply_ref_no'      => $cv->reply_ref_no,
            'related_refs'      => $cv->related_refs ?? [],
            'person_name'       => $cv->person_name,
            'position'          => $cv->position,
            'approval_status'   => $cv->approval_status,
            'validity_value'    => $cv->validity_value,
            'validity_unit'     => $cv->validity_unit,
            'expiry_date'       => $cv->expiry_date?->toDateString(),
            'days_remaining'    => $cv->days_remaining,
            'countdown_status'  => $cv->countdown_status,
            'remarks'           => $cv->remarks,
            'hde_remarks'       => $cv->hde_remarks,
            'created_by'        => $cv->createdBy ? ['id' => $cv->createdBy->id, 'name' => $cv->createdBy->name] : null,
        ];
    }

    private function rules(): array
    {
        return [
            'ctp_number'       => 'nullable|string|max:50',
            'reference_no'     => 'required|string|max:200',
            'submission_date'  => 'required|date',
            'description'      => 'nullable|string',
            'direction'        => 'required|in:outgoing,incoming',
            'reply_ref_no'     => 'nullable|string|max:200',
            'related_refs'     => 'nullable|array',
            'related_refs.*'   => 'string|max:200',
            'person_name'      => 'required|string|max:150',
            'position'         => 'nullable|string|max:150',
            'approval_status'  => 'in:pending,approved_temporary,approved_final,rejected',
            'validity_value'   => 'nullable|integer|min:1|required_if:approval_status,approved_temporary',
            'validity_unit'    => 'nullable|in:days,months|required_if:approval_status,approved_temporary',
            'remarks'          => 'nullable|string',
            'hde_remarks'      => 'nullable|string',
        ];
    }

    public function index(Contract $contract)
    {
        $cvs = $contract->cvs()
            ->with('createdBy:id,name')
            ->orderByDesc('submission_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn($c) => $this->format($c));

        return response()->json($cvs);
    }

    public function store(Request $request, Contract $contract)
    {
        $data = $request->validate($this->rules());

        if (($data['approval_status'] ?? 'pending') !== 'approved_temporary') {
            $data['validity_value'] = null;
            $data['validity_unit']  = null;
        }

        $cv = $contract->cvs()->create(array_merge($data, ['created_by' => auth()->id()]));
        $cv->load('createdBy:id,name');

        return response()->json($this->format($cv), 201);
    }

    public function update(Request $request, Contract $contract, ContractCv $cv)
    {
        abort_if($cv->contract_id !== $contract->id, 404);

        $rules = $this->rules();
        $rules['reference_no']    = 'sometimes|string|max:200';
        $rules['submission_date'] = 'sometimes|date';
        $rules['direction']       = 'sometimes|in:outgoing,incoming';
        $rules['person_name']     = 'sometimes|string|max:150';

        $data = $request->validate($rules);

        $status = $data['approval_status'] ?? $cv->approval_status;
        if ($status !== 'approved_temporary') {
            $data['validity_value'] = null;
            $data['validity_unit']  = null;
        }

        $cv->update($data);
        $cv->load('createdBy:id,name');

        return response()->json($this->format($cv));
    }

    public function destroy(Contract $contract, ContractCv $cv)
    {
        abort_if($cv->contract_id !== $contract->id, 404);
        $cv->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    public function alerts(Contract $contract)
    {
        $alerts = $contract->cvs()
            ->where('approval_status', 'approved_temporary')
            ->get()
            ->filter(fn($c) => in_array($c->countdown_status, ['yellow', 'red'], true))
            ->map(fn($c) => $this->format($c))
            ->values();

        return response()->json(['count' => $alerts->count(), 'cvs' => $alerts]);
    }

    public function export(Contract $contract)
    {
        $cvs = $contract->cvs()
            ->with('createdBy:id,name')
            ->orderByDesc('submission_date')
            ->orderByDesc('id')
            ->get();

        $headers = [
            'Direction', 'CTP Number', 'Reference No', 'Submission Date', 'Description',
            'Name', 'Position', 'Reply Ref No', 'Approval Status', 'Validity',
            'Expiry Date', 'Days Remaining', 'Remarks-PDO-HDE', 'HDE Remarks', 'Created By',
        ];

        $rows = [$headers];

        foreach ($cvs as $c) {
            $rows[] = [
                $c->direction === 'outgoing' ? 'Sent to PDO' : 'From PDO',
                $c->ctp_number,
                $c->reference_no,
                $c->submission_date->toDateString(),
                $c->description,
                $c->person_name,
                $c->position,
                $c->reply_ref_no,
                $c->approval_status,
                $c->validity_value ? $c->validity_value . ' ' . $c->validity_unit : null,
                $c->expiry_date?->toDateString(),
                $c->days_remaining,
                $c->remarks,
                $c->hde_remarks,
                $c->createdBy?->name,
            ];
        }

        $filename = 'cvs-' . str($contract->name)->slug() . '-' . now()->format('Ymd') . '.csv';

        return response($this->toCsv($rows), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function toCsv(array $rows): string
    {
        $fp = fopen('php://memory', 'r+');
        foreach ($rows as $row) {
            fputcsv($fp, array_map(fn($v) => $v ?? '', $row));
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return $csv;
    }
}
