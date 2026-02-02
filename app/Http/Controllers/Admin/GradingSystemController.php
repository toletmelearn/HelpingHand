<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradingSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GradingSystemController extends Controller
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
        $gradingSystems = GradingSystem::orderBy('order')->orderBy('grade')->get();
        return view('admin.grading-systems.index', compact('gradingSystems'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.grading-systems.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'grade' => 'required|string|max:10',
            'min_percentage' => 'required|numeric|min:0|max:100',
            'max_percentage' => 'nullable|numeric|min:0|max:100',
            'grade_points' => 'nullable|numeric|min:0|max:10',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'order' => 'integer|min:0',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        GradingSystem::create($validator->validated());
        
        return redirect()->route('admin.grading-systems.index')
            ->with('success', 'Grading system created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $gradingSystem = GradingSystem::findOrFail($id);
        return view('admin.grading-systems.show', compact('gradingSystem'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $gradingSystem = GradingSystem::findOrFail($id);
        return view('admin.grading-systems.edit', compact('gradingSystem'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $gradingSystem = GradingSystem::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'grade' => 'required|string|max:10',
            'min_percentage' => 'required|numeric|min:0|max:100',
            'max_percentage' => 'nullable|numeric|min:0|max:100',
            'grade_points' => 'nullable|numeric|min:0|max:10',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'order' => 'integer|min:0',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        $gradingSystem->update($validator->validated());
        
        return redirect()->route('admin.grading-systems.index')
            ->with('success', 'Grading system updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $gradingSystem = GradingSystem::findOrFail($id);
        $gradingSystem->delete();
        
        return redirect()->route('admin.grading-systems.index')
            ->with('success', 'Grading system deleted successfully.');
    }
}
