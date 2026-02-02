<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExaminationPattern;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExaminationPatternController extends Controller
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
        $examinationPatterns = ExaminationPattern::orderBy('sort_order')->orderBy('name')->get();
        return view('admin.examination-patterns.index', compact('examinationPatterns'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.examination-patterns.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:examination_patterns,code',
            'pattern_config' => 'required|array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'description' => 'nullable|string|max:500',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        $data = $validator->validated();
        // Handle the pattern_config array separately to ensure it's properly stored as JSON
        $data['pattern_config'] = $request->input('pattern_config', []);
        
        $examinationPattern = ExaminationPattern::create($data);
        
        // If this pattern is set as default, unset the default flag for other patterns
        if ($examinationPattern->is_default) {
            ExaminationPattern::where('id', '!=', $examinationPattern->id)
                ->update(['is_default' => false]);
        }
        
        return redirect()->route('admin.examination-patterns.index')
            ->with('success', 'Examination pattern created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $examinationPattern = ExaminationPattern::findOrFail($id);
        return view('admin.examination-patterns.show', compact('examinationPattern'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $examinationPattern = ExaminationPattern::findOrFail($id);
        return view('admin.examination-patterns.edit', compact('examinationPattern'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $examinationPattern = ExaminationPattern::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:examination_patterns,code,' . $id,
            'pattern_config' => 'required|array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'description' => 'nullable|string|max:500',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        $data = $validator->validated();
        // Handle the pattern_config array separately to ensure it's properly stored as JSON
        $data['pattern_config'] = $request->input('pattern_config', []);
        
        $examinationPattern->update($data);
        
        // If this pattern is set as default, unset the default flag for other patterns
        if ($examinationPattern->is_default) {
            ExaminationPattern::where('id', '!=', $examinationPattern->id)
                ->update(['is_default' => false]);
        }
        
        return redirect()->route('admin.examination-patterns.index')
            ->with('success', 'Examination pattern updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $examinationPattern = ExaminationPattern::findOrFail($id);
        
        // Prevent deletion if this is the default pattern
        if ($examinationPattern->is_default) {
            return redirect()->route('admin.examination-patterns.index')
                ->with('error', 'Cannot delete the default examination pattern. Please set another pattern as default first.');
        }
        
        $examinationPattern->delete();
        
        return redirect()->route('admin.examination-patterns.index')
            ->with('success', 'Examination pattern deleted successfully.');
    }
}