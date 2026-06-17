<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectWell;
use App\Services\CountdownService;
use Illuminate\Http\Response;

class ExportController extends Controller
{
    public function __construct(private CountdownService $countdown) {}

    public function master(Project $project): Response
    {
        $wells = ProjectWell::where('project_id', $project->id)
            ->with(['sapTracking', 'dcTracking'])
            ->orderByRaw('well_number IS NULL, well_number ASC')
            ->get();

        $headers = [
            'Well No.', 'Well ID', 'Well Type', 'Contract No', 'Scope',
            'HUA Date', 'RAMZ ID', 'Year', 'Responsible Asset',
            'CIMS Upload Status', 'SPIR Status', 'ASB EDW Status',
            'E-Pad Closeout', 'RLMU Status',
            'SAP Status', 'Earliest Start', 'Earliest End', 'Z6 Number',
            'AFC EDW Status', 'Tie-in Completed', 'Commissioning Date', 'SAP KPI (days)',
            'ASB Document Status', 'ASB Receive Date', 'ASB Submission Date',
            'AFC Upload Status', 'RLMU Upload Status',
            'Countdown Days', 'Countdown Status',
        ];

        $rows = [$headers];

        foreach ($wells as $well) {
            $sap      = $well->sapTracking;
            $dc       = $well->dcTracking;
            $computed = $this->countdown->computeForWell($well);

            $rows[] = [
                $well->well_number,
                $well->well_id,
                $well->well_type,
                $well->contract_no,
                $well->scope,
                $well->hua_date?->toDateString(),
                $well->ramz_id,
                $well->year,
                $well->responsible_asset,
                $well->cims_upload_status,
                $well->spir_status,
                $well->asb_edw_status,
                $well->e_pad_closeout,
                $well->rlmu_status,
                $sap?->sap_status,
                $sap?->earliest_start_date?->toDateString(),
                $sap?->earliest_end_date?->toDateString(),
                $sap?->z6_number,
                $sap?->afc_edw_status,
                $sap?->hook_up_tie_in_completed?->toDateString(),
                $sap?->commissioning_date?->toDateString(),
                $sap?->sap_kpi,
                $dc?->asb_document_status,
                $dc?->asb_receive_date?->toDateString(),
                $dc?->asb_document_submission_date?->toDateString(),
                $dc?->afc_upload_status,
                $dc?->rlmu_upload_status,
                $computed['countdown_days'] ?? null,
                $computed['countdown_status'] ?? null,
            ];
        }

        $filename = 'master-' . str($project->name)->slug() . '-' . now()->format('Ymd') . '.csv';

        return response($this->toCsv($rows), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function sap(Project $project): Response
    {
        $wells = ProjectWell::where('project_id', $project->id)
            ->with(['sapTracking', 'sapTracking.updatedBy:id,name'])
            ->orderByRaw('well_number IS NULL, well_number ASC')
            ->get();

        $headers = [
            'Well No.', 'Well ID', 'Well Type',
            'Earliest Start Date', 'Earliest End Date', 'Z6 Number',
            'SAP Status', 'AFC EDW Status', 'AFC Document SPO Status',
            'Tie-in Completed', 'Commissioning Date',
            'Z6 Creation Date', 'Z6 AWAP Date', 'Z6 APPR Date',
            'SAP Upload Date', 'BOM Linking Date', 'BOM Linking Status',
            'CIMS Upload Status', 'SPIR Status', 'ASB EDW Status',
            'EDW Remark', 'SAP KPI (days)', 'Updated By',
        ];

        $rows = [$headers];

        foreach ($wells as $well) {
            $sap = $well->sapTracking;

            $rows[] = [
                $well->well_number,
                $well->well_id,
                $well->well_type,
                $sap?->earliest_start_date?->toDateString(),
                $sap?->earliest_end_date?->toDateString(),
                $sap?->z6_number,
                $sap?->sap_status,
                $sap?->afc_edw_status,
                $sap?->afc_document_spo_status,
                $sap?->hook_up_tie_in_completed?->toDateString(),
                $sap?->commissioning_date?->toDateString(),
                $sap?->z6_creation_date?->toDateString(),
                $sap?->z6_awap_date?->toDateString(),
                $sap?->z6_appr_date?->toDateString(),
                $sap?->sap_upload_date?->toDateString(),
                $sap?->bom_linking_date?->toDateString(),
                $sap?->bom_linking_status,
                $sap?->cims_upload_status,
                $sap?->spir_status,
                $sap?->asb_edw_status,
                $sap?->edw_remark,
                $sap?->sap_kpi,
                $sap?->updatedBy?->name,
            ];
        }

        $filename = 'sap-tracking-' . str($project->name)->slug() . '-' . now()->format('Ymd') . '.csv';

        return response($this->toCsv($rows), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function dc(Project $project): Response
    {
        $wells = ProjectWell::where('project_id', $project->id)
            ->with(['dcTracking', 'dcTracking.updatedBy:id,name'])
            ->orderByRaw('well_number IS NULL, well_number ASC')
            ->get();

        $headers = [
            'Well No.', 'Well ID', 'Well Type',
            'ASB Document Status', 'ASB Receive Date', 'ASB Submission Date',
            'ASB TR No', 'AFC Upload Status', 'Final AFC Upload Date',
            'AFC Document Remarks', 'RLMU Upload Status', 'Final RLMU Upload Date',
            'Well Time Document Status', 'Document IM Health Check', 'Updated By',
        ];

        $rows = [$headers];

        foreach ($wells as $well) {
            $dc = $well->dcTracking;

            $rows[] = [
                $well->well_number,
                $well->well_id,
                $well->well_type,
                $dc?->asb_document_status,
                $dc?->asb_receive_date?->toDateString(),
                $dc?->asb_document_submission_date?->toDateString(),
                $dc?->asb_tr_no,
                $dc?->afc_upload_status,
                $dc?->final_afc_upload_date?->toDateString(),
                $dc?->afc_document_remarks,
                $dc?->rlmu_upload_status,
                $dc?->final_rlmu_upload_date?->toDateString(),
                $dc?->well_time_document_status,
                $dc?->document_im_health_check,
                $dc?->updatedBy?->name,
            ];
        }

        $filename = 'dc-tracking-' . str($project->name)->slug() . '-' . now()->format('Ymd') . '.csv';

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
