<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class CellComment extends Model
{
    protected $fillable = [
        'project_well_id', 'source_table', 'source_column',
        'comment_text', 'commented_by',
    ];

    protected $appends = ['created_at_human'];

    public function getCreatedAtHumanAttribute(): ?string
    { return $this->created_at?->diffForHumans(); }
 
    public function author()
    { return $this->belongsTo(User::class, 'commented_by'); }

    public function well()
    { return $this->belongsTo(ProjectWell::class, 'project_well_id'); }
}