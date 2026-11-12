<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class ImportSession extends Model
{
    protected $fillable = [
        'project_id', 'uploaded_by', 'original_filename', 'stored_path',
        'sheet_name', 'target_table', 'status', 'column_mapping',
        'total_rows', 'processed_rows', 'skipped_rows', 'error_log',
        'created_wells', 'updated_wells', 'merged_rows', 'duplicate_rows', 'warning_log',
    ];
 
    protected $casts = [
        'column_mapping' => 'array',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    protected $appends = ['created_at_human'];

    public function getCreatedAtHumanAttribute(): ?string
    {
        return $this->created_at?->format('d M Y, H:i');
    }
 
    public function project()    { return $this->belongsTo(Project::class); }
    public function uploadedBy() { return $this->belongsTo(User::class, 'uploaded_by'); }
 
    public function getProgressAttribute(): int
    {
        if (!$this->total_rows) return 0; // null before the job starts — avoid division by zero
        return (int) round(($this->processed_rows / $this->total_rows) * 100);
    }
}