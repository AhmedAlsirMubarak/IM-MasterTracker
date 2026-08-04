<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractCv extends Model
{
    protected $fillable = [
        'contract_id', 'ctp_number', 'reference_no', 'submission_date',
        'description', 'direction', 'reply_ref_no', 'related_refs',
        'person_name', 'position',
        'approval_status', 'validity_value', 'validity_unit',
        'remarks', 'hde_remarks', 'created_by',
    ];

    protected $casts = [
        'submission_date' => 'date',
        'validity_value'  => 'integer',
        'related_refs'    => 'array',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getExpiryDateAttribute(): ?\Carbon\Carbon
    {
        if ($this->approval_status !== 'approved_temporary' || !$this->validity_value) {
            return null;
        }

        return $this->validity_unit === 'days'
            ? $this->submission_date->copy()->addDays($this->validity_value)
            : $this->submission_date->copy()->addMonths($this->validity_value);
    }

    public function getDaysRemainingAttribute(): ?int
    {
        $expiry = $this->expiry_date;
        return $expiry ? (int) now()->startOfDay()->diffInDays($expiry->copy()->startOfDay(), false) : null;
    }

    public function getCountdownStatusAttribute(): ?string
    {
        $days = $this->days_remaining;
        if ($days === null) return null;
        if ($days <= 0) return 'red';
        if ($days <= 30) return 'yellow';
        return 'green';
    }
}
