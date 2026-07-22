<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DefaulterStage;
use App\Models\DefaulterLog;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Models\LegacyClassMap;
use App\Notifications\DefaulterActionNotification;
use App\Services\DefaulterService;
use App\Services\ExamRestrictionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DefaulterController extends Controller
{
    protected $defaulterService;

    public function __construct(DefaulterService $defaulterService)
    {
        $this->middleware('auth');
        // communicate-defaulters (Class Teacher / Receptionist) can see the
        // registry and send communications, but not promote/override a
        // stage or grant an exam exception -- those stay manage-defaulters/
        // override-exam-restriction only (Admin/Principal/Accountant).
        $this->middleware('permission:view-defaulters,manage-defaulters,communicate-defaulters')->only(['dashboard', 'index', 'history']);
        // Export is Admin/Clerk/Accountant only -- deliberately excludes
        // communicate-defaulters (Class Teacher/Receptionist), who can act
        // on the registry but shouldn't bulk-download it.
        $this->middleware('permission:view-defaulters,manage-defaulters')->only(['export']);
        $this->middleware('permission:manage-defaulters,communicate-defaulters')->only(['takeAction', 'bulkAction']);
        $this->middleware('permission:manage-defaulters')->only(['override']);
        $this->middleware('permission:override-exam-restriction')->only(['grantExamOverride', 'revokeExamOverride']);
        $this->defaulterService = $defaulterService;
    }

    /**
     * Class IDs this user is scoped to, or null for "no scoping, see
     * everyone" (Admin/Principal/Accountant/Receptionist). Any user who
     * doesn't hold the broad manage-defaulters permission but has a linked
     * Teacher record is scoped to that teacher's own assigned classes --
     * covers both the 'class-teacher' and plain 'teacher' roles the same
     * way, and reuses the exact resolution ClassTeacherController already
     * uses so the roster matches everywhere else in a teacher's portal.
     */
    protected function scopedClassIds(): ?array
    {
        $user = Auth::user();
        if (!$user || $user->hasPermission('manage-defaulters')) {
            return null;
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (!$teacher) {
            return null; // e.g. Receptionist -- no class of their own, sees all.
        }

        // Teacher::classes() is keyed to class_management (the legacy class
        // table), but Student::class_id/school_class_id are school_classes
        // ids -- translate through legacy_class_map so this scoping actually
        // matches the students it's meant to.
        $classManagementIds = $teacher->classes()->pluck('class_management.id')->toArray();

        return LegacyClassMap::whereIn('class_management_id', $classManagementIds)
            ->pluck('school_class_id')
            ->toArray();
    }

    /**
     * Display the Defaulter Dashboard with recovery analytics, class-wise stats, and ageing buckets.
     */
    public function dashboard()
    {
        // Dynamic Sync to ensure freshest data
        $this->defaulterService->syncDefaulters();

        $classIds = $this->scopedClassIds();

        // 1. Recovery Percentage = (Credit Sum / Debit Sum) * 100
        $recovery = DB::table('student_fee_ledgers')
            ->selectRaw('SUM(debit) as total_demand, SUM(credit) as total_collected')
            ->first();
        $totalDemand = (float)($recovery->total_demand ?? 0);
        $totalCollected = (float)($recovery->total_collected ?? 0);
        $recoveryPercentage = $totalDemand > 0 ? round(($totalCollected / $totalDemand) * 100, 2) : 100.00;

        // 2. Top Defaulters (stage !== Cleared)
        $topDefaulters = DefaulterStage::with(['student.schoolClass'])
            ->where('stage', '!=', 'Cleared')
            ->when($classIds !== null, fn($q) => $q->whereHas('student', fn($s) => $s->whereIn('class_id', $classIds)))
            ->orderBy('outstanding_amount', 'desc')
            ->take(10)
            ->get();

        // 3. Class-wise Defaulters
        $classwise = DB::table('defaulter_stages')
            ->join('students', 'students.id', '=', 'defaulter_stages.student_id')
            ->leftJoin('school_classes', 'school_classes.id', '=', DB::raw('COALESCE(students.class_id, students.school_class_id)'))
            ->select(DB::raw("COALESCE(school_classes.name, 'Unassigned') as class_name"))
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(defaulter_stages.outstanding_amount) as total_outstanding')
            ->where('defaulter_stages.stage', '!=', 'Cleared')
            ->when($classIds !== null, fn($q) => $q->whereIn('students.class_id', $classIds))
            ->groupBy('class_name')
            ->orderBy('total_outstanding', 'desc')
            ->get();

        // 4. Ageing Buckets (1-30, 31-60, 61-90, 90+ days) based on oldest unpaid debit date
        $ageing = [
            '1_30' => 0,
            '31_60' => 0,
            '61_90' => 0,
            '90_plus' => 0
        ];

        $unpaidLedgers = DB::table('student_fee_ledgers')
            ->select('student_id')
            ->selectRaw('MIN(date) as oldest_due_date')
            ->where('unpaid_amount', '>', 0.01)
            ->groupBy('student_id')
            ->get();

        foreach ($unpaidLedgers as $ledger) {
            $days = abs(now()->diffInDays(\Carbon\Carbon::parse($ledger->oldest_due_date)));
            if ($days <= 30) {
                $ageing['1_30']++;
            } elseif ($days <= 60) {
                $ageing['31_60']++;
            } elseif ($days <= 90) {
                $ageing['61_90']++;
            } else {
                $ageing['90_plus']++;
            }
        }

        $stagesStats = DB::table('defaulter_stages')
            ->select('stage')
            ->selectRaw('COUNT(*) as count')
            ->where('stage', '!=', 'Cleared')
            ->groupBy('stage')
            ->get();

        return view('admin.fees.defaulters.dashboard', compact(
            'totalDemand', 'totalCollected', 'recoveryPercentage',
            'topDefaulters', 'classwise', 'ageing', 'stagesStats'
        ));
    }

    /**
     * List current defaulters with advanced filtering.
     */
    public function index(Request $request)
    {
        $this->defaulterService->syncDefaulters();

        $query = $this->buildDefaulterQuery($request);

        $defaulters = $query->paginate(20)->withQueryString();
        $classes = SchoolClass::orderBy('class_order', 'asc')->get();
        $sections = \App\Models\Section::orderBy('name')->get();
        // Student has both a legacy 'section' string column and a
        // section() relation of the same name -- the column always wins
        // on ->section, so resolve the display name via this map instead.
        $sectionsById = $sections->pluck('name', 'id');
        $stages = DefaulterService::$stages;

        $overriddenStudentIds = \App\Models\DefaulterExamOverride::whereNull('revoked_at')
            ->whereIn('student_id', $defaulters->pluck('student_id'))
            ->pluck('student_id')
            ->all();
        $canOverrideExam = Auth::user()->hasPermission('override-exam-restriction');
        $totalFeeAmountsByStudent = $this->totalFeeAmountsByStudent($defaulters->pluck('student_id')->all());

        return view('admin.fees.defaulters.index', compact(
            'defaulters', 'classes', 'sections', 'sectionsById', 'stages',
            'overriddenStudentIds', 'canOverrideExam', 'totalFeeAmountsByStudent'
        ));
    }

    /**
     * Same filters as index() (stage/class/section/min-amount/month/
     * quarter/ageing, plus Class Teacher scoping) factored out so the PDF
     * and Excel exports below never drift out of sync with what's on
     * screen -- whatever the list shows is exactly what gets exported.
     */
    private function buildDefaulterQuery(Request $request)
    {
        $stage = $request->get('stage');
        $classId = $request->get('class_id');
        $sectionId = $request->get('section_id');
        $minAmount = $request->get('min_amount');
        $month = $request->get('month');
        $quarter = $request->get('quarter');
        $ageingBucket = $request->get('ageing');

        $query = DefaulterStage::with(['student.schoolClass', 'student.section'])
            ->where('stage', '!=', 'Cleared');

        $classIds = $this->scopedClassIds();
        if ($classIds !== null) {
            $query->whereHas('student', fn($q) => $q->whereIn('class_id', $classIds));
        }

        if ($stage) {
            $query->where('stage', $stage);
        }
        if ($minAmount) {
            $query->where('outstanding_amount', '>=', $minAmount);
        }
        if ($classId) {
            $query->whereHas('student', function($q) use($classId) {
                $q->where('class_id', $classId)->orWhere('school_class_id', $classId);
            });
        }
        if ($sectionId) {
            $query->whereHas('student', fn($q) => $q->where('section_id', $sectionId));
        }

        // Month/Quarter: restrict to students who have an unpaid ledger
        // charge dated within that period -- DefaulterStage only stores one
        // rolled-up total, so period filtering has to go back to
        // student_fee_ledgers, the same source FeeCollectionController's
        // Demand Register already filters this way.
        if ($month || $quarter) {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $months = $month
                ? [sprintf('%02d', (int) $month)]
                : $this->quarterMonths($quarter);

            $studentIdsForPeriod = DB::table('student_fee_ledgers')
                ->select('student_id')
                ->where('unpaid_amount', '>', 0)
                ->when($isSqlite,
                    fn($q) => $q->whereIn(DB::raw("strftime('%m', date)"), $months),
                    fn($q) => $q->whereIn(DB::raw('MONTH(date)'), array_map('intval', $months))
                )
                ->distinct();

            $query->whereIn('student_id', $studentIdsForPeriod);
        }

        // Ageing bucket: reuses the exact same bucket boundaries as the
        // dashboard's own ageing-chart calculation, just as a selectable
        // filter here instead of a stats-only display.
        if ($ageingBucket) {
            $oldestDueDates = DB::table('student_fee_ledgers')
                ->select('student_id')
                ->selectRaw('MIN(date) as oldest_due_date')
                ->where('unpaid_amount', '>', 0.01)
                ->groupBy('student_id')
                ->get();

            $matchingStudentIds = $oldestDueDates->filter(function ($row) use ($ageingBucket) {
                $days = abs(now()->diffInDays(\Carbon\Carbon::parse($row->oldest_due_date)));
                $bucket = $days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : '90_plus'));
                return $bucket === $ageingBucket;
            })->pluck('student_id');

            $query->whereIn('student_id', $matchingStudentIds);
        }

        return $query;
    }

    /**
     * Export the (filtered) Defaulter Registry as PDF or Excel (CSV
     * stream) -- Admin/Clerk/Accountant only, matching the same
     * class/month/quarter/ageing filters as the on-screen list.
     */
    public function export(Request $request)
    {
        $this->defaulterService->syncDefaulters();

        $format = $request->get('format', 'excel');
        $defaulters = $this->buildDefaulterQuery($request)->get();

        // Student has both a legacy 'section' string COLUMN and a
        // section() belongsTo RELATION with the same name -- Eloquent's
        // attribute lookup always resolves the column first, so
        // $student->section->name silently breaks (returns null) no
        // matter what's eager-loaded. Side-step it with an id->name map
        // built from the real sections table instead of the ambiguous
        // magic property.
        $sectionsById = \App\Models\Section::pluck('name', 'id');
        $totalFeeAmountsByStudent = $this->totalFeeAmountsByStudent($defaulters->pluck('student_id')->all());

        if ($format === 'pdf') {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.fees.defaulters.pdf', compact('defaulters', 'sectionsById', 'totalFeeAmountsByStudent', 'request'));
            $pdf->setPaper('A4', 'landscape');
            return $pdf->download('defaulter-registry-' . now()->format('Y-m-d') . '.pdf');
        }

        $fileName = 'defaulter-registry-' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=$fileName",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($defaulters, $sectionsById, $totalFeeAmountsByStudent) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // Excel UTF-8 BOM

            fputcsv($file, [
                'Admission No', 'Student', 'Class', 'Section', 'Workflow Stage',
                'Total Fee Amount (INR)', 'Outstanding (INR)', 'Last Action Date',
            ]);

            foreach ($defaulters as $def) {
                fputcsv($file, [
                    $def->student->admission_no ?? '',
                    $def->student->name ?? '',
                    $def->student->schoolClass->name ?? '',
                    $sectionsById[$def->student->section_id] ?? '',
                    $def->stage,
                    number_format($totalFeeAmountsByStudent[$def->student_id] ?? 0, 2, '.', ''),
                    number_format($def->outstanding_amount, 2, '.', ''),
                    $def->last_action_date ? $def->last_action_date->format('Y-m-d H:i') : '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Total fee amount ever billed to each student (fee demand + late
     * fee, i.e. every debit except refund reversals) -- same "fee_demand"
     * definition FeeCollectionController::buildDemandRegisterQuery()
     * already uses, computed as one grouped aggregate rather than a
     * per-row subquery so it stays cheap on a 900+ row export.
     */
    private function totalFeeAmountsByStudent(array $studentIds): \Illuminate\Support\Collection
    {
        if (empty($studentIds)) {
            return collect();
        }

        return DB::table('student_fee_ledgers')
            ->select('student_id')
            ->selectRaw("SUM(CASE WHEN debit > 0 AND reference_type != 'fee_refund' THEN debit ELSE 0 END) as total_fee_amount")
            ->whereIn('student_id', $studentIds)
            ->groupBy('student_id')
            ->pluck('total_fee_amount', 'student_id');
    }

    /**
     * Q1=Apr-Jun, Q2=Jul-Sep, Q3=Oct-Dec, Q4=Jan-Mar -- the same academic
     * quarter definition used consistently throughout this school's fee
     * structures (Tuition/Robotics billed quarterly on this basis).
     */
    private function quarterMonths(string $quarter): array
    {
        return match ($quarter) {
            'Q1' => ['04', '05', '06'],
            'Q2' => ['07', '08', '09'],
            'Q3' => ['10', '11', '12'],
            'Q4' => ['01', '02', '03'],
            default => [],
        };
    }

    /**
     * 403s if this student falls outside a scoped (Class Teacher) actor's
     * own classes -- the query-level scoping in index()/dashboard() keeps
     * them out of the list UI, but takeAction/bulkAction take a raw student
     * id and must not trust that the caller only ever submits ids they were
     * shown.
     */
    protected function authorizeStudentInScope(int $studentId): void
    {
        $classIds = $this->scopedClassIds();
        if ($classIds === null) {
            return;
        }

        $inScope = Student::where('id', $studentId)->whereIn('class_id', $classIds)->exists();
        if (!$inScope) {
            abort(403, 'This student is outside your assigned class.');
        }
    }

    /**
     * Notifies every Admin/Principal when a Class Teacher or Receptionist
     * (communicate-defaulters only) dispatches a communication -- neither
     * can promote/override a stage themselves, so this is how the school
     * stays aware of what's being sent on its behalf.
     */
    protected function notifyAdminsIfDelegatedActor(int $studentId, string $channel, string $stage): void
    {
        $actor = Auth::user();
        if (!$actor || $actor->hasPermission('manage-defaulters')) {
            return;
        }

        $student = Student::find($studentId);
        if (!$student) {
            return;
        }

        $roleLabel = $actor->roles->first()->display_name ?? $actor->roles->first()->name ?? 'Staff';
        $admins = User::whereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'principal']))->get();
        foreach ($admins as $admin) {
            $admin->notify(new DefaulterActionNotification($student, $actor, $roleLabel, $channel, $stage));
        }
    }

    /**
     * Dispatch single student action.
     */
    public function takeAction(Request $request, $id)
    {
        $request->validate([
            'channel' => 'required|in:sms,whatsapp,email,notification,phone call',
            'stage' => 'required|string',
            'promote' => 'nullable|boolean'
        ]);

        $this->authorizeStudentInScope((int) $id);

        $success = $this->defaulterService->dispatchCommunication(
            $id,
            $request->channel,
            $request->stage,
            Auth::id()
        );

        if ($success) {
            $this->notifyAdminsIfDelegatedActor((int) $id, $request->channel, $request->stage);

            // Stage promotion stays manage-defaulters-only even though
            // communicate-defaulters actors can reach this action.
            if ($request->get('promote') && Auth::user()->hasPermission('manage-defaulters')) {
                $newStage = $this->defaulterService->promoteStage($id, Auth::id());
                return back()->with('success', "Action logged successfully. Student promoted to stage: {$newStage}.");
            }
            return back()->with('success', 'Action logged and notice dispatched successfully.');
        }

        return back()->with('error', 'Failed to dispatch communication.');
    }

    /**
     * Handle bulk notifications to selected student defaulters.
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
            'channel' => 'required|in:sms,whatsapp,email,notification',
            'promote' => 'nullable|boolean'
        ]);

        $canPromote = Auth::user()->hasPermission('manage-defaulters');

        $successCount = 0;
        foreach ($request->student_ids as $studentId) {
            $this->authorizeStudentInScope((int) $studentId);

            $defStage = DefaulterStage::where('student_id', $studentId)->first();
            if ($defStage) {
                $success = $this->defaulterService->dispatchCommunication(
                    $studentId,
                    $request->channel,
                    $defStage->stage,
                    Auth::id()
                );

                if ($success) {
                    $successCount++;
                    $this->notifyAdminsIfDelegatedActor((int) $studentId, $request->channel, $defStage->stage);
                    if ($request->get('promote') && $canPromote) {
                        $this->defaulterService->promoteStage($studentId, Auth::id());
                    }
                }
            }
        }

        return back()->with('success', "Bulk actions processed. Successfully dispatched to {$successCount} parents.");
    }

    /**
     * Override/Update stage manually.
     */
    public function override(Request $request, $id)
    {
        $request->validate([
            'stage' => 'required|string',
            'remarks' => 'nullable|string'
        ]);

        $success = $this->defaulterService->overrideStage($id, $request->stage, $request->remarks, Auth::id());

        if ($success) {
            return back()->with('success', 'Defaulter stage updated successfully.');
        }

        return back()->with('error', 'Failed to update defaulter stage.');
    }

    /**
     * View history of actions logged for a student.
     */
    public function history($studentId)
    {
        $student = Student::findOrFail($studentId);
        $logs = DefaulterLog::with('performedBy')
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'student' => $student->name,
            'logs' => $logs
        ]);
    }

    /**
     * Admin/Principal/Accountant exception: lets a student sit the exam
     * despite an active Exam Restriction (or beyond) -- restores any
     * already-revoked admit card immediately and, via
     * AdmitCard::validateForGeneration(), unblocks generating a new one.
     */
    public function grantExamOverride(Request $request, $id)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        ExamRestrictionService::grantOverride((int) $id, Auth::id(), $request->reason);

        return back()->with('success', 'Exam restriction override granted -- the student\'s admit card has been restored.');
    }

    public function revokeExamOverride(Request $request, $id)
    {
        ExamRestrictionService::revokeOverride((int) $id, Auth::id());

        return back()->with('success', 'Exam restriction override revoked.');
    }
}
