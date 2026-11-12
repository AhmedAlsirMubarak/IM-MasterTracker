<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\HiddenWellCell;
use App\Models\MasterColumnConfig;
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

        $date = fn ($d) => $d?->toDateString();

        // [header, "source.column" key (for hiding), value getter]
        $columns = [
            ['Well No.',            'project_wells.well_number',          fn ($w, $s, $d, $c) => $w->well_number],
            ['Well ID',             'project_wells.well_id',              fn ($w, $s, $d, $c) => $w->well_id],
            ['Well Type',           'project_wells.well_type',            fn ($w, $s, $d, $c) => $w->well_type],
            ['Contract No',         'project_wells.contract_no',          fn ($w, $s, $d, $c) => $w->contract_no],
            ['Scope',               'project_wells.scope',                fn ($w, $s, $d, $c) => $w->scope],
            ['HUA Date',            'project_wells.hua_date',             fn ($w, $s, $d, $c) => $date($w->hua_date)],
            ['RAMZ ID',             'project_wells.ramz_id',              fn ($w, $s, $d, $c) => $w->ramz_id],
            ['Year',                'project_wells.year',                 fn ($w, $s, $d, $c) => $w->year],
            ['Responsible Asset',   'project_wells.responsible_asset',    fn ($w, $s, $d, $c) => $w->responsible_asset],
            ['CIMS Upload Status',  'project_wells.cims_upload_status',   fn ($w, $s, $d, $c) => $w->cims_upload_status],
            ['SPIR Status',         'project_wells.spir_status',          fn ($w, $s, $d, $c) => $w->spir_status],
            ['ASB EDW Status',      'project_wells.asb_edw_status',       fn ($w, $s, $d, $c) => $w->asb_edw_status],
            ['E-Pad Closeout',      'project_wells.e_pad_closeout',       fn ($w, $s, $d, $c) => $w->e_pad_closeout],
            ['RLMU Status',         'project_wells.rlmu_status',          fn ($w, $s, $d, $c) => $w->rlmu_status],
            ['SAP Status',          'sap_tracking.sap_status',            fn ($w, $s, $d, $c) => $s?->sap_status],
            ['Earliest Start',      'sap_tracking.earliest_start_date',   fn ($w, $s, $d, $c) => $date($s?->earliest_start_date)],
            ['Earliest End',        'sap_tracking.earliest_end_date',     fn ($w, $s, $d, $c) => $date($s?->earliest_end_date)],
            ['Station Code',        'sap_tracking.station_code',          fn ($w, $s, $d, $c) => $s?->station_code],
            ['Z6 Number',           'sap_tracking.z6_number',             fn ($w, $s, $d, $c) => $s?->z6_number],
            ['AFC EDW Status',      'sap_tracking.afc_edw_status',        fn ($w, $s, $d, $c) => $s?->afc_edw_status],
            ['Tie-in Completed',    'sap_tracking.hook_up_tie_in_completed', fn ($w, $s, $d, $c) => $date($s?->hook_up_tie_in_completed)],
            ['Commissioning Date',  'sap_tracking.commissioning_date',    fn ($w, $s, $d, $c) => $date($s?->commissioning_date)],
            ['SAP KPI (days)',      'computed.sap_kpi',                   fn ($w, $s, $d, $c) => $c['sap_kpi'] ?? null],
            ['ASB Document Status', 'dc_tracking.asb_document_status',    fn ($w, $s, $d, $c) => $d?->asb_document_status],
            ['ASB Receive Date',    'dc_tracking.asb_receive_date',       fn ($w, $s, $d, $c) => $date($d?->asb_receive_date)],
            ['ASB Submission Date', 'dc_tracking.asb_document_submission_date', fn ($w, $s, $d, $c) => $date($d?->asb_document_submission_date)],
            ['AFC Upload Status',   'dc_tracking.afc_upload_status',      fn ($w, $s, $d, $c) => $d?->afc_upload_status],
            ['RLMU Upload Status',  'dc_tracking.rlmu_upload_status',     fn ($w, $s, $d, $c) => $d?->rlmu_upload_status],
            ['Countdown Days',      'computed.countdown_days',            fn ($w, $s, $d, $c) => $c['countdown_days'] ?? null],
            ['Countdown Status',    'computed.countdown_status',          fn ($w, $s, $d, $c) => $c['countdown_status'] ?? null],
        ];

        // Non-admins never get hidden columns or hidden cells in the file
        $hiddenByWell = collect();
        if (!auth()->user()->isSuperAdmin()) {
            $hiddenCols = MasterColumnConfig::where('project_id', $project->id)
                ->where('is_hidden', true)
                ->get(['source_table', 'source_column'])
                ->map(fn ($c) => $c->source_table . '.' . $c->source_column)
                ->all();
            $columns = array_values(array_filter($columns, fn ($col) => !in_array($col[1], $hiddenCols, true)));

            $hiddenByWell = HiddenWellCell::where('project_id', $project->id)
                ->get(['well_id', 'source_table', 'source_column'])
                ->groupBy('well_id')
                ->map(fn ($rows) => $rows->map(fn ($r) => $r->source_table . '.' . $r->source_column)->all());
        }

        $rows = [array_column($columns, 0)];

        foreach ($wells as $well) {
            $computed = $this->countdown->computeForWell($well);
            $hidden   = $hiddenByWell->get($well->id, []);
            $row      = [];
            foreach ($columns as [, $key, $get]) {
                $row[] = in_array($key, $hidden, true)
                    ? null
                    : $get($well, $well->sapTracking, $well->dcTracking, $computed);
            }
            $rows[] = $row;
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
            'Earliest Start Date', 'Earliest End Date', 'Station Code', 'Z6 Number',
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
                $sap?->station_code,
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
                $this->countdown->computeSapKpi($sap?->earliest_start_date, $sap?->earliest_end_date),
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
            fputcsv($fp, array_map([\App\Support\Csv::class, 'cell'], $row));
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return $csv;
    }
}
