<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
 
class User extends Authenticatable
{
    use HasFactory, Notifiable;
 
    protected $fillable = ['name', 'email', 'password', 'role', 'department', 'is_active'];
 
    protected $hidden = ['password', 'remember_token'];
 
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
    ];
 
    // Role helpers
    public function isSuperAdmin(): bool  { return $this->role === 'super_admin'; }
    public function isManager(): bool     { return $this->role === 'manager'; }
    public function isSapAdmin(): bool    { return $this->role === 'sap_admin'; }
    public function isDc(): bool          { return $this->role === 'dc'; }
    public function isImLead(): bool      { return $this->role === 'im_lead'; }
 
    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles);
    }
 
    public function canEditSource(string $sourceTable): bool
    {
        return match ($sourceTable) {
            'project_wells' => $this->hasRole(['super_admin', 'manager']),
            'sap_tracking'  => $this->hasRole(['super_admin', 'sap_admin']),
            'dc_tracking'   => $this->hasRole(['super_admin', 'dc']),
            'computed'      => false,
            default         => $this->isSuperAdmin(),
        };
    }
 
    public function canEditFixedColumns(): bool
    {
        return $this->isSuperAdmin();
    }
 
    // Relationships
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
 
    public function cellComments()
    {
        return $this->hasMany(CellComment::class, 'commented_by');
    }
}