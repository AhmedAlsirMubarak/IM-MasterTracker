<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class DcTracking extends Model
{
    protected $table = 'dc_tracking';
 
    protected $fillable = [
        'project_well_id',
        'well_time_document_status', 'afc_upload_status',
        'final_afc_upload_date', 'afc_document_remarks',
        'earliest_start_date', 'earliest_end_date',
        'rlmu_upload_status', 'final_rlmu_upload_date',
        'asb_receive_date', 'asb_document_status',
        'asb_document_submission_date', 'asb_tr_no',
        'document_im_health_check', 'updated_by',
    ];
 
    protected $casts = [
        'final_afc_upload_date'        => 'date',
        'earliest_start_date'          => 'date',
        'earliest_end_date'            => 'date',
        'final_rlmu_upload_date'       => 'date',
        'asb_receive_date'             => 'date',
        'asb_document_submission_date' => 'date',
    ];
 
    // Columns fetched into master view
    public static array $masterViewColumns = ['asb_document_status'];
 
    public function well()
    {
        return $this->belongsTo(ProjectWell::class, 'project_well_id');
    }
 
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}