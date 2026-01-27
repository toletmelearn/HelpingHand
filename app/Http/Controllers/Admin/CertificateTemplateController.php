<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CertificateTemplateController extends Controller
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
        $templates = CertificateTemplate::with('creator', 'updater')->latest()->paginate(20);
        
        return view('admin.certificate-templates.index', compact('templates'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.certificate-templates.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:tc,bonafide,character,experience',
            'template_content' => 'required|string',
            'template_variables' => 'required|array',
            'template_variables.*' => 'string',
            'is_default' => 'boolean',
        ]);
        
        $template = CertificateTemplate::create([
            'name' => $request->name,
            'type' => $request->type,
            'template_content' => $request->template_content,
            'template_variables' => $request->template_variables,
            'is_default' => $request->is_default ?? false,
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);
        
        // If this template is set as default, unset other defaults for the same type
        if ($request->is_default) {
            CertificateTemplate::where('type', $request->type)
                             ->where('id', '!=', $template->id)
                             ->update(['is_default' => false]);
        }
        
        return redirect()->route('admin.certificate-templates.index')
                         ->with('success', 'Certificate template created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(CertificateTemplate $certificateTemplate)
    {
        return view('admin.certificate-templates.show', compact('certificateTemplate'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CertificateTemplate $certificateTemplate)
    {
        return view('admin.certificate-templates.edit', compact('certificateTemplate'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CertificateTemplate $certificateTemplate)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:tc,bonafide,character,experience',
            'template_content' => 'required|string',
            'template_variables' => 'required|array',
            'template_variables.*' => 'string',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);
        
        $certificateTemplate->update([
            'name' => $request->name,
            'type' => $request->type,
            'template_content' => $request->template_content,
            'template_variables' => $request->template_variables,
            'is_default' => $request->is_default ?? false,
            'is_active' => $request->is_active ?? false,
            'updated_by' => Auth::id(),
        ]);
        
        // If this template is set as default, unset other defaults for the same type
        if ($request->is_default) {
            CertificateTemplate::where('type', $request->type)
                             ->where('id', '!=', $certificateTemplate->id)
                             ->update(['is_default' => false]);
        }
        
        return redirect()->route('admin.certificate-templates.index')
                         ->with('success', 'Certificate template updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CertificateTemplate $certificateTemplate)
    {
        if ($certificateTemplate->is_default) {
            return back()->withErrors(['error' => 'Cannot delete a default template. Set another template as default first.']);
        }
        
        $certificateTemplate->delete();
        
        return redirect()->route('admin.certificate-templates.index')
                         ->with('success', 'Certificate template deleted successfully.');
    }
    
    /**
     * Set template as default
     */
    public function setDefault(CertificateTemplate $certificateTemplate)
    {
        $certificateTemplate->update(['is_default' => true]);
        
        // Unset other defaults for the same type
        CertificateTemplate::where('type', $certificateTemplate->type)
                         ->where('id', '!=', $certificateTemplate->id)
                         ->update(['is_default' => false]);
        
        return redirect()->back()->with('success', 'Template set as default successfully.');
    }
}
