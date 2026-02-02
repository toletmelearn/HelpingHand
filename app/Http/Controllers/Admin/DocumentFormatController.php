<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentFormat;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentFormatController extends Controller
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
        $documentFormats = DocumentFormat::orderBy('name')->paginate(15);
        return view('admin.document-formats.index', compact('documentFormats'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.document-formats.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:document_formats,name',
            'type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'template_content' => 'nullable|array',
            'css_styles' => 'nullable|array',
            'header_content' => 'nullable|string',
            'footer_content' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'page_size' => 'required|string|max:20',
            'orientation' => 'required|in:portrait,landscape',
            'margin_top' => 'required|numeric|min:0|max:100',
            'margin_bottom' => 'required|numeric|min:0|max:100',
            'margin_left' => 'required|numeric|min:0|max:100',
            'margin_right' => 'required|numeric|min:0|max:100',
        ]);

        // Handle template_content and css_styles arrays
        if (isset($validated['template_content'])) {
            $validated['template_content'] = json_encode($validated['template_content']);
        }
        
        if (isset($validated['css_styles'])) {
            $validated['css_styles'] = json_encode($validated['css_styles']);
        }

        $documentFormat = DocumentFormat::create($validated);

        // If this is set as default, make sure to unset other defaults
        if ($request->filled('is_default') && $request->boolean('is_default')) {
            DocumentFormat::setAsDefault($documentFormat->id);
        }

        return redirect()->route('admin.document-formats.index')
                        ->with('success', 'Document format created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(DocumentFormat $documentFormat)
    {
        return view('admin.document-formats.show', compact('documentFormat'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DocumentFormat $documentFormat)
    {
        return view('admin.document-formats.edit', compact('documentFormat'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DocumentFormat $documentFormat)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('document_formats')->ignore($documentFormat->id)],
            'type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'template_content' => 'nullable|array',
            'css_styles' => 'nullable|array',
            'header_content' => 'nullable|string',
            'footer_content' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'page_size' => 'required|string|max:20',
            'orientation' => 'required|in:portrait,landscape',
            'margin_top' => 'required|numeric|min:0|max:100',
            'margin_bottom' => 'required|numeric|min:0|max:100',
            'margin_left' => 'required|numeric|min:0|max:100',
            'margin_right' => 'required|numeric|min:0|max:100',
        ]);

        // Handle template_content and css_styles arrays
        if (isset($validated['template_content'])) {
            $validated['template_content'] = json_encode($validated['template_content']);
        }
        
        if (isset($validated['css_styles'])) {
            $validated['css_styles'] = json_encode($validated['css_styles']);
        }

        $documentFormat->update($validated);

        // If this is set as default, make sure to unset other defaults
        if ($request->filled('is_default') && $request->boolean('is_default')) {
            DocumentFormat::setAsDefault($documentFormat->id);
        }

        return redirect()->route('admin.document-formats.index')
                        ->with('success', 'Document format updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DocumentFormat $documentFormat)
    {
        // Prevent deletion if this is the default format
        if ($documentFormat->is_default) {
            return redirect()->route('admin.document-formats.index')
                            ->with('error', 'Cannot delete the default document format.');
        }

        $documentFormat->delete();

        return redirect()->route('admin.document-formats.index')
                        ->with('success', 'Document format deleted successfully.');
    }

    /**
     * Set the specified document format as default
     */
    public function setDefault(Request $request, DocumentFormat $documentFormat)
    {
        DocumentFormat::setAsDefault($documentFormat->id);

        return redirect()->route('admin.document-formats.index')
                        ->with('success', 'Default document format updated successfully.');
    }
}