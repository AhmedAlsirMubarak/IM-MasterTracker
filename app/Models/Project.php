<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
 
class Project extends Model
{
    use HasFactory, SoftDeletes;
 
    protected $fillable = ['contract_id', 'name', 'description', 'status', 'created_by'];
 
    public function wells()
    {
        return $this->hasMany(ProjectWell::class);
    }
 
    public function columnConfigs()
    {
        return $this->hasMany(MasterColumnConfig::class)->orderBy('display_order');
    }
 
    public function pinnedColumns()
    {
        return $this->columnConfigs()->where('is_pinned', true);
    }
 
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
 
    public function importSessions()
    {
        return $this->hasMany(ImportSession::class);
    }
}