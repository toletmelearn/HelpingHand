<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolHoliday;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolHolidayController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin');
    }

    public function index()
    {
        $holidays = SchoolHoliday::orderBy('start_date')->get();

        return view('admin.school-holidays.index', compact('holidays'));
    }

    public function create()
    {
        return view('admin.school-holidays.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_year' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'holiday_name' => [
                'required', 'string', 'max:255',
                Rule::unique('school_holidays')->where(fn ($q) => $q->where('academic_year', $request->academic_year)),
            ],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'holiday_type' => 'required|in:festival,leave,special,exam_break',
            'description' => 'nullable|string',
        ]);

        SchoolHoliday::create($validated + ['created_by' => auth()->id()]);

        return redirect()->route('admin.school-holidays.index')->with('success', 'Holiday created successfully.');
    }

    public function edit(SchoolHoliday $schoolHoliday)
    {
        return view('admin.school-holidays.edit', ['holiday' => $schoolHoliday]);
    }

    public function update(Request $request, SchoolHoliday $schoolHoliday)
    {
        $validated = $request->validate([
            'holiday_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'holiday_type' => 'required|in:festival,leave,special,exam_break',
            'description' => 'nullable|string',
        ]);

        $schoolHoliday->update($validated);

        return redirect()->route('admin.school-holidays.index')->with('success', 'Holiday updated successfully.');
    }

    public function destroy(SchoolHoliday $schoolHoliday)
    {
        $schoolHoliday->delete();

        return redirect()->route('admin.school-holidays.index')->with('success', 'Holiday deleted successfully.');
    }
}
