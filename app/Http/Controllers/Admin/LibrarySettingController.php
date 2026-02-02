<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LibrarySetting;

class LibrarySettingController extends Controller
{
    public function index()
    {
        $setting = LibrarySetting::getSetting();
        return view('admin.library-settings.index', compact('setting'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'default_issue_days' => 'required|integer|min:1|max:365',
            'fine_per_day' => 'required|numeric|min:0',
            'low_stock_threshold' => 'required|integer|min:0',
            'auto_reminder_enabled' => 'required|boolean',
        ]);

        $setting = LibrarySetting::getSetting();
        $setting->update($request->all());

        return redirect()->route('library-settings.index')
            ->with('success', 'Library settings updated successfully!');
    }
}
