<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractLetter extends Model
{
    protected $fillable = [
        'contract_id', 'ctp_number', 'reference_no', 'submission_date',
        'description', 'direction', 'reply_ref_no', 'related_refs',
        'required_action', 'status', 'remarks', 'created_by',
    ];

    protected $casts = [
        'submission_date'  => 'date',
        'required_action'  => 'boolean',
        'related_refs'     => 'array',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getDaysElapsedAttribute(): int
    {
        return (int) max(0, $this->submission_date->diffInDays(now(), false));
    }

    public function getDaysRemainingAttribute(): int
    {
        return 14 - $this->days_elapsed;
    }

    public function getCountdownStatusAttribute(): string
    {
        $elapsed = $this->days_elapsed;
        if ($elapsed >= 14) return 'red';
        if ($elapsed >= 10) return 'yellow';
        return 'green';
    }
}
