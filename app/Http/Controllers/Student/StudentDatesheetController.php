<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DatesheetEntry;
use Illuminate\Support\Facades\Auth;

/**
 * Reuses the exact ownership pattern StudentResultController already uses
 * (confirmed IDOR-safe this session): scoped to the authenticated
 * student's own school_class_id/section_id, published entries only.
 */
class StudentDatesheetController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $student = Auth::user()->student;

        if (! $student) {
            return redirect()->back()->with('error', 'No student record linked to this account.');
        }

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

        return view('student.datesheets.index', compact('entries'));
    }
}
