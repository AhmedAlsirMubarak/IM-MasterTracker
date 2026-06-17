<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class ImportSession extends Model
{
    protected $fillable = [
        'project_id', 'uploaded_by', 'original_filename', 'stored_path',
        'sheet_name', 'target_table', 'status', 'column_mapping',
        'total_rows', 'processed_rows', 'skipped_rows', 'error_log',
    ];
 
    protected $casts = [
        'column_mapping' => 'array',
    ];
 
    public function project()    { return $this->belongsTo(Project::class); }
    public function uploadedBy() { return $this->belongsTo(User::class, 'uploaded_by'); }
 
    public function getProgressAttribute(): int
    {
        if ($this->total_rows === 0) return 0;
        return (int) round(($this->processed_rows / $this->total_rows) * 100);
    }
}