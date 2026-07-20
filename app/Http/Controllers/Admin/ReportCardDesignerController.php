<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportCardTemplate;
use App\Models\PromotionRule;
use App\Models\Student;
use App\Services\Academic\AcademicRankerService;
use Illuminate\Http\Request;

class ReportCardDesignerController extends Controller
{
    protected AcademicRankerService $rankerService;

    public function __construct(AcademicRankerService $rankerService)
    {
        $this->rankerService = $rankerService;
    }

    public function index()
    {
        $templates = ReportCardTemplate::all();
        $promotionRules = PromotionRule::all();

        return view('admin.exams.report_designer', compact('templates', 'promotionRules'));
    }

    public function storeTemplate(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'show_logo' => 'boolean',
            'show_attendance' => 'boolean',
            'show_grades' => 'boolean',
            'remarks_box' => 'boolean',
        ]);

        $config = [
            'show_logo' => $request->has('show_logo'),
            'show_attendance' => $request->has('show_attendance'),
            'show_grades' => $request->has('show_grades'),
            'remarks_box' => $request->has('remarks_box'),
        ];

        ReportCardTemplate::create([
            'name' => $validated['name'],
            'layout_config' => $config,
            'is_active' => true,
        ]);

        return back()->with('success', 'Report card template layout configuration saved.');
    }

    public function storePromotionRule(Request $request)
    {
        $validated = $request->validate([
            'class_name' => 'required|string',
            'min_overall_percentage' => 'required|numeric|min:0|max:100',
            'max_failed_subjects' => 'required|integer|min:0',
            'min_attendance_percentage' => 'required|numeric|min:0|max:100',
            'academic_year' => 'required|string',
        ]);

        PromotionRule::create($validated);

        return back()->with('success', 'Promotion policy parameters saved.');
    }

    public function checkPromotion(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'academic_year' => 'required|string',
        ]);

        // Compute rankings
        $student = Student::findOrFail($validated['student_id']);
        $className = $student->class ?: 'Grade 10';
        $this->rankerService->calculateRanks($className, $validated['academic_year']);

        // Check promotion rule matching
        $result = $this->rankerService->evaluatePromotion($validated['student_id'], $validated['academic_year']);

        return back()->with('promotion_result', [
            'student' => $student->name,
            'admission_no' => $student->admission_no,
            'promoted' => $result['promoted'],
            'reason' => $result['reason'],
            'stats' => $result['stats']
        ]);
    }
}
