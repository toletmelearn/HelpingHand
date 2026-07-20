<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AdminConfiguration;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\ClassManagement;

class SetupWizardController extends Controller
{
    public function __construct()
    {
        // Require admin role hierarchy (only admin users can access wizard)
        $this->middleware('role:admin');
    }

    /**
     * Redirect to the dynamic resume step.
     */
    public function index()
    {
        // If already onboarded, redirect to dashboard
        if ((bool) AdminConfiguration::get('general', 'is_onboarded', false)) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('admin.setup-wizard', ['step' => $this->getHighestIncompleteStep()]);
    }

    /**
     * Compute the highest incomplete onboarding step.
     */
    private function getHighestIncompleteStep(): int
    {
        if (!AdminConfiguration::get('general', 'school_name')) {
            return 1;
        }
        if (!AcademicSession::where('is_current', true)->where('is_active', true)->exists()) {
            return 2;
        }
        if (!SchoolClass::exists() || !ClassManagement::exists()) {
            return 3;
        }
        if (!Subject::exists()) {
            return 4;
        }
        return 5;
    }

    /**
     * Show the specified step in the setup wizard.
     */
    public function showStep(int $step)
    {
        // If already onboarded, redirect to dashboard
        if ((bool) AdminConfiguration::get('general', 'is_onboarded', false)) {
            return redirect()->route('admin.dashboard');
        }

        // Enforce step bounds
        if ($step < 1 || $step > 5) {
            return redirect()->route('admin.setup-wizard.index');
        }

        // Prevent skipping ahead of the highest incomplete step
        $highestIncompleteStep = $this->getHighestIncompleteStep();
        if ($step > $highestIncompleteStep) {
            return redirect()->route('admin.setup-wizard', ['step' => $highestIncompleteStep])
                ->with('error', 'Please complete the previous steps first.');
        }

        $data = $this->getStepData($step);

        return view('admin.setup-wizard.index', compact('step', 'data'));
    }

    /**
     * Process step submissions.
     */
    public function submitStep(int $step, Request $request)
    {
        if ($step < 1 || $step > 5) {
            return redirect()->route('admin.setup-wizard.index');
        }

        switch ($step) {
            case 1:
                return $this->processSchoolProfile($request);
            case 2:
                return $this->processAcademicSession($request);
            case 3:
                return $this->processClassesSections($request);
            case 4:
                return $this->processSubjects($request);
            case 5:
                return $this->processComplete($request);
        }
    }

    /**
     * Complete the onboarding workflow.
     */
    public function completeSetup(Request $request)
    {
        return $this->processComplete($request);
    }

    /**
     * Show reset setup confirmation form.
     */
    public function showResetForm()
    {
        // Enforce Super Admin only
        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            abort(403, 'Unauthorized action. Only Super Admin can reset the school setup.');
        }

        return view('admin.setup-wizard.reset');
    }

    /**
     * Reset the school configuration.
     */
    public function performReset(Request $request)
    {
        // Enforce Super Admin only
        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            abort(403, 'Unauthorized action. Only Super Admin can reset the school setup.');
        }

        $request->validate([
            'confirm_reset' => 'required|accepted',
        ]);

        try {
            DB::transaction(function () {
                // Delete pivot mapping first
                DB::table('class_sections')->delete();
                
                // Delete classes, sections, subjects, sessions
                ClassManagement::query()->delete();
                SchoolClass::query()->delete();
                Section::query()->delete();
                AcademicSession::query()->delete();

                // Delete general config settings
                AdminConfiguration::where('module', 'general')->delete();

                // Explicitly set is_onboarded config to false
                AdminConfiguration::set('general', 'is_onboarded', false, 'boolean');
            });

            return redirect()->route('admin.setup-wizard.index')
                ->with('success', 'School configuration has been successfully reset.');

        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()
                ->with('error', 'Cannot reset setup: Existing operational data (such as students, teachers, or billing logs) is referencing the current configurations. Please remove that data first.');
        }
    }

    /**
     * Get pre-existing data or defaults for rendering setup views.
     */
    private function getStepData(int $step): array
    {
        switch ($step) {
            case 1:
                return [
                    'school_name' => AdminConfiguration::get('general', 'school_name', ''),
                    'school_email' => AdminConfiguration::get('general', 'school_email', ''),
                    'school_phone' => AdminConfiguration::get('general', 'school_phone', ''),
                    'school_address' => AdminConfiguration::get('general', 'school_address', ''),
                    'school_logo' => AdminConfiguration::get('general', 'school_logo', ''),
                    'payment_qr' => AdminConfiguration::get('general', 'payment_qr', ''),
                ];
            case 2:
                $latest = AcademicSession::latest()->first();
                return [
                    'name' => $latest ? $latest->name : date('Y') . '-' . substr(date('Y') + 1, -2),
                    'code' => $latest ? $latest->code : 'ACAD-' . date('Y'),
                    'start_date' => $latest ? $latest->start_date?->format('Y-m-d') : date('Y') . '-04-01',
                    'end_date' => $latest ? $latest->end_date?->format('Y-m-d') : (date('Y') + 1) . '-03-31',
                ];
            case 3:
                // Predefined list of standard school classes and default sections
                return [
                    'available_classes' => [
                        ['name' => 'Nursery', 'order' => 1],
                        ['name' => 'LKG', 'order' => 2],
                        ['name' => 'UKG', 'order' => 3],
                        ['name' => 'Class 1', 'order' => 4],
                        ['name' => 'Class 2', 'order' => 5],
                        ['name' => 'Class 3', 'order' => 6],
                        ['name' => 'Class 4', 'order' => 7],
                        ['name' => 'Class 5', 'order' => 8],
                        ['name' => 'Class 6', 'order' => 9],
                        ['name' => 'Class 7', 'order' => 10],
                        ['name' => 'Class 8', 'order' => 11],
                        ['name' => 'Class 9', 'order' => 12],
                        ['name' => 'Class 10', 'order' => 13],
                        ['name' => 'Class 11 Science', 'order' => 14],
                        ['name' => 'Class 11 Commerce', 'order' => 15],
                        ['name' => 'Class 11 Arts', 'order' => 16],
                        ['name' => 'Class 12 Science', 'order' => 17],
                        ['name' => 'Class 12 Commerce', 'order' => 18],
                        ['name' => 'Class 12 Arts', 'order' => 19],
                    ],
                    'available_sections' => ['A', 'B', 'C', 'D'],
                    'configured_classes' => SchoolClass::pluck('name')->toArray()
                ];
            case 4:
                return [
                    'predefined_subjects' => [
                        'Mathematics', 'English', 'Science', 'Social Studies', 'Hindi',
                        'Computer Science', 'Art', 'Physical Education', 'Physics',
                        'Chemistry', 'Biology', 'Accountancy', 'Business Studies', 'Economics',
                        'History', 'Geography', 'Political Science'
                    ],
                    'configured_subjects' => Subject::pluck('name')->toArray()
                ];
            case 5:
                return [
                    'school_name' => AdminConfiguration::get('general', 'school_name'),
                    'session_name' => AcademicSession::current()->value('name'),
                    'class_count' => SchoolClass::count(),
                    'section_count' => Section::count(),
                    'subject_count' => Subject::count()
                ];
        }
        return [];
    }

    private function processSchoolProfile(Request $request)
    {
        $validated = $request->validate([
            'school_name' => 'required|string|max:255',
            'school_email' => 'required|email|max:255',
            'school_phone' => 'required|string|max:50',
            'school_address' => 'required|string|max:500',
            'school_logo' => 'nullable|image|max:2048',
            'payment_qr' => 'nullable|image|max:2048',
        ]);

        AdminConfiguration::set('general', 'school_name', $validated['school_name'], 'string');
        AdminConfiguration::set('general', 'school_email', $validated['school_email'], 'string');
        AdminConfiguration::set('general', 'school_phone', $validated['school_phone'], 'string');
        AdminConfiguration::set('general', 'school_address', $validated['school_address'], 'string');

        // Handle file uploads
        if ($request->hasFile('school_logo')) {
            $path = $request->file('school_logo')->store('school', 'public');
            AdminConfiguration::set('general', 'school_logo', $path, 'string');
        }
        if ($request->hasFile('payment_qr')) {
            $path = $request->file('payment_qr')->store('school', 'public');
            AdminConfiguration::set('general', 'payment_qr', $path, 'string');
        }

        return redirect()->route('admin.setup-wizard', ['step' => 2])
            ->with('success', 'School profile saved successfully!');
    }

    private function processAcademicSession(Request $request)
    {
        $latest = AcademicSession::latest()->first();
        $latestId = $latest ? $latest->id : 'NULL';

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:academic_sessions,name,' . $latestId . ',id',
            'code' => 'required|string|max:50|unique:academic_sessions,code,' . $latestId . ',id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        DB::transaction(function () use ($validated, $latest) {
            // Set all existing sessions as not current
            AcademicSession::query()->update(['is_current' => false]);

            if ($latest) {
                $latest->update([
                    'name' => $validated['name'],
                    'code' => $validated['code'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'is_current' => true,
                    'is_active' => true,
                ]);
            } else {
                AcademicSession::create([
                    'name' => $validated['name'],
                    'code' => $validated['code'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'is_current' => true,
                    'is_active' => true,
                ]);
            }
        });

        return redirect()->route('admin.setup-wizard', ['step' => 3])
            ->with('success', 'Academic session initialized successfully!');
    }

    private function processClassesSections(Request $request)
    {
        $validated = $request->validate([
            'classes' => 'required|array|min:1',
            'classes.*' => 'string',
            'sections' => 'required|array|min:1',
            'sections.*' => 'array',
        ]);

        DB::transaction(function () use ($validated) {
            // Build standard classes list to resolve orders
            $orderMap = [
                'Nursery' => 1, 'LKG' => 2, 'UKG' => 3, 'Class 1' => 4, 'Class 2' => 5,
                'Class 3' => 6, 'Class 4' => 7, 'Class 5' => 8, 'Class 6' => 9, 'Class 7' => 10,
                'Class 8' => 11, 'Class 9' => 12, 'Class 10' => 13, 'Class 11 Science' => 14,
                'Class 11 Commerce' => 15, 'Class 11 Arts' => 16, 'Class 12 Science' => 17,
                'Class 12 Commerce' => 18, 'Class 12 Arts' => 19,
            ];

            // 1. Create sections in database
            $sectionModels = [];
            foreach ($validated['sections'] as $className => $sectionNames) {
                foreach ($sectionNames as $name) {
                    if (!isset($sectionModels[$name])) {
                        $sectionModels[$name] = Section::firstOrCreate(
                            ['name' => $name],
                            [
                                'capacity' => 40,
                                'description' => "Section {$name}",
                            ]
                        );
                    }
                }
            }

            // 2. Create canonical classes and legacy ClassManagement entries
            foreach ($validated['classes'] as $className) {
                $order = $orderMap[$className] ?? 100;
                
                // Canonical Class
                SchoolClass::firstOrCreate(
                    ['name' => $className],
                    [
                        'class_order' => $order,
                        'description' => "Grade {$className}",
                        'is_active' => true,
                    ]
                );

                // Legacy ClassManagement entries (representing class sections for compatibility)
                $selectedSections = $validated['sections'][$className] ?? [];
                foreach ($selectedSections as $secName) {
                    $legacyName = $className;
                    $legacySec = $secName;
                    $legacyStream = '';

                    // Resolve legacy streams
                    if (str_contains($className, 'Science')) {
                        $legacyName = str_replace(' Science', '', $className);
                        $legacySec = 'Science';
                        $legacyStream = 'Science';
                    } elseif (str_contains($className, 'Commerce')) {
                        $legacyName = str_replace(' Commerce', '', $className);
                        $legacySec = 'Commerce';
                        $legacyStream = 'Commerce';
                    } elseif (str_contains($className, 'Arts')) {
                        $legacyName = str_replace(' Arts', '', $className);
                        $legacySec = 'Arts';
                        $legacyStream = 'Arts';
                    }

                    $legacyClass = ClassManagement::firstOrCreate(
                        [
                            'name' => $legacyName,
                            'section' => $legacySec,
                            'stream' => $legacyStream
                        ],
                        [
                            'order' => $order,
                            'capacity' => 40,
                            'description' => "{$className} - Section {$secName}",
                            'is_active' => true
                        ]
                    );

                    // Map Section in Pivot Table class_sections
                    $secModel = $sectionModels[$secName];
                    DB::table('class_sections')->updateOrInsert(
                        [
                            'class_management_id' => $legacyClass->id,
                            'section_id' => $secModel->id
                        ],
                        ['assigned_at' => now()]
                    );
                }
            }
        });

        return redirect()->route('admin.setup-wizard', ['step' => 4])
            ->with('success', 'Classes and sections created successfully!');
    }

    private function processSubjects(Request $request)
    {
        $validated = $request->validate([
            'subjects' => 'required|array|min:1',
            'subjects.*' => 'string|max:100',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['subjects'] as $subjectName) {
                // Determine a subject code
                $code = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $subjectName), 0, 4)) . '-' . rand(100, 999);

                Subject::firstOrCreate(
                    ['name' => $subjectName],
                    [
                        'code' => $code,
                        'type' => 'theory',
                        'is_active' => true,
                    ]
                );
            }
        });

        return redirect()->route('admin.setup-wizard', ['step' => 5])
            ->with('success', 'Core subjects added successfully!');
    }

    /**
     * Verify that all setup steps are validly configured before completing.
     */
    private function validateSetupState(): ?string
    {
        if (!AdminConfiguration::get('general', 'school_name')) {
            return 'School profile configuration is missing.';
        }
        if (!AcademicSession::where('is_current', true)->where('is_active', true)->exists()) {
            return 'Academic session configuration is missing.';
        }
        if (!SchoolClass::exists() || !ClassManagement::exists()) {
            return 'No school classes have been created.';
        }
        if (!Section::exists()) {
            return 'No class sections have been created.';
        }
        if (!Subject::exists()) {
            return 'No academic subjects have been created.';
        }
        return null;
    }

    private function processComplete(Request $request)
    {
        $request->validate([
            'confirm_setup' => 'required|accepted',
        ]);

        $validationError = $this->validateSetupState();
        if ($validationError) {
            return redirect()->route('admin.setup-wizard', ['step' => $this->getHighestIncompleteStep()])
                ->with('error', 'Cannot complete setup: ' . $validationError);
        }

        // Complete onboarding
        AdminConfiguration::set('general', 'is_onboarded', true, 'boolean');

        return redirect()->route('admin.dashboard')
            ->with('success', 'Setup Wizard completed! Welcome to HelpingHand ERP.');
    }
}
