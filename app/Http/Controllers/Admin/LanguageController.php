<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Translation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;

class LanguageController extends Controller
{
    public function index(Request $request)
    {
        $languages = Language::orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10);
            
        return view('admin.languages.index', compact('languages'));
    }
    
    public function create()
    {
        return view('admin.languages.create');
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|unique:languages,code|max:10',
            'flag_icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);
        
        // If setting as default, unset other defaults
        if ($request->is_default) {
            Language::where('is_default', true)->update(['is_default' => false]);
        }
        
        $language = Language::create([
            'name' => $request->name,
            'code' => strtolower($request->code),
            'flag_icon' => $request->flag_icon,
            'is_active' => $request->is_active ?? true,
            'is_default' => $request->is_default ?? false,
            'sort_order' => $request->sort_order ?? 0,
        ]);
        
        return redirect()->route('admin.languages.index')
            ->with('success', 'Language created successfully.');
    }
    
    public function show(Language $language)
    {
        $translations = $language->translations()
            ->orderBy('module')
            ->orderBy('key')
            ->paginate(20);
            
        return view('admin.languages.show', compact('language', 'translations'));
    }
    
    public function edit(Language $language)
    {
        return view('admin.languages.edit', compact('language'));
    }
    
    public function update(Request $request, Language $language)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|unique:languages,code,' . $language->id . '|max:10',
            'flag_icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);
        
        // If setting as default, unset other defaults
        if ($request->is_default) {
            Language::where('is_default', true)->where('id', '!=', $language->id)->update(['is_default' => false]);
        }
        
        $language->update([
            'name' => $request->name,
            'code' => strtolower($request->code),
            'flag_icon' => $request->flag_icon,
            'is_active' => $request->is_active ?? true,
            'is_default' => $request->is_default ?? false,
            'sort_order' => $request->sort_order ?? $language->sort_order,
        ]);
        
        return redirect()->route('admin.languages.index')
            ->with('success', 'Language updated successfully.');
    }
    
    public function destroy(Language $language)
    {
        if ($language->is_default) {
            return redirect()->back()->with('error', 'Cannot delete default language.');
        }
        
        if ($language->translations()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete language with existing translations. Delete translations first.');
        }
        
        $language->delete();
        
        return redirect()->route('admin.languages.index')
            ->with('success', 'Language deleted successfully.');
    }
    
    // Translation Management
    public function translations(Language $language)
    {
        $translations = $language->translations()
            ->orderBy('module')
            ->orderBy('key')
            ->paginate(20);
            
        $modules = ['general', 'admin', 'student', 'teacher', 'parent', 'finance', 'academics'];
        
        return view('admin.languages.translations', compact('language', 'translations', 'modules'));
    }
    
    public function storeTranslation(Request $request, Language $language)
    {
        $request->validate([
            'key' => 'required|string|max:255',
            'value' => 'required|string',
            'module' => 'required|string|in:general,admin,student,teacher,parent,finance,academics',
            'is_published' => 'boolean',
        ]);
        
        // Check if translation already exists
        $existing = Translation::where('language_id', $language->id)
            ->where('key', $request->key)
            ->first();
            
        if ($existing) {
            return redirect()->back()->with('error', 'Translation key already exists for this language.');
        }
        
        Translation::create([
            'language_id' => $language->id,
            'key' => $request->key,
            'value' => $request->value,
            'module' => $request->module,
            'is_published' => $request->is_published ?? true,
        ]);
        
        return redirect()->back()->with('success', 'Translation added successfully.');
    }
    
    public function updateTranslation(Request $request, Language $language, Translation $translation)
    {
        $request->validate([
            'value' => 'required|string',
            'is_published' => 'boolean',
        ]);
        
        $translation->update([
            'value' => $request->value,
            'is_published' => $request->is_published ?? $translation->is_published,
        ]);
        
        return redirect()->back()->with('success', 'Translation updated successfully.');
    }
    
    public function destroyTranslation(Language $language, Translation $translation)
    {
        $translation->delete();
        
        return redirect()->back()->with('success', 'Translation deleted successfully.');
    }
    
    // Language Switcher
    public function switchLanguage(Request $request, $code)
    {
        $language = Language::where('code', $code)->where('is_active', true)->first();
        
        if (!$language) {
            return redirect()->back()->with('error', 'Language not available.');
        }
        
        // Store in session
        session(['app_language' => $language->code]);
        App::setLocale($language->code);
        
        return redirect()->back()->with('success', 'Language switched to ' . $language->name);
    }
    
    // Export/Import
    public function exportTranslations(Language $language)
    {
        $translations = $language->translations()->get();
        
        $exportData = $translations->map(function ($translation) {
            return [
                'key' => $translation->key,
                'value' => $translation->value,
                'module' => $translation->module,
            ];
        });
        
        return response()->json($exportData);
    }
    
    public function importTranslations(Request $request, Language $language)
    {
        $request->validate([
            'file' => 'required|file|mimes:json,csv,txt',
        ]);
        
        // Implementation for file import would go here
        return redirect()->back()->with('success', 'Import functionality will be implemented.');
    }
}