<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['name', 'display_name', 'description'];
    
    // Define relationship with users
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }
    
    // Define relationship with permissions
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id');
    }
    
    // Check if role has specific permission
    public function hasPermission($permissionName): bool
    {
        if (is_string($permissionName)) {
            $permission = Permission::where('name', $permissionName)->first();
            return $permission ? $this->permissions->contains($permission->id) : false;
        }
        
        return $this->permissions->contains($permissionName->id);
    }
    
    // Grant permission to role
    public function grantPermission($permissionName): void
    {
        if (is_string($permissionName)) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission) {
                $this->permissions()->attach($permission->id);
            }
        } else {
            $this->permissions()->attach($permissionName);
        }
    }
    
    // Revoke permission from role
    public function revokePermission($permissionName): void
    {
        if (is_string($permissionName)) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission) {
                $this->permissions()->detach($permission->id);
            }
        } else {
            $this->permissions()->detach($permissionName);
        }
    }
}
