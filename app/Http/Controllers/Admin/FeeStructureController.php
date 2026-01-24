<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeeStructure;
use App\Models\ClassManagement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeeStructureController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', FeeStructure::class);
        $feeStructures = FeeStructure::paginate(15);
        return view('admin.fee-structures.index', compact('feeStructures'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', FeeStructure::class);
        $classes = ClassManagement::all();
        return view('admin.fee-structures.create', compact('classes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', FeeStructure::class);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'class_name' => 'required|string|max:100',
            'term' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'frequency' => 'required|string|max:50',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from'
        ]);

        FeeStructure::create($request->all());

        return redirect()->route('admin.fee-structures.index')
                         ->with('success', 'Fee structure created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(FeeStructure $feeStructure)
    {
        $this->authorize('view', $feeStructure);
        return view('admin.fee-structures.show', compact('feeStructure'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FeeStructure $feeStructure)
    {
        $this->authorize('update', $feeStructure);
        $classes = ClassManagement::all();
        return view('admin.fee-structures.edit', compact('feeStructure', 'classes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FeeStructure $feeStructure)
    {
        $this->authorize('update', $feeStructure);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'class_name' => 'required|string|max:100',
            'term' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'frequency' => 'required|string|max:50',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from'
        ]);

        $feeStructure->update($request->all());

        return redirect()->route('admin.fee-structures.index')
                         ->with('success', 'Fee structure updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FeeStructure $feeStructure)
    {
        $this->authorize('delete', $feeStructure);
        
        $feeStructure->delete();

        return redirect()->route('admin.fee-structures.index')
                         ->with('success', 'Fee structure deleted successfully.');
    }
}
