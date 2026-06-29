<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HiddenWellCell extends Model
{
    protected $fillable = ['project_id', 'well_id', 'source_table', 'source_column', 'hidden_by'];
}
