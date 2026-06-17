<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model {

    public $timestamps = false;

    protected $fillable = 
    ['user_id','model_type','model_id','project_well_id','field_name','old_value','new_value','ip_address','user_agent'];

    protected $casts = 
    ['created_at' => 'datetime'];

    protected $appends = 
    ['created_at_human'];

    public function getCreatedAtHumanAttribute(): ?string
    { return $this->created_at?->diffForHumans(); }

    public function user()
    { return $this->belongsTo(User::class)->select(['id','name','role']); }

    public function projectWell() 
    { return $this->belongsTo(ProjectWell::class)->select(['id','well_number','well_id']); }
}