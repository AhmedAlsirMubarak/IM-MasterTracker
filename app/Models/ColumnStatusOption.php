<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class ColumnStatusOption extends Model
{
    protected $fillable = [
        'source_table', 'source_column', 'option_value',
        'option_label', 'option_color', 'sort_order', 'is_active',
    ];
 
    protected $casts = ['is_active' => 'boolean'];
}