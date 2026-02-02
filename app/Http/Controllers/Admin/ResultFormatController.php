<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResultFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ResultFormatController extends Controller
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
        $resultFormats = ResultFormat::orderBy('sort_order')->orderBy('name')->get();
        return view('admin.result-formats.index', compact('resultFormats'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.result-formats.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:result_formats,code',
            'template_html' => 'required|string',
            'fields' => 'nullable|array',
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
        // Handle the fields array separately to ensure it's properly stored as JSON
        $data['fields'] = $request->input('fields', []);
        
        $resultFormat = ResultFormat::create($data);
        
        // If this format is set as default, unset the default flag for other formats
        if ($resultFormat->is_default) {
            ResultFormat::where('id', '!=', $resultFormat->id)
                ->update(['is_default' => false]);
        }
        
        return redirect()->route('admin.result-formats.index')
            ->with('success', 'Result format created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $resultFormat = ResultFormat::findOrFail($id);
        return view('admin.result-formats.show', compact('resultFormat'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $resultFormat = ResultFormat::findOrFail($id);
        return view('admin.result-formats.edit', compact('resultFormat'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $resultFormat = ResultFormat::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:result_formats,code,' . $id,
            'template_html' => 'required|string',
            'fields' => 'nullable|array',
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
        // Handle the fields array separately to ensure it's properly stored as JSON
        $data['fields'] = $request->input('fields', []);
        
        $resultFormat->update($data);
        
        // If this format is set as default, unset the default flag for other formats
        if ($resultFormat->is_default) {
            ResultFormat::where('id', '!=', $resultFormat->id)
                ->update(['is_default' => false]);
        }
        
        return redirect()->route('admin.result-formats.index')
            ->with('success', 'Result format updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $resultFormat = ResultFormat::findOrFail($id);
        
        // Prevent deletion if this is the default format
        if ($resultFormat->is_default) {
            return redirect()->route('admin.result-formats.index')
                ->with('error', 'Cannot delete the default result format. Please set another format as default first.');
        }
        
        $resultFormat->delete();
        
        return redirect()->route('admin.result-formats.index')
            ->with('success', 'Result format deleted successfully.');
    }
}
