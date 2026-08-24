<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\User;
use App\Models\ExamHead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TeacherController extends Controller
{
    // Display all teachers
    public function index()
    {
        $this->authorize('viewAny', Teacher::class);
        $teachers = Teacher::with(['examHead'])->get();
        return view('teachers.index', compact('teachers'));
    }

    // Show create form
    public function create()
    {
        $this->authorize('create', Teacher::class);
        $subjects = ['Mathematics', 'Science', 'English', 'Hindi', 'Social Studies', 
                    'Physics', 'Chemistry', 'Biology', 'Computer Science', 'Physical Education'];
        $qualifications = ['B.Ed', 'M.Ed', 'B.Sc B.Ed', 'M.Ed', 'Ph.D', 'Other'];
        
        return view('teachers.create', compact('subjects', 'qualifications'));
    }

    // Store new teacher
    public function store(Request $request)
    {
        $this->authorize('create', Teacher::class);
        
        $validated = $request->validate(
    Teacher::storeRules(),
    [
        'name.required' => 'Teacher name is required',
        'email.required' => 'Email is required',
        'email.email' => 'Enter a valid email address',
        'email.unique' => 'This email already exists',
        'phone.required' => 'Phone number is required',
        'phone.digits' => 'Phone number must be 10 digits',
        'designation.required' => 'Designation is required',
    ]
);

        
        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            $imagePath = $request->file('profile_image')->store('teacher_profiles', 'public');
            $validated['profile_image'] = $imagePath;
        }

        // Handle is_exam_head and is_exam_cell_member checkboxes
        $isExamHead = $request->has('is_exam_head') ? true : false;
        $validated['is_exam_head'] = $isExamHead;
        $validated['is_exam_cell_member'] = $request->has('is_exam_cell_member') ? true : false;
        
        $teacher = Teacher::create($validated);

        // Sync with exam_heads table
        if ($isExamHead) {
            ExamHead::updateOrCreate(
                ['teacher_id' => $teacher->id],
                [
                    'assigned_by' => Auth::id(),
                    'assigned_at' => now(),
                    'status' => 'active'
                ]
            );
        }
        
        return redirect()->route('admin.teachers.index')
                         ->with('success', 'Teacher added successfully!');
    }

    // Show single teacher (route-model binding)
    public function show(Teacher $teacher)
    {
        $this->authorize('view', $teacher);
        return view('teachers.show', compact('teacher'));
    }

    // Show edit form (route-model binding)
    public function edit(Teacher $teacher)
    {
        $this->authorize('update', $teacher);
        $subjects = ['Mathematics', 'Science', 'English', 'Hindi', 'Social Studies', 
                    'Physics', 'Chemistry', 'Biology', 'Computer Science', 'Physical Education'];
        $qualifications = ['B.Ed', 'M.Ed', 'B.Sc B.Ed', 'M.Ed', 'Ph.D', 'Other'];
        
        return view('teachers.edit', compact('teacher', 'subjects', 'qualifications'));
    }

    // Update teacher (route-model binding)
    public function update(Request $request, Teacher $teacher)
    {
        $this->authorize('update', $teacher);
        $validated = $request->validate(Teacher::updateRules($teacher->id));

        // Handle profile image update
        if ($request->hasFile('profile_image')) {
            // Delete old image if exists
            if ($teacher->profile_image) {
                Storage::disk('public')->delete($teacher->profile_image);
            }

            $imagePath = $request->file('profile_image')->store('teacher_profiles', 'public');
            $validated['profile_image'] = $imagePath;
        }
        
        // Handle is_exam_head and is_exam_cell_member checkboxes
        $isExamHead = $request->has('is_exam_head') ? true : false;
        $validated['is_exam_head'] = $isExamHead;
        $validated['is_exam_cell_member'] = $request->has('is_exam_cell_member') ? true : false;

        $teacher->update($validated);

        // Sync with exam_heads table
        if ($isExamHead) {
            \App\Models\ExamHead::updateOrCreate(
                ['teacher_id' => $teacher->id],
                [
                    'assigned_by' => Auth::id(),
                    'assigned_at' => now(),
                    'status' => 'active'
                ]
            );
        } else {
            $examHead = \App\Models\ExamHead::where('teacher_id', $teacher->id)->first();
            if ($examHead) {
                $examHead->update(['status' => 'inactive']);
            }
        }

        return redirect()->route('admin.teachers.index')
                         ->with('success', 'Teacher updated successfully!');
    }

    /**
     * Upload/replace a teacher's profile photo. Deliberately separate from
     * update() -- gated by the FieldPermission system (model_type=teacher,
     * field_name=profile_image) rather than the TeacherPolicy, since roles
     * like clerk/receptionist/class-teacher should be able to upload a
     * photo without gaining the ability to edit the rest of the record.
     */
    public function updatePhoto(Request $request, Teacher $teacher)
    {
        if (!\App\Helpers\FieldPermissionHelper::canEditField('teacher', 'profile_image')) {
            abort(403, 'You are not authorized to upload a teacher photo.');
        }

        $request->validate([
            // 8MB, jpeg/png/gif/webp/bmp -- a real phone camera photo
            // routinely exceeds the old 2MB/jpeg-png-gif-only cap, and this
            // page had no visible way to show the resulting validation
            // error, so an oversized/unsupported photo just silently failed.
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,webp,bmp|max:8192',
        ]);

        if ($teacher->profile_image) {
            Storage::disk('public')->delete($teacher->profile_image);
        }

        $teacher->update(['profile_image' => $request->file('photo')->store('teacher_profiles', 'public')]);

        // Audit logging is a best-effort side effect -- it must never be
        // able to fail the upload itself (e.g. if activity_log's schema
        // has drifted on a given install).
        try {
            activity()->causedBy(Auth::user())->performedOn($teacher)->log('Uploaded teacher photo');
        } catch (\Throwable $e) {
            Log::warning('Failed to log teacher photo upload activity: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Teacher photo updated successfully.');
    }

    // Toggle exam head status
    public function toggleExamHead(Request $request, Teacher $teacher)
    {
        $request->validate([
            'make_exam_head' => 'required|boolean'
        ]);

        $isAdmin = Auth::check();
        $currentUser = $isAdmin ? Auth::user() : null;

        if ($request->make_exam_head) {
            // Create or update exam head record
            ExamHead::updateOrCreate(
                ['teacher_id' => $teacher->id],
                [
                    'assigned_by' => $currentUser ? $currentUser->id : null,
                    'assigned_at' => now(),
                    'status' => 'active'
                ]
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Successfully assigned as Exam Head!'
            ]);
        } else {
            // Deactivate exam head record
            $examHead = ExamHead::where('teacher_id', $teacher->id)->first();
            if ($examHead) {
                $examHead->update(['status' => 'inactive']);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Successfully removed Exam Head status!'
            ]);
        }
    }

    // Delete teacher (route-model binding)
    /**
     * Dependency-safety fix (same "smallest safe fix" pattern already
     * applied to Sections): this previously deleted (soft-deleted) any
     * teacher with zero dependency check, silently dropping an actively
     * scheduled teacher from Timetable/Substitution/class-assignment
     * views with no warning.
     */
    public function destroy(Teacher $teacher)
    {
        $this->authorize('delete', $teacher);

        $blockingDependency = $this->blockingTeacherDependency($teacher);
        if ($blockingDependency !== null) {
            return redirect()->route('admin.teachers.index')
                ->with('error', "Cannot delete this teacher: {$blockingDependency}");
        }

        $teacher->delete();

        return redirect()->route('admin.teachers.index')
                         ->with('success', 'Teacher deleted');
    }

    /**
     * Returns a human-readable reason if the teacher has a live
     * dependency, or null if safe to delete. Shared by web and API
     * delete paths so both enforce the same rule.
     */
    public static function blockingTeacherDependency(Teacher $teacher): ?string
    {
        $slotCount = \Illuminate\Support\Facades\DB::table('timetable_slots')
            ->where('teacher_id', $teacher->id)
            ->orWhere('co_teacher_id', $teacher->id)
            ->count();
        if ($slotCount > 0) {
            return "{$slotCount} timetable slot(s) are currently assigned to this teacher.";
        }

        $substitutionCount = \Illuminate\Support\Facades\DB::table('teacher_substitutions')
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($teacher) {
                $q->where('absent_teacher_id', $teacher->id)
                  ->orWhere('substitute_teacher_id', $teacher->id);
            })
            ->count();
        if ($substitutionCount > 0) {
            return "{$substitutionCount} active teacher substitution(s) reference this teacher.";
        }

        $assignmentCount = \Illuminate\Support\Facades\DB::table('teacher_class_subject_assignments')
            ->where('teacher_id', $teacher->id)
            ->count();
        if ($assignmentCount > 0) {
            return "{$assignmentCount} class/subject assignment(s) are currently assigned to this teacher. Remove or reassign them first.";
        }

        return null;
    }
}