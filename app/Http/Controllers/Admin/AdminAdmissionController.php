<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdmissionEnquiry;
use App\Services\AdmissionNotificationService;
use Illuminate\Http\Request;

class AdminAdmissionController extends Controller
{
    protected AdmissionNotificationService $notificationService;

    public function __construct(AdmissionNotificationService $notificationService)
    {
        $this->middleware('auth');
        $this->notificationService = $notificationService;
    }

    public function index()
    {
        $query = AdmissionEnquiry::orderBy('created_at', 'desc');

        $user = auth()->user();
        if (!$this->isAdminOrReceptionist($user)) {
            $query->where('counsellor_id', $user->id);
        }

        $enquiries = $query->get();
        return view('admin.admissions.enquiries', compact('enquiries'));
    }

    public function storeEnquiry(Request $request)
    {
        $request->validate([
            'candidate_name' => 'required|string|max:255',
            'parent_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        $enquiry = AdmissionEnquiry::create(array_merge($request->all(), [
            'status' => 'enquiry',
            'counsellor_id' => auth()->id(),
        ]));

        activity()->causedBy(auth()->user())->performedOn($enquiry)
            ->withProperties(['status' => $enquiry->status])
            ->log('admission_enquiry_created');

        $this->notificationService->notifyStage($enquiry, 'admission_enquiry_received');

        return redirect()->route('admin.admissions.index')->with('success', 'Enquiry recorded successfully.');
    }

    public function scheduleInterview(Request $request, $id)
    {
        $request->validate([
            'interview_date' => 'required|date|after_or_equal:today',
        ]);

        $enquiry = AdmissionEnquiry::findOrFail($id);
        $this->checkAccess($enquiry);
        $previousStatus = $enquiry->status;
        $enquiry->update([
            'interview_date' => $request->interview_date,
            'status' => 'interview',
        ]);
        $this->logStatusChangeIfApplicable($enquiry, $previousStatus);
        $this->notificationService->notifyStage($enquiry, 'admission_interview_scheduled', [
            'interview_date' => $request->interview_date,
        ]);

        return redirect()->route('admin.admissions.index')->with('success', 'Interview scheduled successfully.');
    }

    public function evaluate(Request $request, $id)
    {
        $request->validate([
            'interview_score' => 'required|numeric|min:0|max:100',
            'entrance_score' => 'required|numeric|min:0|max:100',
            'status' => 'required|in:selected,closed',
        ]);

        $enquiry = AdmissionEnquiry::findOrFail($id);
        $this->checkAccess($enquiry);
        $previousStatus = $enquiry->status;
        $enquiry->update($request->only(['interview_score', 'entrance_score', 'status']));
        $this->logStatusChangeIfApplicable($enquiry, $previousStatus);

        $eventType = $enquiry->status === 'selected' ? 'admission_confirmed' : 'admission_rejected';
        $this->notificationService->notifyStage($enquiry, $eventType);

        return redirect()->route('admin.admissions.index')->with('success', 'Admission candidate evaluation completed.');
    }

    public function showConfirmForm($id)
    {
        $enquiry = AdmissionEnquiry::findOrFail($id);
        $this->checkAccess($enquiry);
        if (!in_array($enquiry->status, ['selected', 'confirmed'], true)) {
            return redirect()->route('admin.admissions.index')->with('error', 'Only selected candidates can be admitted.');
        }

        $classes = \App\Models\SchoolClass::orderBy('class_order')->get();
        $sections = \App\Models\ClassManagement::all()->groupBy('name');
        $sectionOccupancy = \App\Models\Student::whereNotNull('section_id')
            ->selectRaw('section_id, count(*) as cnt')
            ->groupBy('section_id')
            ->pluck('cnt', 'section_id');
        $isAdmin = $this->isAdmin(auth()->user());

        return view('admin.admissions.confirm', compact('enquiry', 'classes', 'sections', 'sectionOccupancy', 'isAdmin'));
    }

    public function confirmAdmission(\Illuminate\Http\Request $request, $id)
    {
        $enquiry = AdmissionEnquiry::findOrFail($id);
        $this->checkAccess($enquiry);
        $previousStatus = $enquiry->status;
        if (!in_array($enquiry->status, ['selected', 'confirmed'], true)) {
            return redirect()->route('admin.admissions.index')->with('error', 'Only selected candidates can be admitted.');
        }

        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:class_management,id',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|in:male,female,other',
            'category' => 'required|string|max:50',
            'aadhaar_number' => 'nullable|string|max:20|unique:students,aadhaar_number',
            'mother_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'roll_number' => 'nullable|integer',
            'override_capacity' => 'nullable|boolean',
        ]);

        $class = \App\Models\SchoolClass::findOrFail($request->class_id);
        $section = \App\Models\ClassManagement::findOrFail($request->section_id);

        $occupancy = \App\Models\Student::where('class_id', $class->id)->where('section_id', $section->id)->count();
        $isOverride = $request->boolean('override_capacity') && $this->isAdmin(auth()->user());
        if ($occupancy >= $section->capacity && !$isOverride) {
            return redirect()->back()->withInput()->with('error',
                "Section {$section->name} ({$section->section}) is full ({$occupancy}/{$section->capacity} seats). " .
                ($this->isAdmin(auth()->user()) ? 'Check the override option to admit anyway.' : 'Please choose another section or contact an admin.')
            );
        }
        if ($occupancy >= $section->capacity && $isOverride) {
            activity()->causedBy(auth()->user())->performedOn($enquiry)
                ->withProperties(['section_id' => $section->id, 'occupancy' => $occupancy, 'capacity' => $section->capacity])
                ->log('admission_capacity_overridden');
        }

        $year = date('Y');
        $random = mt_rand(1000, 9999);
        $admissionNo = 'ADM-' . $year . '-' . $random;
        while (\App\Models\Student::where('admission_no', $admissionNo)->exists()) {
            $random = mt_rand(1000, 9999);
            $admissionNo = 'ADM-' . $year . '-' . $random;
        }

        $aadharNumber = $request->aadhaar_number;
        if (!$aadharNumber) {
            do {
                $aadharNumber = 'TBD-' . mt_rand(100000, 999999);
            } while (\App\Models\Student::where('aadhaar_number', $aadharNumber)->exists());
        }

        $tempParentPassword = \Illuminate\Support\Str::random(10);
        $currentSession = \App\Models\AcademicSession::current()->first();

        \Illuminate\Support\Facades\DB::transaction(function () use ($enquiry, $request, $class, $section, $admissionNo, $aadharNumber, $tempParentPassword, $currentSession) {
            $student = \App\Models\Student::create([
                'name' => $enquiry->candidate_name,
                'father_name' => $enquiry->parent_name,
                'mother_name' => $request->mother_name ?: 'Not Specified',
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'category' => $request->category,
                'aadhaar_number' => $aadharNumber,
                'phone' => $enquiry->phone,
                'mobile' => $enquiry->phone,
                'address' => $request->address ?: 'Not Specified',
                'class_id' => $class->id,
                'class' => $class->name,
                'section_id' => $section->id,
                'section' => $section->section,
                'roll_number' => $request->roll_number,
                'admission_no' => $admissionNo,
                // Marks this student as a genuinely new admission for the current
                // academic session -- the only signal the fee module has for
                // deciding whether "Admission Fee" applies vs. a continuing student.
                'admission_session_id' => $currentSession?->id,
                'is_verified' => true,
            ]);

            // Student::saved() auto-creates/links a ParentModel with a random password we don't know;
            // set a fresh one here so it can be communicated to the parent exactly once.
            $parent = \App\Models\ParentModel::where('student_id', $student->id)->first();
            if ($parent) {
                $parent->update([
                    'password' => bcrypt($tempParentPassword),
                    'must_reset_password' => true,
                ]);
            }

            $enquiry->update(['status' => 'admitted']);

            // Admission never used to trigger fee billing at all -- a
            // student was only ever billed if a staff member happened to
            // create/copy a FeeStructure for their class AFTER they were
            // already enrolled. Assign the class's active fee structure now,
            // the same way FeeStructureController does when a structure is
            // created, so a newly admitted student is billed from day one.
            // No structure yet (fees not set up for this class/session) is a
            // normal, expected state -- must never block admission itself.
            $feeStructure = \App\Models\FeeStructure::where('class_name', $class->name)
                ->where('status', 'active')
                ->when($currentSession, fn ($q) => $q->where(function ($q2) use ($currentSession) {
                    $q2->where('academic_year', $currentSession->code)
                       ->orWhere('academic_year', $currentSession->name);
                }))
                ->latest('id')
                ->first();

            if ($feeStructure) {
                \App\Services\BulkFeeAssignmentService::bulkAssign($feeStructure, [$student->id]);
            }
        });

        activity()->causedBy(auth()->user())->performedOn($enquiry)
            ->withProperties(['from' => $previousStatus, 'to' => 'admitted', 'admission_no' => $admissionNo])
            ->log('admission_status_changed');

        $this->notificationService->notifyStage($enquiry->fresh(), 'admission_admitted', [
            'admission_no' => $admissionNo,
            'login' => $enquiry->phone,
            'temp_password' => $tempParentPassword,
        ]);

        return redirect()->route('admin.admissions.index')->with('success',
            "Student admitted successfully! Admission No: {$admissionNo}. " .
            "Parent portal login: {$enquiry->phone} / Temporary password: {$tempParentPassword} (must be changed on first login)."
        );
    }

    private function isAdminOrReceptionist($user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('super-admin') || $user->hasRole('receptionist');
    }

    private function isAdmin($user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('super-admin');
    }

    private function checkAccess(AdmissionEnquiry $enquiry): void
    {
        $user = auth()->user();

        if (!$this->isAdminOrReceptionist($user) && $enquiry->counsellor_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function logStatusChangeIfApplicable(AdmissionEnquiry $enquiry, string $previousStatus): void
    {
        if ($enquiry->status === $previousStatus) {
            return;
        }

        activity()->causedBy(auth()->user())->performedOn($enquiry)
            ->withProperties(['from' => $previousStatus, 'to' => $enquiry->status])
            ->log('admission_status_changed');
    }
}
