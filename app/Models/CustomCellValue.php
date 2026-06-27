<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomCellValue extends Model
{
    protected $fillable = [
        'project_id', 'well_id', 'source_column', 'value', 'updated_by',
    ];

    public function well()      { return $this->belongsTo(ProjectWell::class, 'well_id'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by'); }
}
