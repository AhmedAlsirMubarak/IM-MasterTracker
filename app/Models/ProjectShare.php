<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectShare extends Model
{
    protected $fillable = ['project_id', 'token', 'created_by', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function project() { return $this->belongsTo(Project::class); }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
