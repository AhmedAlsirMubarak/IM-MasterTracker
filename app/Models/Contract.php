<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\ContractLetter;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'contract_no', 'description', 'status', 'created_by'];

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function letters()
    {
        return $this->hasMany(ContractLetter::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
