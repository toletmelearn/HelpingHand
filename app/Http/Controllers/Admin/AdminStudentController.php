<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StudentController;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Helpers\FieldPermissionHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminStudentController extends Controller
{
    /**
     * Display a listing of students grouped by class and section.
     */
    public function index(Request $request)
    {
        // Check if class and section filters are applied
        $classId = $request->get('class_id');
        $sectionId = $request->get('section_id');
        $section = $request->get('section');
        $search = $request->get('search');

        if ($classId || $sectionId || $section || $search) {
            // If filters are applied, show students list
            $query = Student::query();
            
            if ($classId) {
                $query->where(function($q) use ($classId) {
                    $q->where('class_id', $classId)
                      ->orWhere('school_class_id', $classId);
                });
            }
            
            if ($sectionId) {
                $query->where(function($q) use ($sectionId) {
                    $q->where('section_id', $sectionId)
                      ->orWhere('section', $sectionId);
                });
            } elseif ($section) {
                $this->applySectionFilter($query, $section);
            }
            
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('admission_no', 'LIKE', "%{$search}%")
                      ->orWhere('mobile', 'LIKE', "%{$search}%");
                });
            }
            
            $students = $query->with('schoolClass')
                ->orderBy(DB::raw('COALESCE(school_class_id, class_id)'))
                ->orderBy('section')
                ->orderBy('roll_number')
                ->get();
            
            [$classList, $sections] = $this->getClassAndSectionList();
            
            return view('admin.students.index', [
                'students' => $students,
                'classList' => $classList,
                'sections' => $sections,
                'selectedClassId' => $classId,
                'selectedSectionId' => $sectionId ?: $this->resolveSectionIdForSelection($section),
                'selectedSection' => $section,
                'search' => $search,
                'showingStudents' => true
            ]);
        } else {
            // Show class-section grouped list
            $classSections = Student::select(
                                      DB::raw('COALESCE(school_class_id, class_id) as class_id'),
                                      'section_id',
                                      'section',
                                      DB::raw('COUNT(*) as total')
                                  )
                                  ->groupBy(DB::raw('COALESCE(school_class_id, class_id)'), 'section_id', 'section')
                                  ->orderBy(DB::raw('COALESCE(school_class_id, class_id)'))
                                  ->orderBy('section')
                                  ->get();
            
            // Map the coalesced class_id value to school_class_id to allow eager loading to match
            $classSections->each(function($item) {
                $item->school_class_id = $item->class_id;
            });

            // Load the schoolClass relation on the collection
            $classSections->load(['schoolClass' => function($query) {
                $query->select('id', 'name');
            }]);
            
            [$classList, $sections] = $this->getClassAndSectionList();
            
            return view('admin.students.index', [
                'classSections' => $classSections,
                'classList' => $classList,
                'sections' => $sections,
                'showingStudents' => false
            ]);
        }
    }

    /**
     * Display students for a specific class and section.
     */
    public function showClassStudents($classId, $section = null)
    {
        $query = Student::where(function($q) use ($classId) {
            $q->where('class_id', $classId)
              ->orWhere('school_class_id', $classId);
        });
        
        if ($section) {
            $query->where(function($q) use ($section) {
                $q->where('section', $section)
                  ->orWhere('section_id', $section);
            });
        }
        
        $students = $query->with('schoolClass')->orderBy('roll_number')->get();
        $schoolClass = SchoolClass::find($classId);
        
        return view('admin.students.show-class', [
            'students' => $students,
            'schoolClass' => $schoolClass,
            'section' => $section
        ]);
    }

    public function list(Request $request)
    {
        $query = Student::query();
        
        // Apply any filters if provided
        if ($request->filled('class')) {
            $query->where('class', $request->get('class'));
        }
        if ($request->filled('section')) {
            $query->where('section', $request->get('section'));
        }
        
        $students = $query->with('schoolClass')->get();
        return view('admin.students.list', compact('students'));
    }

    /**
     * Show the form for creating a new student.
     */
    public function create()
    {
        $this->authorize('create', Student::class);
        [$classList, $sections] = $this->getClassAndSectionList();

        return view('admin.students.create', ['classList' => $classList, 'sections' => $sections]);
    }

    /**
     * Store a newly created student in database.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Student::class);
        // Validate the request data
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'mother_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:today', // Ensure birth date is not in future
            'aadhar_number' => 'required|digits:12|unique:students',
            'admission_no' => 'nullable|string|max:100|unique:students,admission_no',
            'address' => 'required|string',
            'mobile' => 'required|digits:10',
            'gender' => 'required|in:male,female,other',
            'category' => 'required|in:General,OBC,SC,ST,Other',
            'class_id' => 'nullable|integer|exists:school_classes,id',
            'class' => 'required_without:class_id|nullable|string|max:50',
            'section_id' => 'nullable|integer|exists:sections,id',
            'section' => 'nullable|string|max:10',
            'roll_number' => 'nullable|integer|unique:students',
            'religion' => 'nullable|string|max:50',
            'caste' => 'nullable|string|max:50',
            'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-,unknown'
        ]);

        $normalized = $this->normalizeClassSectionPayload($validated, $request);

        $student = new Student();
        $student->fill($normalized);
        $this->assignClassSectionCompatibility($student, $normalized);
        $student->save();

        // Redirect with success message
        return redirect()->route('admin.students.index')
                         ->with('success', 'Student successfully added!');
    }

    /**
     * Display the specified student.
     */
    public function show($id)
    {
        $this->authorize('view', [Student::class, Student::findOrFail($id)]);
        $student = Student::with('schoolClass')->findOrFail($id);
        return view('admin.students.show', ['student' => $student]);
    }

    /**
     * Show the form for editing a student.
     */
    public function edit($id)
    {
        $this->authorize('update', [Student::class, Student::findOrFail($id)]);
        $student = Student::findOrFail($id);
        [$classList, $sections] = $this->getClassAndSectionList();
        return view('admin.students.edit', ['student' => $student, 'classList' => $classList, 'sections' => $sections]);
    }

    /**
     * Update the specified student.
     */
    public function update(Request $request, $id)
    {
        $this->authorize('update', [Student::class, Student::findOrFail($id)]);
        $student = Student::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'mother_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:today', // Ensure birth date is not in future
            'aadhar_number' => 'required|digits:12|unique:students,aadhar_number,'.$id,
            'admission_no' => 'nullable|string|max:100|unique:students,admission_no,'.$id,
            'address' => 'required|string',
            'mobile' => 'required|digits:10',
            'gender' => 'required|in:male,female,other',
            'category' => 'required|in:General,OBC,SC,ST,Other',
            'class_id' => 'nullable|integer|exists:school_classes,id',
            'class' => 'required_without:class_id|nullable|string|max:50',
            'section_id' => 'nullable|integer|exists:sections,id',
            'section' => 'nullable|string|max:10',
            'roll_number' => 'nullable|integer|unique:students,roll_number,'.$id,
            'religion' => 'nullable|string|max:50',
            'caste' => 'nullable|string|max:50',
            'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-,unknown'
        ]);

        $normalized = $this->normalizeClassSectionPayload($validated, $request);

        $student->fill($normalized);
        $this->assignClassSectionCompatibility($student, $normalized);
        $student->save();
        
        return redirect()->route('admin.students.index')
                         ->with('success', 'Student updated successfully!');
    }

    /**
     * Upload/replace a student's profile photo. Deliberately separate from
     * update() -- gated by the FieldPermission system (model_type=student,
     * field_name=photo) rather than the StudentPolicy, since roles like
     * clerk/receptionist/class-teacher should be able to upload a photo
     * without gaining the ability to edit the rest of the record.
     */
    public function updatePhoto(Request $request, $id)
    {
        if (!FieldPermissionHelper::canEditField('student', 'photo')) {
            abort(403, 'You are not authorized to upload a student photo.');
        }

        $student = Student::findOrFail($id);

        $request->validate([
            // 8MB, jpeg/png/gif/webp/bmp -- a real phone camera photo
            // routinely exceeds the old 2MB/jpeg-png-gif-only cap, and this
            // page had no visible way to show the resulting validation
            // error, so an oversized/unsupported photo just silently failed.
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,webp,bmp|max:8192',
        ]);

        if ($student->photo) {
            Storage::disk('public')->delete($student->photo);
        }

        $student->update(['photo' => $request->file('photo')->store('student_photos', 'public')]);

        // Audit logging is a best-effort side effect -- it must never be
        // able to fail the upload itself (e.g. if activity_log's schema
        // has drifted on a given install).
        try {
            activity()->causedBy(auth()->user())->performedOn($student)->log('Uploaded student photo');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to log student photo upload activity: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Student photo updated successfully.');
    }

    /**
     * Remove the specified student.
     */
    public function destroy($id)
    {
        $this->authorize('delete', [Student::class, Student::findOrFail($id)]);
        $student = Student::findOrFail($id);
        $student->delete();

        return redirect()->route('admin.students.index')
                         ->with('success', 'Student deleted successfully!');
    }

    /**
     * Remove multiple students at once, selected via checkboxes on the list page.
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:students,id',
        ]);

        $students = Student::whereIn('id', $request->student_ids)->get();

        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($students as $student) {
            if (!auth()->user()->can('delete', $student)) {
                $skippedCount++;
                continue;
            }
            $student->delete();
            $deletedCount++;
        }

        $message = "{$deletedCount} student(s) deleted successfully.";
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} student(s) were skipped (insufficient permission).";
        }

        return redirect()->route('admin.students.index')->with('success', $message);
    }

    private function normalizeClassSectionPayload(array $validated, Request $request): array
    {
        $schoolClass = null;
        $classId = $request->input('class_id');

        if ($classId !== null && $classId !== '') {
            $schoolClass = SchoolClass::find($classId);
        } elseif (!empty($validated['class'])) {
            $schoolClass = SchoolClass::where('name', $validated['class'])->first();
        }

        if ($schoolClass) {
            $validated['class_id'] = $schoolClass->id;
            $validated['school_class_id'] = $schoolClass->id;
            $validated['class'] = $schoolClass->name;
        }

        $section = null;
        $sectionId = $request->input('section_id');

        if ($sectionId !== null && $sectionId !== '') {
            $section = Section::find($sectionId);
        } elseif (isset($validated['section']) && $validated['section'] !== '') {
            if (is_numeric($validated['section'])) {
                $section = Section::find((int) $validated['section']);
            } else {
                $section = Section::where('name', $validated['section'])->first();
            }
        }

        if ($section) {
            $validated['section_id'] = $section->id;
            $validated['section'] = (string) $section->id;
        }

        return $validated;
    }

    private function assignClassSectionCompatibility(Student $student, array $normalized): void
    {
        foreach (['class_id', 'school_class_id', 'section_id'] as $field) {
            if (array_key_exists($field, $normalized)) {
                $student->{$field} = $normalized[$field];
            }
        }
    }

    private function applySectionFilter($query, string $section): void
    {
        if (is_numeric($section)) {
            $query->where(function ($q) use ($section) {
                $q->where('section_id', (int) $section)
                  ->orWhere('section', $section);
            });

            return;
        }

        $resolvedSection = Section::where('name', $section)->first();

        if ($resolvedSection) {
            $query->where(function ($q) use ($resolvedSection, $section) {
                $q->where('section_id', $resolvedSection->id)
                  ->orWhere('section', (string) $resolvedSection->id)
                  ->orWhere('section', $section);
            });

            return;
        }

        $query->where('section', $section);
    }

    private function resolveSectionIdForSelection(?string $section): ?int
    {
        if ($section === null || $section === '') {
            return null;
        }

        if (is_numeric($section)) {
            return (int) $section;
        }

        return Section::where('name', $section)->value('id');
    }

    private function getClassAndSectionList(): array
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('academic_sessions') && 
                \Illuminate\Support\Facades\Schema::hasTable('school_classes') &&
                \Illuminate\Support\Facades\Schema::hasTable('class_management') &&
                \Illuminate\Support\Facades\Schema::hasTable('class_sections')) {
                
                $currentSession = \App\Models\AcademicSession::where('is_current', true)->first();
                if ($currentSession) {
                    $classList = SchoolClass::where('academic_session_id', $currentSession->id)->orderBy('class_order')->get();

                    // SchoolClass rows aren't required to carry an
                    // academic_session_id (e.g. a school that hasn't set up
                    // multi-session class scoping) -- when the session-scoped
                    // query comes back empty, fall through to the unfiltered
                    // list below rather than returning an empty dropdown that
                    // makes class filtering look broken.
                    if ($classList->isNotEmpty()) {
                        $classNames = $classList->pluck('name')->toArray();
                        $classManagementIds = \App\Models\ClassManagement::whereIn('name', $classNames)->pluck('id')->toArray();
                        $sectionIds = \DB::table('class_sections')
                            ->whereIn('class_management_id', $classManagementIds)
                            ->pluck('section_id')
                            ->toArray();
                        if (!empty($sectionIds)) {
                            $sections = Section::whereIn('id', $sectionIds)->orderBy('name')->get();
                        } else {
                            $sections = Section::orderBy('name')->get();
                        }

                        return [$classList, $sections];
                    }
                }
            }
        } catch (\Exception $e) {}

        // Fallback
        try {
            $classList = SchoolClass::orderBy('class_order')->get();
        } catch (\Exception $e) {
            $classList = collect();
        }
        try {
            $sections = Section::orderBy('name')->get();
        } catch (\Exception $e) {
            $sections = collect();
        }

        return [$classList, $sections];
    }
}
