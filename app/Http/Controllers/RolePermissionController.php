<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RolePermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin']);
    }
    
    /**
     * Display a listing of roles with their permissions.
     */
    public function index()
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();
        
        return view('admin.roles.permissions-index', compact('roles', 'permissions'));
    }
    
    /**
     * Show form to assign permissions to a role.
     */
    public function edit($roleId)
    {
        $role = Role::with('permissions')->findOrFail($roleId);
        $allPermissions = Permission::orderBy('label')->get();

        $groupedPermissions = $allPermissions
            ->groupBy(fn ($permission) => $permission->module ?? 'other')
            ->sortKeys()
            ->mapWithKeys(function ($permissions, $moduleKey) {
                // Recover the richer module label (e.g. "Bell Schedules & Special
                // Day Overrides") from a "View {label}" / "Manage {label}"
                // permission's own label, rather than losing detail by
                // title-casing the bare module key. Falls back to a title-cased
                // key for the original hand-picked modules, whose labels don't
                // follow that prefix pattern.
                $prefixed = $permissions->first(fn ($p) => str_starts_with((string) $p->label, 'View ') || str_starts_with((string) $p->label, 'Manage '));
                $label = $prefixed
                    ? preg_replace('/^(View|Manage)\s+/', '', $prefixed->label)
                    : \Illuminate\Support\Str::of($moduleKey)->replace('-', ' ')->title();

                return [$moduleKey => ['label' => $label, 'permissions' => $permissions]];
            });

        return view('admin.roles.permissions-edit', compact('role', 'allPermissions', 'groupedPermissions'));
    }
    
    /**
     * Update permissions for a role.
     */
    public function update(Request $request, $roleId)
    {
        $role = Role::findOrFail($roleId);
        $permissions = $request->input('permissions', []);
        
        // Clear existing permissions
        $role->permissions()->detach();
        
        // Attach new permissions
        foreach ($permissions as $permissionId) {
            $permission = Permission::find($permissionId);
            if ($permission) {
                $role->grantPermission($permission->name);
            }
        }
        
        return redirect()->route('admin.role-permissions.index')
                         ->with('success', 'Permissions updated successfully for role: ' . $role->name);
    }
    
    /**
     * Show form to assign roles to a user.
     */
    public function editUserRoles($userId)
    {
        $user = User::with('roles')->findOrFail($userId);
        $allRoles = Role::all();
        
        return view('admin.users.roles-edit', compact('user', 'allRoles'));
    }
    
    /**
     * Update roles for a user.
     */
    public function updateUserRoles(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        $roles = $request->input('roles', []);
        
        // Clear existing roles
        $user->roles()->detach();
        
        // Attach new roles
        foreach ($roles as $roleId) {
            $role = Role::find($roleId);
            if ($role) {
                $user->assignRole($role->name);
            }
        }
        
        return redirect()->route('users.index')
                         ->with('success', 'User roles updated successfully for: ' . $user->name);
    }
}
