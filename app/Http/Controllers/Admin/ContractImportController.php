<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ContractImportController extends Controller
{
    /** Download a blank CSV template with the expected columns and an example row */
    public function template(Request $request, Contract $contract)
    {
        $request->validate(['type' => 'required|in:letters,cvs']);

        $type    = $request->input('type');
        $headers = array_map(fn($f) => $f['label'], $this->fieldsFor($type));
        $rows    = [$headers, $this->exampleRow($type)];

        return response($this->toCsv($rows), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $type . '-import-template.csv"',
        ]);
    }

    /** Upload a Letters/CVs Excel or CSV file — returns headers, a sample, and a suggested column mapping */
    public function upload(Request $request, Contract $contract)
    {
        $request->validate([
            'type' => 'required|in:letters,cvs',
            'file' => 'required|file|extensions:xlsx,xls,csv|max:10240',
        ]);

        $type         = $request->input('type');
        $uploadedFile = $request->file('file');
        $ext          = strtolower($uploadedFile->getClientOriginalExtension());
        $filename     = Str::uuid() . '.' . $ext;
        $destDir      = storage_path('app/private/imports');

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $uploadedFile->move($destDir, $filename);

        $rows = $this->readRows(storage_path('app/private/imports/' . $filename));

        if (empty($rows)) {
            Storage::delete('imports/' . $filename);
            return response()->json(['message' => 'The file appears to be empty.'], 422);
        }

        $headers    = array_map(fn($h) => trim((string) $h), $rows[0]);
        $sampleRows = array_slice($rows, 1, 5);

        return response()->json([
            'token'       => $filename,
            'headers'     => $headers,
            'sample_rows' => $sampleRows,
            'fields'      => $this->fieldsFor($type),
            'total_rows'  => count($rows) - 1,
        ]);
    }

    /** Apply the confirmed column mapping and create the letter/CV records */
    public function confirm(Request $request, Contract $contract)
    {
        $data = $request->validate([
            'type'    => 'required|in:letters,cvs',
            'token'   => ['required', 'string', 'regex:/^[0-9a-fA-F-]{36}\.(xlsx|xls|csv)$/'],
            'mapping' => 'required|array',
        ]);

        $type    = $data['type'];
        $relPath = 'imports/' . $data['token'];

        if (!Storage::exists($relPath)) {
            return response()->json(['message' => 'Upload session expired. Please re-upload the file.'], 422);
        }

        $rows = $this->readRows(Storage::path($relPath));
        Storage::delete($relPath);

        $mapping  = $request->input('mapping', []);
        $rules    = $type === 'cvs' ? $this->cvRules() : $this->letterRules();
        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        foreach (array_slice($rows, 1) as $i => $row) {
            $rowNum = $i + 2;

            if (empty(array_filter($row, fn($v) => $v !== null && trim((string) $v) !== ''))) {
                continue;
            }

            $record    = $this->mapRow($row, $mapping, $type);
            $validator = Validator::make($record, $rules);

            if ($validator->fails()) {
                $skipped++;
                if (count($errors) < 50) {
                    $errors[] = ['row' => $rowNum, 'message' => $validator->errors()->first()];
                }
                continue;
            }

            $record['created_by'] = auth()->id();

            if ($type === 'cvs') {
                if (($record['approval_status'] ?? 'pending') !== 'approved_temporary') {
                    $record['validity_value'] = null;
                    $record['validity_unit']  = null;
                }
                $contract->cvs()->create($record);
            } else {
                $contract->letters()->create($record);
            }

            $imported++;
        }

        return response()->json(compact('imported', 'skipped', 'errors'));
    }

    private function readRows(string $fullPath): array
    {
        $spreadsheet = IOFactory::load($fullPath);
        $rows        = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    /** Field definitions sent to the client, which runs the same normalize+alias auto-map algorithm as the project import wizard */
    private function fieldsFor(string $type): array
    {
        $common = [
            ['value' => 'reference_no',    'label' => 'Reference No',                   'required' => true,  'aliases' => ['referenceno', 'refno', 'reference', 'ref', 'letterno', 'letternumber']],
            ['value' => 'submission_date', 'label' => 'Submission Date',                'required' => true,  'aliases' => ['submissiondate', 'date', 'sentdate', 'senddate', 'letterdate']],
            ['value' => 'direction',       'label' => 'Direction (outgoing/incoming)',  'required' => true,  'aliases' => ['direction', 'type']],
            ['value' => 'ctp_number',      'label' => 'CTP Number',                     'required' => false, 'aliases' => ['ctpnumber', 'ctp', 'ctpno']],
            ['value' => 'description',     'label' => 'Description',                    'required' => false, 'aliases' => ['description', 'subject']],
            ['value' => 'reply_ref_no',    'label' => 'Reply Ref No',                    'required' => false, 'aliases' => ['replyrefno', 'replyref', 'replyreference', 'replyletterno']],
            ['value' => 'related_refs',    'label' => 'Related Refs (comma-separated)', 'required' => false, 'aliases' => ['relatedrefs', 'related']],
            ['value' => 'remarks',         'label' => 'Remarks',                        'required' => false, 'aliases' => ['remarks', 'remarkspdohde', 'notes']],
        ];

        if ($type === 'cvs') {
            return array_merge($common, [
                ['value' => 'person_name',     'label' => 'Name',                        'required' => true,  'aliases' => ['name', 'personname']],
                ['value' => 'position',        'label' => 'Position',                    'required' => false, 'aliases' => ['position', 'role']],
                ['value' => 'approval_status', 'label' => 'Approval Status',             'required' => false, 'aliases' => ['approvalstatus']],
                ['value' => 'validity_value',  'label' => 'Validity Value',              'required' => false, 'aliases' => ['validityvalue', 'validity']],
                ['value' => 'validity_unit',   'label' => 'Validity Unit (days/months)', 'required' => false, 'aliases' => ['validityunit']],
                ['value' => 'hde_remarks',     'label' => 'HDE Remarks',                 'required' => false, 'aliases' => ['hderemarks']],
            ]);
        }

        return array_merge($common, [
            ['value' => 'required_action', 'label' => 'Required Action (yes/no)', 'required' => false, 'aliases' => ['requiredaction']],
            ['value' => 'status',          'label' => 'Status (open/closed)',      'required' => false, 'aliases' => ['status']],
        ]);
    }

    private function exampleRow(string $type): array
    {
        $common = [
            'PDO-2026-001', '2026-01-15', 'outgoing', 'CTP-0155',
            'Request for approval of work plan', '', '', '',
        ];

        return $type === 'cvs'
            ? array_merge($common, ['John Smith', 'Site Engineer', 'pending', '', '', ''])
            : array_merge($common, ['yes', 'open']);
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

    private function mapRow(array $row, array $mapping, string $type): array
    {
        $get = function (string $field) use ($row, $mapping) {
            $idx = $mapping[$field] ?? null;
            if ($idx === null || $idx === '' || !array_key_exists((int) $idx, $row)) {
                return null;
            }
            $v = $row[(int) $idx];
            if (is_string($v)) $v = trim($v);
            return $v === '' ? null : $v;
        };

        $record = [
            'ctp_number'      => $get('ctp_number'),
            'reference_no'    => $get('reference_no'),
            'submission_date' => $this->normalizeDate($get('submission_date')),
            'description'     => $get('description'),
            'direction'       => $this->normalizeDirection($get('direction')),
            'reply_ref_no'    => $get('reply_ref_no'),
            'related_refs'    => $this->normalizeRelatedRefs($get('related_refs')),
            'remarks'         => $get('remarks'),
        ];

        if ($type === 'cvs') {
            $record['person_name']     = $get('person_name');
            $record['position']        = $get('position');
            $record['approval_status'] = $this->normalizeApprovalStatus($get('approval_status'));
            $record['validity_value']  = $get('validity_value') !== null ? (int) $get('validity_value') : null;
            $record['validity_unit']   = $this->normalizeValidityUnit($get('validity_unit'));
            $record['hde_remarks']     = $get('hde_remarks');
        } else {
            $record['required_action'] = $this->normalizeBool($get('required_action'));
            $record['status']          = $this->normalizeStatus($get('status'));
        }

        return $record;
    }

    private function normalizeDirection($v): ?string
    {
        if ($v === null) return 'outgoing';
        $n = strtolower(trim((string) $v));
        if (in_array($n, ['outgoing', 'out', 'sent to pdo', 'sent', 'o'], true)) return 'outgoing';
        if (in_array($n, ['incoming', 'in', 'from pdo', 'received', 'i'], true)) return 'incoming';
        return (string) $v;
    }

    private function normalizeStatus($v): string
    {
        $n = strtolower(trim((string) $v));
        return in_array($n, ['open', 'closed'], true) ? $n : 'open';
    }

    private function normalizeApprovalStatus($v): string
    {
        if ($v === null) return 'pending';
        $n = strtolower(str_replace(' ', '_', trim((string) $v)));
        return in_array($n, ['pending', 'approved_temporary', 'approved_final', 'rejected'], true) ? $n : 'pending';
    }

    private function normalizeValidityUnit($v): ?string
    {
        if ($v === null) return null;
        $n = strtolower(trim((string) $v));
        if (str_starts_with($n, 'day'))   return 'days';
        if (str_starts_with($n, 'month')) return 'months';
        return null;
    }

    private function normalizeBool($v): bool
    {
        if ($v === null) return false;
        return in_array(strtolower(trim((string) $v)), ['yes', 'y', 'true', '1'], true);
    }

    private function normalizeRelatedRefs($v): array
    {
        if ($v === null) return [];
        return array_values(array_filter(array_map('trim', explode(',', (string) $v))));
    }

    private function normalizeDate($v): ?string
    {
        if ($v === null) return null;

        if (is_numeric($v)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
            } catch (\Throwable) {
                // fall through to string parsing
            }
        }

        try {
            return Carbon::parse((string) $v)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function letterRules(): array
    {
        return [
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
        ];
    }

    private function cvRules(): array
    {
        return [
            'ctp_number'      => 'nullable|string|max:50',
            'reference_no'    => 'required|string|max:200',
            'submission_date' => 'required|date',
            'description'     => 'nullable|string',
            'direction'       => 'required|in:outgoing,incoming',
            'reply_ref_no'    => 'nullable|string|max:200',
            'related_refs'    => 'nullable|array',
            'related_refs.*'  => 'string|max:200',
            'person_name'     => 'required|string|max:150',
            'position'        => 'nullable|string|max:150',
            'approval_status' => 'in:pending,approved_temporary,approved_final,rejected',
            'validity_value'  => 'nullable|integer|min:1|required_if:approval_status,approved_temporary',
            'validity_unit'   => 'nullable|in:days,months|required_if:approval_status,approved_temporary',
            'remarks'         => 'nullable|string',
            'hde_remarks'     => 'nullable|string',
        ];
    }
}
