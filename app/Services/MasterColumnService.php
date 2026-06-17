<?php
 
namespace App\Services;
 
use App\Models\MasterColumnConfig;
use Illuminate\Support\Collection;
 
class MasterColumnService
{
    /**
     * All columns available for pinning, organized by source table.
     */
    public function availableColumns(): array
    {
        return [
            'project_wells' => [
                ['column' => 'contract_no',       'label' => 'Contract NO',        'type' => 'text',   'locked' => true],
                ['column' => 'scope',             'label' => 'Scope',              'type' => 'text',   'locked' => true],
                ['column' => 'hua_date',          'label' => 'HUA Date',           'type' => 'date',   'locked' => true],
                ['column' => 'well_id',           'label' => 'Well ID',            'type' => 'text',   'locked' => true],
                ['column' => 'well_number',       'label' => 'Well Number',        'type' => 'text',   'locked' => true],
                ['column' => 'well_type',         'label' => 'Well Type',          'type' => 'text',   'locked' => true],
                ['column' => 'well_field',        'label' => 'Field',              'type' => 'text',   'locked' => true],
                ['column' => 'well_location',     'label' => 'Well Location',      'type' => 'text',   'locked' => true],
                ['column' => 'ramz_id',           'label' => 'Ramz ID',            'type' => 'text',   'locked' => true],
                ['column' => 'year',              'label' => 'Year',               'type' => 'number', 'locked' => true],
                ['column' => 'responsible_asset', 'label' => 'Responsible Asset',  'type' => 'text',   'locked' => true],
                ['column' => 'cims_upload_status','label' => 'CIMS Upload Status', 'type' => 'select', 'locked' => false],
                ['column' => 'spir_status',       'label' => 'SPIR Status',        'type' => 'select', 'locked' => false],
                ['column' => 'asb_edw_status',    'label' => 'ASB EDW Status',     'type' => 'select', 'locked' => false],
                ['column' => 'e_pad_closeout',    'label' => 'E-Pad Closeout',     'type' => 'select', 'locked' => false],
                ['column' => 'rlmu_status',       'label' => 'RLMU Status',        'type' => 'select', 'locked' => false],
            ],
            'sap_tracking' => [
                ['column' => 'earliest_start_date',      'label' => 'EarliestStartDate',           'type' => 'date',   'locked' => false],
                ['column' => 'earliest_end_date',        'label' => 'EarliestEndDate',             'type' => 'date',   'locked' => false],
                ['column' => 'z6_number',                'label' => 'Z6 Number',                   'type' => 'text',   'locked' => false],
                ['column' => 'afc_edw_status',           'label' => 'AFC EDW Status',              'type' => 'select', 'locked' => false],
                ['column' => 'sap_status',               'label' => 'SAP Status',                  'type' => 'select', 'locked' => false],
                ['column' => 'hook_up_tie_in_completed', 'label' => 'Hook up & Tie in Completed',  'type' => 'date',   'locked' => false],
                ['column' => 'commissioning_date',       'label' => 'Commissioning Date',          'type' => 'date',   'locked' => false],
                ['column' => 'afc_document_spo_status',  'label' => 'AFC Document SPO Status',     'type' => 'select', 'locked' => false],
                ['column' => 'afc_submitted_date',       'label' => 'AFC Submitted Date',          'type' => 'date',   'locked' => false],
                ['column' => 'z6_creation_date',         'label' => 'Z6 Creation Date',            'type' => 'date',   'locked' => false],
                ['column' => 'z6_awap_date',             'label' => 'Z6 AWAP Date',                'type' => 'date',   'locked' => false],
                ['column' => 'z6_appr_date',             'label' => 'Z6 APPR Date',                'type' => 'date',   'locked' => false],
                ['column' => 'sap_upload_date',          'label' => 'SAP Upload Date',             'type' => 'date',   'locked' => false],
                ['column' => 'bom_linking_date',         'label' => 'BOM Linking Date',            'type' => 'date',   'locked' => false],
                ['column' => 'bom_linking_status',       'label' => 'BOM Linking Status',          'type' => 'select', 'locked' => false],
                ['column' => 'cims_upload_status',       'label' => 'CIMS Upload Status (SAP)',    'type' => 'select', 'locked' => false],
                ['column' => 'cims_transmittal_no',      'label' => 'CIMS Transmittal No',         'type' => 'text',   'locked' => false],
                ['column' => 'spir_status',              'label' => 'SPIR Status (SAP)',           'type' => 'select', 'locked' => false],
                ['column' => 'asb_edw_status',           'label' => 'ASB EDW Status',              'type' => 'select', 'locked' => false],
                ['column' => 'asb_edw_sent_date',        'label' => 'ASB EDW Sent Date',           'type' => 'date',   'locked' => false],
                ['column' => 'im_health_check_tags',     'label' => 'IM Health Check (Tags)',      'type' => 'select', 'locked' => false],
                ['column' => 'asset_register_report',    'label' => 'Asset Register Report',       'type' => 'select', 'locked' => false],
                ['column' => 'elec_isolator',            'label' => 'Elec Isolator',               'type' => 'select', 'locked' => false],
                ['column' => 'inst_manufacturer_date',   'label' => 'Inst. Manufacturer Date',     'type' => 'date',   'locked' => false],
                ['column' => 'mech_manufacturer_date',   'label' => 'Mech. Manufacturer Date',     'type' => 'date',   'locked' => false],
                ['column' => 'edw_remark',               'label' => 'EDW Remark',                  'type' => 'text',   'locked' => false],
            ],
            'dc_tracking' => [
                ['column' => 'asb_document_status',          'label' => 'ASB Document Status',         'type' => 'select', 'locked' => false],
                ['column' => 'well_time_document_status',    'label' => 'Well Time Document Status',   'type' => 'select', 'locked' => false],
                ['column' => 'afc_upload_status',            'label' => 'AFC Upload Status',           'type' => 'select', 'locked' => false],
                ['column' => 'final_afc_upload_date',        'label' => 'Final AFC Upload Date',       'type' => 'date',   'locked' => false],
                ['column' => 'rlmu_upload_status',           'label' => 'RLMU Upload Status',          'type' => 'select', 'locked' => false],
                ['column' => 'final_rlmu_upload_date',       'label' => 'Final RLMU Upload Date',      'type' => 'date',   'locked' => false],
                ['column' => 'asb_receive_date',             'label' => 'ASB Receive Date',            'type' => 'date',   'locked' => false],
                ['column' => 'asb_document_submission_date', 'label' => 'ASB Submission Date',         'type' => 'date',   'locked' => false],
                ['column' => 'asb_tr_no',                    'label' => 'ASB TR No',                   'type' => 'text',   'locked' => false],
                ['column' => 'document_im_health_check',     'label' => 'Document IM Health Check',    'type' => 'select', 'locked' => false],
                ['column' => 'afc_document_remarks',         'label' => 'AFC Document Remarks',        'type' => 'text',   'locked' => false],
            ],
            'computed' => [
                ['column' => 'sap_kpi',          'label' => 'SAP KPI (days)',      'type' => 'computed', 'locked' => true],
                ['column' => 'countdown_days',   'label' => 'Countdown 90 days',   'type' => 'computed', 'locked' => true],
                ['column' => 'countdown_status', 'label' => 'Countdown Status',    'type' => 'computed', 'locked' => true],
            ],
        ];
    }
 
    /**
     * Get pinned columns for a project, ready to render in the master grid.
     */
    public function getPinnedColumns(int $projectId): Collection
    {
        return MasterColumnConfig::where('project_id', $projectId)
            ->where('is_pinned', true)
            ->orderBy('display_order')
            ->get();
    }
 
    /**
     * Seed default columns for a newly created project.
     */
    public function seedDefaults(int $projectId): void
    {
        $defaults = [
            ['source_table' => 'project_wells', 'source_column' => 'contract_no',            'display_label' => 'Contract NO',               'display_order' => 1,  'is_locked' => true],
            ['source_table' => 'project_wells', 'source_column' => 'scope',                  'display_label' => 'Scope',                     'display_order' => 2,  'is_locked' => true],
            ['source_table' => 'project_wells', 'source_column' => 'hua_date',               'display_label' => 'HUA Date',                  'display_order' => 3,  'is_locked' => true],
            ['source_table' => 'project_wells', 'source_column' => 'well_id',                'display_label' => 'Well ID',                   'display_order' => 4,  'is_locked' => true],
            ['source_table' => 'project_wells', 'source_column' => 'well_number',            'display_label' => 'Well Number',               'display_order' => 5,  'is_locked' => true],
            ['source_table' => 'project_wells', 'source_column' => 'well_type',              'display_label' => 'Well Type',                 'display_order' => 6,  'is_locked' => true],
            ['source_table' => 'project_wells', 'source_column' => 'well_field',            'display_label' => 'Field',                     'display_order' => 7,  'is_locked' => true],
            ['source_table' => 'project_wells', 'source_column' => 'well_location',         'display_label' => 'Well Location',             'display_order' => 8,  'is_locked' => true],
            ['source_table' => 'project_wells', 'source_column' => 'ramz_id',               'display_label' => 'Ramz ID',                   'display_order' => 9,  'is_locked' => true],
            ['source_table' => 'project_wells', 'source_column' => 'year',                   'display_label' => 'Year',                      'display_order' => 10, 'is_locked' => true],
            ['source_table' => 'project_wells', 'source_column' => 'responsible_asset',      'display_label' => 'Responsible Asset',         'display_order' => 11, 'is_locked' => true],
            ['source_table' => 'sap_tracking',  'source_column' => 'earliest_start_date',    'display_label' => 'Earliest Start Date',       'display_order' => 12, 'is_locked' => false],
            ['source_table' => 'sap_tracking',  'source_column' => 'earliest_end_date',      'display_label' => 'Earliest End Date',         'display_order' => 13, 'is_locked' => false],
            ['source_table' => 'sap_tracking',  'source_column' => 'z6_number',              'display_label' => 'Z6 Number',                 'display_order' => 14, 'is_locked' => false],
            ['source_table' => 'sap_tracking',  'source_column' => 'afc_edw_status',         'display_label' => 'AFC EDW Status',            'display_order' => 15, 'is_locked' => false],
            ['source_table' => 'sap_tracking',  'source_column' => 'sap_status',             'display_label' => 'SAP Status',                'display_order' => 16, 'is_locked' => false],
            ['source_table' => 'computed',       'source_column' => 'sap_kpi',               'display_label' => 'SAP KPI (days)',            'display_order' => 17, 'is_locked' => true],
            ['source_table' => 'sap_tracking',  'source_column' => 'hook_up_tie_in_completed','display_label' => 'Tie in Completed',         'display_order' => 18, 'is_locked' => false],
            ['source_table' => 'sap_tracking',  'source_column' => 'commissioning_date',     'display_label' => 'Commissioning Date',        'display_order' => 19, 'is_locked' => false],
            ['source_table' => 'computed',       'source_column' => 'countdown_days',         'display_label' => 'Countdown 90 days',         'display_order' => 20, 'is_locked' => true],
            ['source_table' => 'project_wells', 'source_column' => 'rlmu_status',            'display_label' => 'RLMU Status',               'display_order' => 21, 'is_locked' => false],
            ['source_table' => 'dc_tracking',   'source_column' => 'asb_document_status',    'display_label' => 'ASB Document Status',       'display_order' => 22, 'is_locked' => false],
        ];
 
        $now  = now();
        $rows = array_map(fn($col) => array_merge($col, [
            'project_id' => $projectId,
            'is_pinned'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]), $defaults);

        // insertOrIgnore skips rows whose (project_id, source_table, source_column) already exist
        MasterColumnConfig::insertOrIgnore($rows);
    }
}