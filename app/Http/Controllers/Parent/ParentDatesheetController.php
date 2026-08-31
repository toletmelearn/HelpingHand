<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\DatesheetEntry;
use Illuminate\Support\Facades\Auth;

/**
 * Reuses the exact ownership pattern ParentExamPaperController already
 * uses (confirmed safe this session): $parent->student, class/section
 * scoped, published entries only.
 */
class ParentDatesheetController extends Controller
{
    public function index()
    {
        $parent = Auth::guard('parent')->user();

        if (! $parent || ! $parent->student) {
            return redirect()->back()->with('error', 'No student associated with this parent account.');
        }

        $student = $parent->student;

        $entries = DatesheetEntry::where('school_class_id', $student->school_class_id)
            ->where(function ($q) use ($student) {
                $q->whereNull('section_id');
                if ($student->section_id) {
                    $q->orWhere('section_id', $student->section_id);
                }
            })
            ->whereHas('datesheet', fn ($q) => $q->where('status', 'published'))
            ->with(['subject', 'datesheet'])
            ->orderBy('exam_date')
            ->get();

        return view('parent.datesheets.index', compact('entries', 'student'));
    }
}
