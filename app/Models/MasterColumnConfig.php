<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class MasterColumnConfig extends Model
{
    protected $fillable = [
        'project_id', 'source_table', 'source_column',
        'display_label', 'display_order', 'is_pinned', 'is_locked', 'pinned_by',
    ];
 
    protected $casts = [
        'is_pinned'  => 'boolean',
        'is_locked'  => 'boolean',
    ];
 
    public function project()  { return $this->belongsTo(Project::class); }
    public function pinnedBy() { return $this->belongsTo(User::class, 'pinned_by'); }
 
    public function statusOptions()
    {
        return ColumnStatusOption::where('source_table', $this->source_table)
            ->where('source_column', $this->source_column)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}