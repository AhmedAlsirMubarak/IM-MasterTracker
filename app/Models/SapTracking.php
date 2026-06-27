<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
 
class SapTracking extends Model
{
    protected $table = 'sap_tracking';
 
    protected $fillable = [
        'project_well_id',
        'earliest_start_date', 'earliest_end_date', 'station_code',
        'hook_up_tie_in_completed', 'commissioning_date',
        'z6_creation_date', 'z6_awap_date', 'z6_appr_date',
        'sap_upload_date', 'bom_linking_date',
        'afc_submitted_date', 'afc_edw_completed_date',
        'asb_edw_sent_date', 'inst_manufacturer_date',
        'mech_manufacturer_date', 'rv_mfr_details_date', 'esp_req_date',
        'z6_number', 'sap_status', 'upload_status',
        'afc_document_spo_status', 'afc_edw_status', 'asb_edw_status',
        'asb_edw_transmittal', 'bom_linking_status',
        'cims_document_number', 'cims_upload_status', 'cims_transmittal_no',
        'spir_status', 'im_health_check_tags', 'asset_register_report',
        'sap_screenshot', 'elec_isolator', 'db_field', 'edw_remark',
        'sap_kpi', 'updated_by',
    ];
 
    protected $casts = [
        'earliest_start_date'    => 'date',
        'earliest_end_date'      => 'date',
        'hook_up_tie_in_completed' => 'date',
        'commissioning_date'     => 'date',
        'z6_creation_date'       => 'date',
        'z6_awap_date'           => 'date',
        'z6_appr_date'           => 'date',
        'sap_upload_date'        => 'date',
        'bom_linking_date'       => 'date',
        'afc_submitted_date'     => 'date',
        'afc_edw_completed_date' => 'date',
        'asb_edw_sent_date'      => 'date',
        'inst_manufacturer_date' => 'date',
        'mech_manufacturer_date' => 'date',
        'rv_mfr_details_date'    => 'date',
        'esp_req_date'           => 'date',
    ];
 
    // Columns editable by SAP Admin that appear in the master view
    public static array $masterViewColumns = [
        'earliest_start_date', 'earliest_end_date', 'station_code', 'z6_number',
        'afc_edw_status', 'sap_status', 'hook_up_tie_in_completed',
        'commissioning_date',
    ];
 
    public function well()
    {
        return $this->belongsTo(ProjectWell::class, 'project_well_id');
    }
 
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
 
    // Computed SAP KPI in days
    public function computeKpi(): ?int
    {
        if (!$this->earliest_start_date || !$this->earliest_end_date) return null;
        return $this->earliest_start_date->diffInDays($this->earliest_end_date);
    }
 
    public function getKpiStatusAttribute(): string
    {
        $kpi = $this->sap_kpi;
        if ($kpi === null) return 'none';
        if ($kpi <= 30) return 'green';
        if ($kpi <= 60) return 'amber';
        return 'red';
    }
}