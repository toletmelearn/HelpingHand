<?php

namespace App\Http\Controllers;

use App\Models\FieldPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FieldPermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = FieldPermission::query();

        // Apply filters
        if ($request->filled('model_type')) {
            $query->forModel($request->model_type);
        }

        if ($request->filled('role')) {
            $query->forRole($request->role);
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->inactive();
            }
        }

        $permissions = $query->orderBy('model_type')->orderBy('role')->orderBy('field_name')->paginate(20);

        return view('admin.field-permissions.index', compact('permissions'));
    }

    public function create()
    {
        return view('admin.field-permissions.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'model_type' => 'required|string|max:255',
            'field_name' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'permission_level' => 'required|in:editable,read_only,hidden',
            'is_active' => 'boolean',
        ]);

        FieldPermission::create([
            'model_type' => $request->model_type,
            'field_name' => $request->field_name,
            'role' => $request->role,
            'permission_level' => $request->permission_level,
            'is_active' => $request->has('is_active'),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.field-permissions.index')
                         ->with('success', 'Field permission created successfully.');
    }

    public function show(FieldPermission $permission)
    {
        return view('admin.field-permissions.show', compact('permission'));
    }

    public function edit(FieldPermission $permission)
    {
        return view('admin.field-permissions.edit', compact('permission'));
    }

    public function update(Request $request, FieldPermission $permission)
    {
        $request->validate([
            'model_type' => 'required|string|max:255',
            'field_name' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'permission_level' => 'required|in:editable,read_only,hidden',
            'is_active' => 'boolean',
        ]);

        $permission->update([
            'model_type' => $request->model_type,
            'field_name' => $request->field_name,
            'role' => $request->role,
            'permission_level' => $request->permission_level,
            'is_active' => $request->has('is_active'),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('admin.field-permissions.index')
                         ->with('success', 'Field permission updated successfully.');
    }

    public function destroy(FieldPermission $permission)
    {
        $permission->delete();

        return redirect()->route('admin.field-permissions.index')
                         ->with('success', 'Field permission deleted successfully.');
    }
}