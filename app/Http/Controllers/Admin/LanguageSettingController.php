<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LanguageSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class LanguageSettingController extends Controller
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
        $languages = LanguageSetting::orderBy('name')->paginate(20);
        
        return view('admin.language-settings.index', compact('languages'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.language-settings.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'locale' => 'required|unique:language_settings,locale',
            'name' => 'required|string|max:100',
            'flag' => 'nullable|string|max:50',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);
        
        $language = LanguageSetting::create([
            'locale' => $request->locale,
            'name' => $request->name,
            'flag' => $request->flag,
            'is_default' => $request->is_default ?? false,
            'is_active' => $request->is_active ?? true,
            'created_by' => Auth::id(),
        ]);
        
        // If this language is set as default, unset other defaults
        if ($request->is_default) {
            LanguageSetting::where('id', '!=', $language->id)
                           ->update(['is_default' => false]);
        }
        
        Cache::forget('active_languages');
        Cache::forget('default_language');
        
        return redirect()->route('admin.language-settings.index')
                         ->with('success', 'Language added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(LanguageSetting $languageSetting)
    {
        return view('admin.language-settings.show', compact('languageSetting'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LanguageSetting $languageSetting)
    {
        return view('admin.language-settings.edit', compact('languageSetting'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LanguageSetting $languageSetting)
    {
        $request->validate([
            'locale' => 'required|unique:language_settings,locale,' . $languageSetting->id,
            'name' => 'required|string|max:100',
            'flag' => 'nullable|string|max:50',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);
        
        $languageSetting->update([
            'locale' => $request->locale,
            'name' => $request->name,
            'flag' => $request->flag,
            'is_default' => $request->is_default ?? false,
            'is_active' => $request->is_active ?? true,
            'updated_by' => Auth::id(),
        ]);
        
        // If this language is set as default, unset other defaults
        if ($request->is_default) {
            LanguageSetting::where('id', '!=', $languageSetting->id)
                           ->update(['is_default' => false]);
        }
        
        Cache::forget('active_languages');
        Cache::forget('default_language');
        
        return redirect()->route('admin.language-settings.index')
                         ->with('success', 'Language updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LanguageSetting $languageSetting)
    {
        if ($languageSetting->is_default) {
            return redirect()->back()->withErrors(['error' => 'Cannot delete the default language.']);
        }
        
        $languageSetting->delete();
        
        Cache::forget('active_languages');
        Cache::forget('default_language');
        
        return redirect()->route('admin.language-settings.index')
                         ->with('success', 'Language deleted successfully.');
    }
    
    /**
     * Set language as default
     */
    public function setDefault(LanguageSetting $languageSetting)
    {
        LanguageSetting::where('id', '!=', $languageSetting->id)
                       ->update(['is_default' => false]);
        
        $languageSetting->update(['is_default' => true]);
        
        Cache::forget('active_languages');
        Cache::forget('default_language');
        
        return redirect()->back()->with('success', 'Default language updated successfully.');
    }
    
    /**
     * Toggle language status
     */
    public function toggleStatus(LanguageSetting $languageSetting)
    {
        $languageSetting->update(['is_active' => !$languageSetting->is_active]);
        
        Cache::forget('active_languages');
        
        return redirect()->back()->with('success', 'Language status updated successfully.');
    }
}
