<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdmitCardFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdmitCardFormatController extends Controller
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
        $this->authorize('viewAny', AdmitCardFormat::class);
        $formats = AdmitCardFormat::orderBy('created_at', 'desc')->paginate(10);
        return view('admin.admit-cards.formats.index', compact('formats'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', AdmitCardFormat::class);
        return view('admin.admit-cards.formats.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', AdmitCardFormat::class);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'header_html' => 'nullable|string',
            'body_html' => 'nullable|string',
            'footer_html' => 'nullable|string',
            'background_image' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $format = AdmitCardFormat::create([
            'name' => $request->name,
            'header_html' => $request->header_html,
            'body_html' => $request->body_html,
            'footer_html' => $request->footer_html,
            'background_image' => $request->background_image,
            'is_active' => $request->has('is_active'),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.admit-card-formats.index')
                         ->with('success', 'Admit card format created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(AdmitCardFormat $admitCardFormat)
    {
        $this->authorize('view', $admitCardFormat);
        return view('admin.admit-cards.formats.show', compact('admitCardFormat'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AdmitCardFormat $admitCardFormat)
    {
        $this->authorize('update', $admitCardFormat);
        return view('admin.admit-cards.formats.edit', compact('admitCardFormat'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AdmitCardFormat $admitCardFormat)
    {
        $this->authorize('update', $admitCardFormat);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'header_html' => 'nullable|string',
            'body_html' => 'nullable|string',
            'footer_html' => 'nullable|string',
            'background_image' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $admitCardFormat->update([
            'name' => $request->name,
            'header_html' => $request->header_html,
            'body_html' => $request->body_html,
            'footer_html' => $request->footer_html,
            'background_image' => $request->background_image,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('admin.admit-card-formats.index')
                         ->with('success', 'Admit card format updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AdmitCardFormat $admitCardFormat)
    {
        $this->authorize('delete', $admitCardFormat);
        
        $admitCardFormat->delete();

        return redirect()->route('admin.admit-card-formats.index')
                         ->with('success', 'Admit card format deleted successfully.');
    }
}
