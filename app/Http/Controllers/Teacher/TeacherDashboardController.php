<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Services\TeacherAcademicService;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TimetableSlot;

class TeacherDashboardController extends Controller
{
    /**
     * Show the teacher dashboard using single source of truth.
     * 
     * IDENTITY MAPPING:
     * - Authenticated object: TeacherLogin (from teacher_logins table)
     * - Linked business record: Teacher (from teachers table)
     * - Link field: teacher_logins.teacher_id -> teachers.id
     * - Assignments table: teacher_class_subject_assignments (NOT teacher_subjects)
     */
    public function index()
    {
        try {

            $teacherLogin = \Illuminate\Support\Facades\Auth::guard('teacher')->user();

            $teacher = null;
            $assignedClasses = collect();
            $assignedSubjects = [];
            $uploadedResults = 0;
            $recentExams = collect();
            $notices = collect();
            $homeworks = collect();
            $classTeacherAssignments = collect();
            $isExamHead = false;
            $assignments = collect();

            if ($teacherLogin) {

                // Get the actual teacher master record using teacher_id from TeacherLogin
                $teacher = \App\Models\Teacher::find($teacherLogin->teacher_id);

                if ($teacher) {

                    // Check if teacher is exam head
                    $isExamHead = $teacherLogin->isExamHead();

                    // Get class + subject assignments from the CORRECT table
                    // Use TeacherClassSubjectAssignment model, NOT teacher_subjects table
                    $assignments = \App\Models\TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
                        ->with(['schoolClass', 'section', 'subject'])
                        ->get();

                    // Extract unique classes
                    $assignedClasses = $assignments->pluck('schoolClass')->filter()->unique('id');

                    // Extract unique subjects
                    $assignedSubjects = $assignments->pluck('subject')->filter()->unique('id')->pluck('name')->toArray();

                    // Get class teacher assignments
                    $classTeacherAssignments = $assignments->where('is_class_teacher', true);

                    // Exams created by teacher (use teacher->id, NOT teacherLogin->id)
                    $recentExams = \Illuminate\Support\Facades\DB::table('exams')
                        ->where('created_by', $teacher->id)
                        ->latest()
                        ->take(5)
                        ->get();

                    // Results uploaded by teacher (use teacher->id)
                    // FIXED: Table name is 'results' not 'marks'. Column is
                    // uploaded_by_teacher_id -- results has no teacher_id
                    // column at all, so this previously threw on every load,
                    // was swallowed by the catch below, and silently wiped
                    // out every other section of this dashboard with it.
                    $uploadedResults = \Illuminate\Support\Facades\DB::table('results')
                        ->where('uploaded_by_teacher_id', $teacher->id)
                        ->count();

                    // Notices: there is no notices table/system anywhere in
                    // this schema (only the unrelated homework_notices
                    // feature below) -- this query previously threw on every
                    // load, unconditionally, and (being inside the same try
                    // block) took every other section of this dashboard down
                    // with it. Matches AdminDashboardController's own
                    // 'notices_count' => 5 placeholder for the same
                    // not-yet-built concept -- not invented here either.
                    $notices = collect();

                        // Homework assigned by teacher (use teacher->id)
                    // FIXED: Table name is 'homework_notices' not 'homework'
                    // FIXED: Column name is 'assigned_by' not 'teacher_id'
                    $homeworks = \Illuminate\Support\Facades\DB::table('homework_notices')
                        ->where('assigned_by', $teacher->id)
                        ->latest()
                        ->take(5)
                        ->get();

                    // Fetch invigilator/relieving duties in their own try/catch:
                    // exam_invigilator_duties and exam_relieving_duties have no
                    // migration anywhere in this repo (they exist only in the
                    // live dev DB), so this throws on a fresh install/CI/test
                    // run and was previously wiping out every other section of
                    // this dashboard with it. Same isolation pattern as
                    // todaysPeriods below.
                    $invigilatorDuties = collect();
                    $relievingDuties = collect();
                    try {
                        $invigilatorDuties = \App\Models\ExamInvigilatorDuty::where('teacher_id', $teacher->id)
                            ->with('exam')
                            ->get();

                        $relievingDuties = \App\Models\ExamRelievingDuty::where('teacher_id', $teacher->id)
                            ->with('exam')
                            ->get();
                    } catch (\Exception $e) {
                        $invigilatorDuties = collect();
                        $relievingDuties = collect();
                    }

                    // Fetch assigned admission enquiries for this counsellor
                    $assignedEnquiries = collect();
                    if ($teacher->user_id) {
                        $assignedEnquiries = \App\Models\AdmissionEnquiry::where('counsellor_id', $teacher->user_id)
                            ->latest()
                            ->take(5)
                            ->get();
                    }
                }
            }

        } catch (\Exception $e) {

            $teacher = null;
            $assignedClasses = collect();
            $assignedSubjects = [];
            $uploadedResults = 0;
            $recentExams = collect();
            $notices = collect();
            $homeworks = collect();
            $classTeacherAssignments = collect();
            $isExamHead = false;
            $assignments = collect();
            $invigilatorDuties = collect();
            $relievingDuties = collect();
            $assignedEnquiries = collect();
        }

        // Today's periods -- Hardening pass item 4: mirrors
        // Parent\TimetableController::today()'s exact shape (published
        // only, today's day_of_week), scoped to this teacher as either the
        // primary or co-teacher. Deliberately computed in its OWN
        // try/catch, independent of the block above: that block wraps
        // several unrelated, pre-existing queries (exams/results/
        // notices/homework/duties/enquiries) behind a single catch-all, so
        // a failure in any one of THOSE must never silently take this card
        // down too, and a failure here must never take the rest of the
        // dashboard down either.
        $todaysPeriods = collect();
        try {
            $teacherLoginForToday = \Illuminate\Support\Facades\Auth::guard('teacher')->user();
            $teacherForToday = $teacherLoginForToday ? \App\Models\Teacher::find($teacherLoginForToday->teacher_id) : null;
            if ($teacherForToday) {
                $todaysPeriods = $this->todaysPeriodsForTeacher($teacherForToday->id);
            }
        } catch (\Exception $e) {
            $todaysPeriods = collect();
        }

        // Universal safe defaults - ensure all variables exist
        $teacher = $teacher ?? null;
        $assignedClasses = isset($assignedClasses) ? collect($assignedClasses) : collect();
        $assignedSubjects = $assignedSubjects ?? [];
        $uploadedResults = $uploadedResults ?? 0;
        $isExamHead = $isExamHead ?? false;
        $recentExams = isset($recentExams) ? collect($recentExams) : collect();
        $notices = isset($notices) ? collect($notices) : collect();
        $homeworks = isset($homeworks) ? collect($homeworks) : collect();
        $classTeacherAssignments = isset($classTeacherAssignments) ? collect($classTeacherAssignments) : collect();
        $assignments = isset($assignments) ? collect($assignments) : collect();
        $invigilatorDuties = isset($invigilatorDuties) ? collect($invigilatorDuties) : collect();
        $relievingDuties = isset($relievingDuties) ? collect($relievingDuties) : collect();
        $assignedEnquiries = isset($assignedEnquiries) ? collect($assignedEnquiries) : collect();
        $todaysPeriods = isset($todaysPeriods) ? collect($todaysPeriods) : collect();

        return view('teacher.dashboard', compact(
            'teacher',
            'assignedClasses',
            'assignedSubjects',
            'uploadedResults',
            'isExamHead',
            'recentExams',
            'notices',
            'homeworks',
            'classTeacherAssignments',
            'assignments',
            'invigilatorDuties',
            'relievingDuties',
            'assignedEnquiries',
            'todaysPeriods'
        ));
    }

    /**
     * Today's periods for this teacher, as either the primary or
     * co-teacher -- published slots only, same shape and same "published
     * is the only version anyone outside draft review ever sees" rule
     * every other timetable reader in the app follows.
     *
     * UAT Step 5, Scenario 15: an absent-and-substituted teacher's own
     * Today card showed zero acknowledgement of the substitution -- the
     * query simply never fetched it. Mirrors the same
     * date+bell_timing_id-keyed lookup TeacherTimetableController::index()
     * already uses for the weekly view's "Covered by X" overlay, narrowed
     * to today's date instead of the whole week, and attached as a dynamic
     * ->arrangement property on the existing TimetableSlot rows so the
     * view's current field access (bellTiming/schoolClass/subject) is
     * untouched.
     */
    private function todaysPeriodsForTeacher(int $teacherId)
    {
        $dayOfWeek = now()->format('l');
        $today = now()->toDateString();

        $periods = TimetableSlot::published()
            ->where(fn ($q) => $q->where('teacher_id', $teacherId)->orWhere('co_teacher_id', $teacherId))
            ->whereHas('bellTiming', fn ($q) => $q->where('day_of_week', $dayOfWeek))
            ->with(['subject', 'schoolClass', 'section', 'bellTiming'])
            ->get()
            ->filter(fn (TimetableSlot $s) => $s->bellTiming !== null)
            ->sortBy(fn (TimetableSlot $s) => $s->bellTiming->order_index)
            ->values();

        $arrangements = \App\Models\TeacherSubstitution::where('absent_teacher_id', $teacherId)
            ->whereIn('bell_timing_id', $periods->pluck('bell_timing_id')->unique())
            ->whereDate('substitution_date', $today)
            ->where('status', '!=', 'cancelled')
            ->with('substituteTeacher')
            ->get()
            ->keyBy('bell_timing_id');

        $periods->each(function (TimetableSlot $period) use ($arrangements) {
            $period->arrangement = $arrangements->get($period->bell_timing_id);
        });

        return $periods;
    }
}
