<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UdiseStudentsExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\StudentController;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Helpers\FieldPermissionHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class AdminStudentController extends Controller
{
    /**
     * Display a listing of students grouped by class and section.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Student::class);

        // Check if class and section filters are applied
        $classId = $request->get('class_id');
        $sectionId = $request->get('section_id');
        $section = $request->get('section');
        $search = $request->get('search');
        $createdDate = $request->get('created_date');
        $aadhaarMismatch = $request->boolean('aadhaar_mismatch');

        if ($classId || $sectionId || $section || $search || $createdDate || $aadhaarMismatch) {
            // If filters are applied, show students list
            $query = Student::query();

            if ($aadhaarMismatch) {
                $query->aadhaarNameMismatch();
            }

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

            if ($createdDate) {
                // Matches AccountantDashboardController's own definition of
                // "Today's Admissions" -- new student rows created that day,
                // not a separate admission-date field (this table has none).
                $query->whereDate('created_at', $createdDate);
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
                'aadhaarMismatch' => $aadhaarMismatch,
                'showingStudents' => true
            ]);
        } else {
            // Show class-section grouped list.
            //
            // section_id is the reliable, always-populated FK (verified: 0 of
            // 980 students have a null section_id) -- the legacy `section`
            // string column is just a denormalized copy that's gone stale
            // for some students (25 currently blank/null despite a valid
            // section_id). Grouping by that legacy string too, as this used
            // to, split a single real class+section into multiple phantom
            // cards whenever a student's copy didn't match everyone else's
            // (e.g. 4 Nursery-A students with section=null showing as a
            // separate "N/A" card from the other 33 Nursery-A students).
            // Group by (class_id, section_id) only and resolve the display
            // name from the Section relation, never the stale string.
            $classSections = Student::select(
                                      DB::raw('COALESCE(school_class_id, class_id) as class_id'),
                                      'section_id',
                                      DB::raw('COUNT(*) as total')
                                  )
                                  ->groupBy(DB::raw('COALESCE(school_class_id, class_id)'), 'section_id')
                                  ->orderBy(DB::raw('COALESCE(school_class_id, class_id)'))
                                  ->get();

            // Map the coalesced class_id value to school_class_id to allow eager loading to match
            $classSections->each(function($item) {
                $item->school_class_id = $item->class_id;
            });

            // Load the schoolClass and section relations on the collection.
            // schoolClass must include class_order -- it's used to sort this
            // list into the real Nursery/LKG/UKG/Class 1.../Class 12
            // sequence below; leaving it out of the restricted select left
            // every row's class_order silently null, so the sort fell back
            // to its default for every item and grouped by section letter
            // instead of by class.
            $classSections->load(['schoolClass' => function($query) {
                $query->select('id', 'name', 'class_order');
            }, 'section' => function($query) {
                $query->select('id', 'name');
            }]);

            // Sort by class order, then section name, now that the section
            // name comes from the relation instead of the raw column.
            // Collection::sortBy() with an array of criteria (multi-column
            // sort) does not reliably order by the first criterion when
            // given plain closures -- sorting by a single zero-padded
            // composite string is the robust way to do this.
            $classSections = $classSections->sortBy(function ($item) {
                return sprintf('%05d-%s', $item->schoolClass->class_order ?? PHP_INT_MAX, $item->section->name ?? '');
            })->values();

            [$classList, $sections] = $this->getClassAndSectionList();

            // One row per class (not per class+section) with its total
            // student count across every section, for the "Delete Class &
            // Students" action -- that's a class-level operation, not a
            // per-section one.
            $classTotals = $classSections->groupBy('class_id')->map(function ($group) {
                $first = $group->first();
                return (object) [
                    'class_id' => $first->class_id,
                    'schoolClass' => $first->schoolClass,
                    'total' => $group->sum('total'),
                ];
            })->sortBy(fn($item) => $item->schoolClass->class_order ?? PHP_INT_MAX)->values();

            $academicSessions = \App\Models\AcademicSession::orderByDesc('id')->get();

            return view('admin.students.index', [
                'classSections' => $classSections,
                'classTotals' => $classTotals,
                'academicSessions' => $academicSessions,
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
        $this->authorize('viewAny', Student::class);

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
        $this->authorize('viewAny', Student::class);

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
            'aadhaar_number' => 'required|digits:12|unique:students',
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
            'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-,unknown',
            'udise_pen' => 'nullable|string|max:255|unique:students,udise_pen',
            'apaar_id' => 'nullable|digits:12|unique:students,apaar_id',
            'name_as_per_aadhaar' => 'nullable|string|max:255',
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
     * Export all students in the column set UDISE+ student import requires.
     */
    public function exportUdise()
    {
        $this->authorize('viewAny', Student::class);

        return Excel::download(new UdiseStudentsExport(), 'udise-students-' . date('Y-m-d') . '.xlsx');
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
            'aadhaar_number' => 'required|digits:12|unique:students,aadhaar_number,'.$id,
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
            'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-,unknown',
            'udise_pen' => 'nullable|string|max:255|unique:students,udise_pen,'.$id,
            'apaar_id' => 'nullable|digits:12|unique:students,apaar_id,'.$id,
            'name_as_per_aadhaar' => 'nullable|string|max:255',
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
     * Record (or withdraw) APAAR consent for a student. Deliberately
     * separate from update() -- apaar_consent_given/date/by are DPDP-
     * relevant consent records and are not in Student::$fillable, so they
     * can only be set here, via direct property assignment, never through
     * the generic edit form.
     */
    public function recordApaarConsent(Request $request, $id)
    {
        $this->authorize('update', [Student::class, Student::findOrFail($id)]);
        $student = Student::findOrFail($id);

        $validated = $request->validate([
            'apaar_consent_given' => 'required|boolean',
            'apaar_consent_by' => 'required_if:apaar_consent_given,1|nullable|string|max:255',
        ]);

        $consentGiven = (bool) $validated['apaar_consent_given'];

        $student->apaar_consent_given = $consentGiven;
        $student->apaar_consent_by = $consentGiven ? $validated['apaar_consent_by'] : null;
        $student->apaar_consent_date = $consentGiven ? now()->toDateString() : null;
        $student->save();

        return redirect()->route('admin.students.show', $student->id)
            ->with('success', $consentGiven ? 'APAAR consent recorded successfully.' : 'APAAR consent withdrawn.');
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
            $validated['section'] = $section->name;
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
