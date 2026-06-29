<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjectWell extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'contract_no', 'scope', 'hua_date', 'well_id', 'well_number',
        'well_type', 'well_field', 'well_location', 'ramz_id', 'year', 'previous_year', 'responsible_asset',
        'cims_upload_status', 'spir_status', 'asb_edw_status', 'e_pad_closeout',
        'rlmu_status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'hua_date' => 'date',
    ];

    // Fixed columns that only super_admin can change
    public static array $fixedColumns = [
        'contract_no', 'scope', 'hua_date', 'well_id',
        'well_number', 'well_type', 'well_field', 'well_location', 'ramz_id', 'year', 'responsible_asset',
    ];

    // Manager-editable columns
    public static array $managerColumns = [
        'cims_upload_status', 'spir_status', 'asb_edw_status', 'e_pad_closeout', 'rlmu_status',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function sapTracking()
    {
        return $this->hasOne(SapTracking::class);
    }

    public function dcTracking()
    {
        return $this->hasOne(DcTracking::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class)->orderByDesc('created_at');
    }

    public function cellComments()
    {
        return $this->hasMany(CellComment::class)->orderByDesc('created_at');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Computed: countdown from commissioning date
    public function getCountdownDaysAttribute(): ?int
    {
        $commissioned = $this->sapTracking?->commissioning_date;
        if (!$commissioned) return null;
        return (int) $commissioned->diffInDays(now());
    }

    public function getCountdownStatusAttribute(): string
    {
        $days = $this->countdown_days;
        if ($days === null) return 'none';
        $ePadClosed = strtolower($this->e_pad_closeout ?? '') === 'closed';
        if ($ePadClosed) return 'closed';
        if ($days > 90) return 'overdue';   // RED
        if ($days > 80) return 'warning';   // AMBER — 10 days left
        return 'ok';                         // GREEN
    }
}
