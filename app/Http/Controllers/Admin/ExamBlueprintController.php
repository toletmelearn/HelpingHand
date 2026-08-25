<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamBlueprint;
use Illuminate\Http\Request;

class ExamBlueprintController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin');
    }

    public function index($examId)
    {
        $exam = Exam::findOrFail($examId);
        $blueprints = $exam->blueprints;
        $totalWeightage = $blueprints->sum('weightage_percentage');

        return view('admin.exams.blueprints', compact('exam', 'blueprints', 'totalWeightage'));
    }

    public function store(Request $request, $examId)
    {
        $exam = Exam::findOrFail($examId);

        $validated = $request->validate([
            'topic_name' => 'required|string|max:255',
            'weightage_percentage' => 'required|numeric|min:0.01|max:100',
            'competency_level' => 'required|string|in:recall,understanding,application,analysis',
        ]);

        $currentWeightage = $exam->blueprints()->sum('weightage_percentage');
        if ($currentWeightage + $validated['weightage_percentage'] > 100.01) {
            return back()->with('error', 'Total blueprint weightage cannot exceed 100%. Current is ' . $currentWeightage . '%.');
        }

        $exam->blueprints()->create($validated);

        return back()->with('success', 'Blueprint topic mapped successfully.');
    }

    public function destroy($id)
    {
        $blueprint = ExamBlueprint::findOrFail($id);
        $blueprint->delete();

        return back()->with('success', 'Blueprint topic removed successfully.');
    }
}
