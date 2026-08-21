<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\BellScheduleController;
use App\Http\Controllers\SpecialDayOverrideController;
use App\Http\Controllers\ClassTeacherAssignmentController;
use App\Http\Controllers\FieldPermissionController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\ResultEntryController;
use App\Http\Controllers\ResultVerificationController;
use App\Http\Controllers\FinalResultController;
use App\Http\Controllers\Parent\ParentAuthController;
use App\Http\Controllers\Parent\ParentDashboardController;

// ============================================================================
// DEBUG/TEST ROUTES - ONLY AVAILABLE IN LOCAL ENVIRONMENT
// These routes are disabled in production for security
// ============================================================================
if (app()->environment('local')) {
    // Temporary test route for Subject fix
    Route::get('/test-subject-fix', function () {
        try {
            // Test if withTrashed works
            $subjects = \App\Models\Subject::withTrashed()->count();
            return response()->json([
                'status' => 'success',
                'message' => 'Subject model withTrashed() works correctly',
                'count' => $subjects
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    });

    // Test route for exam details endpoint
    Route::get('/test-exam-details/{id}', function ($id) {
        try {
            $exam = \App\Models\Exam::findOrFail($id);
            return response()->json([
                'status' => 'success',
                'exam_id' => $exam->id,
                'exam_name' => $exam->name,
                'subject' => $exam->subject,
                'total_marks' => $exam->total_marks,
                'passing_marks' => $exam->passing_marks,
                'message' => 'Exam details retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    });

    // List all exams route
    Route::get('/list-all-exams', function () {
        try {
            $exams = \App\Models\Exam::all(['id', 'name', 'subject', 'total_marks', 'passing_marks']);
            
            return response()->json([
                'status' => 'success',
                'count' => $exams->count(),
                'exams' => $exams,
                'message' => 'Found ' . $exams->count() . ' exams'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    });
}

Route::get('/', [App\Http\Controllers\HomeController::class, 'welcome']);

// Public Admission Enquiry Form (no login required)
Route::middleware(['throttle:5,1'])->group(function () {
    Route::get('/admissions/apply', [App\Http\Controllers\PublicAdmissionController::class, 'showForm'])->name('admissions.apply');
    Route::post('/admissions/apply', [App\Http\Controllers\PublicAdmissionController::class, 'submit'])->name('admissions.apply.submit');
});

// Central Multi-Role Login Routes (Admin, Teacher, Parent)
Route::middleware(['guest:web'])->group(function () {
    Route::get('/login', [App\Http\Controllers\Auth\CentralLoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [App\Http\Controllers\Auth\CentralLoginController::class, 'login']);
});

Route::middleware(['auth:web'])->group(function () {
    Route::get('/admin/dashboard', [App\Http\Controllers\Admin\AdminDashboardController::class, 'index']);
});

Route::post('/logout', [App\Http\Controllers\Auth\CentralLoginController::class, 'logout'])->name('logout');


Route::prefix('parent')->group(function () {

    // Public routes
    Route::get('/login', [ParentAuthController::class,'showLogin'])
        ->name('parent.login');

    Route::post('/login', [ParentAuthController::class,'login'])
        ->name('parent.login.post');
    
    // Protected routes - require parent authentication
    Route::middleware('parent.auth')->group(function () {

        Route::get('/dashboard', [ParentDashboardController::class,'index'])
            ->name('parent.dashboard');

        Route::get('/payment-history', [ParentDashboardController::class,'paymentHistory'])
            ->name('parent.payment.history');

        Route::get('/fee-structure', [ParentDashboardController::class,'feeStructure'])
            ->name('parent.fee.structure');

        Route::get('/receipt/{id}', [ParentDashboardController::class,'downloadReceipt'])
            ->name('parent.receipt.download');
        
        // Exam Papers Routes
        Route::get('/exam-papers', [App\Http\Controllers\Parent\ParentExamPaperController::class, 'index'])
            ->name('parent.exam-papers.index');
        Route::get('/exam-papers/{id}/download', [App\Http\Controllers\Parent\ParentExamPaperController::class, 'download'])
            ->name('parent.exam-papers.download');
        Route::get('/exam-papers/{id}', [App\Http\Controllers\Parent\ParentExamPaperController::class, 'show'])
            ->name('parent.exam-papers.show');

        Route::match(['GET', 'POST'], '/logout', [ParentAuthController::class,'logout'])
            ->name('parent.logout');

        Route::post('/switch-student/{id}', [ParentAuthController::class, 'switchStudent'])
            ->name('parent.switch-student');

        // Forced password reset (auto-created / admin-reset parent accounts)
        Route::get('/reset-password', [ParentAuthController::class, 'showResetPasswordForm'])
            ->name('parent.password.reset');
        Route::post('/reset-password', [ParentAuthController::class, 'updatePassword'])
            ->name('parent.password.update');
    });
});

// ==================== TEACHER ROUTES ====================
Route::prefix('teacher')->group(function () {
    
    // Public routes
    Route::get('/login', [App\Http\Controllers\Teacher\TeacherAuthController::class, 'showLogin'])
        ->name('teacher.login');
    Route::post('/login', [App\Http\Controllers\Teacher\TeacherAuthController::class, 'login'])
        ->name('teacher.login.post');
    
    // Protected routes - require teacher authentication
    Route::middleware(App\Http\Middleware\TeacherAuth::class)->group(function () {
        
        // optional direct /teacher redirect
        Route::get('/', function () {
            return redirect()->route('teacher.dashboard');
        });
        
        Route::get('/dashboard', [App\Http\Controllers\Teacher\TeacherDashboardController::class, 'index'])
            ->name('teacher.dashboard');

        // Timetable pilot-completion pass (Phase 2): weekly view, alongside
        // the dashboard's existing "today" card. No route parameter --
        // always the authenticated teacher's own timetable.
        Route::get('/timetable', [App\Http\Controllers\Teacher\TeacherTimetableController::class, 'index'])
            ->name('teacher.timetable');

        // My Classes
        Route::get('/my-classes', [App\Http\Controllers\Teacher\TeacherClassController::class, 'index'])
            ->name('teacher.classes');
        Route::get('/my-classes/{classId}/students', [App\Http\Controllers\Teacher\TeacherClassController::class, 'students'])
            ->name('teacher.classes.students');
        
        // Marks Management
        Route::get('/marks/upload', [App\Http\Controllers\Teacher\TeacherMarksController::class, 'index'])
            ->name('teacher.marks.index');
        Route::get('/marks/upload/form', [App\Http\Controllers\Teacher\TeacherMarksController::class, 'uploadForm'])
            ->name('teacher.marks.upload');
        
        // EXAM ROUTES
        Route::get('/exams', [App\Http\Controllers\Teacher\TeacherExamController::class, 'index'])->name('teacher.exams.index');
        Route::get('/exams/create', [App\Http\Controllers\Teacher\TeacherExamController::class, 'create'])->name('teacher.exams.create');
        Route::post('/exams/store', [App\Http\Controllers\Teacher\TeacherExamController::class, 'store'])->name('teacher.exams.store');
        Route::get('/exams/{exam}', [App\Http\Controllers\Teacher\TeacherExamController::class, 'show'])->name('teacher.exams.show');  // ⭐ ADD THIS
        Route::get('/exams/{exam}/edit', [App\Http\Controllers\Teacher\TeacherExamController::class, 'edit'])->name('teacher.exams.edit');
        Route::post('/exams/{exam}/update', [App\Http\Controllers\Teacher\TeacherExamController::class, 'update'])->name('teacher.exams.update');
        Route::delete('/exams/{exam}/delete', [App\Http\Controllers\Teacher\TeacherExamController::class, 'destroy'])->name('teacher.exams.destroy');
        
        Route::get('/marks/upload/{examId}', [App\Http\Controllers\Teacher\TeacherMarksController::class, 'show'])
            ->name('teacher.marks.show');
        Route::post('/marks/save', [App\Http\Controllers\Teacher\TeacherMarksController::class, 'store'])
            ->name('teacher.marks.store');
        
        // Homework
        Route::resource('homework', App\Http\Controllers\Teacher\TeacherHomeworkController::class)->names([
            'index' => 'teacher.homework.index',
            'create' => 'teacher.homework.create',
            'store' => 'teacher.homework.store',
            'show' => 'teacher.homework.show',
            'edit' => 'teacher.homework.edit',
            'update' => 'teacher.homework.update',
            'destroy' => 'teacher.homework.destroy',
        ]);
        
        // Attendance Management
        Route::get('/attendance/dashboard', [App\Http\Controllers\Teacher\TeacherAttendanceController::class, 'dashboard'])
            ->name('teacher.attendance.dashboard');
        Route::get('/attendance/mark/{classId}', [App\Http\Controllers\Teacher\TeacherAttendanceController::class, 'markAttendance'])
            ->name('teacher.attendance.mark');
        Route::post('/attendance/store', [App\Http\Controllers\Teacher\TeacherAttendanceController::class, 'storeAttendance'])
            ->name('teacher.attendance.store');
        Route::get('/attendance/reports', [App\Http\Controllers\Teacher\TeacherAttendanceController::class, 'reports'])
            ->name('teacher.attendance.reports');
        Route::get('/attendance/student/{studentId}', [App\Http\Controllers\Teacher\TeacherAttendanceController::class, 'studentAttendance'])
            ->name('teacher.attendance.student');
        Route::get('/attendance/{id}/edit', [App\Http\Controllers\Teacher\TeacherAttendanceController::class, 'editAttendance'])
            ->name('teacher.attendance.edit');
        Route::put('/attendance/{id}', [App\Http\Controllers\Teacher\TeacherAttendanceController::class, 'updateAttendance'])
            ->name('teacher.attendance.update');
        Route::get('/attendance/export', [App\Http\Controllers\Teacher\TeacherAttendanceController::class, 'exportAttendance'])
            ->name('teacher.attendance.export');
        
        // AJAX route for getting sections by class
        Route::get('/get-sections-by-class/{classId}', [App\Http\Controllers\Teacher\TeacherSectionController::class, 'getSectionsByClass'])->name('teacher.get.sections.by.class');
        
        // Notices (view only)
        Route::get('/notices', [App\Http\Controllers\Teacher\TeacherNoticeController::class, 'index'])
            ->name('teacher.notices');
        
        // Lesson Plan Routes
        Route::resource('lesson-plans', App\Http\Controllers\Teacher\LessonPlanController::class)->names([
            'index' => 'teacher.lesson-plans.index',
            'create' => 'teacher.lesson-plans.create',
            'store' => 'teacher.lesson-plans.store',
            'show' => 'teacher.lesson-plans.show',
            'edit' => 'teacher.lesson-plans.edit',
            'update' => 'teacher.lesson-plans.update',
            'destroy' => 'teacher.lesson-plans.destroy',
        ]);
        Route::get('/lesson-plans/history', [App\Http\Controllers\Teacher\LessonPlanController::class, 'history'])->name('teacher.lesson-plans.history');
        
        // Professional Lesson Plan Routes
        Route::resource('professional-lesson-plans', App\Http\Controllers\Teacher\ProfessionalLessonPlanController::class)->names([
            'index' => 'teacher.professional-lesson-plans.index',
            'create' => 'teacher.professional-lesson-plans.create',
            'store' => 'teacher.professional-lesson-plans.store',
            'show' => 'teacher.professional-lesson-plans.show',
            'edit' => 'teacher.professional-lesson-plans.edit',
            'update' => 'teacher.professional-lesson-plans.update',
            'destroy' => 'teacher.professional-lesson-plans.destroy',
        ]);
        
        // Profile & Password
        Route::get('/profile', [App\Http\Controllers\Teacher\TeacherProfileController::class, 'show'])
            ->name('teacher.profile');
        Route::put('/profile', [App\Http\Controllers\Teacher\TeacherProfileController::class, 'update'])
            ->name('teacher.profile.update');
        Route::get('/change-password', [App\Http\Controllers\Teacher\TeacherProfileController::class, 'changePasswordForm'])
            ->name('teacher.password.change');
        Route::post('/change-password', [App\Http\Controllers\Teacher\TeacherProfileController::class, 'changePassword'])
            ->name('teacher.password.update');
        
        // Exam Head Routes
        Route::get('/exam-head/marks', [App\Http\Controllers\Teacher\ExamHeadController::class, 'index'])
            ->name('teacher.examhead.marks');
        Route::post('/exam-head/marks/{markId}/approve', [App\Http\Controllers\Teacher\ExamHeadController::class, 'approve'])
            ->name('teacher.examhead.approve');
        Route::put('/exam-head/marks/{markId}/edit', [App\Http\Controllers\Teacher\ExamHeadController::class, 'edit'])
            ->name('teacher.examhead.edit');
        
        // Exam Papers Routes
        Route::resource('exam-papers', App\Http\Controllers\Teacher\TeacherExamPaperController::class)->names([
            'index' => 'teacher.exam-papers.index',
            'create' => 'teacher.exam-papers.create',
            'store' => 'teacher.exam-papers.store',
            'show' => 'teacher.exam-papers.show',
            'edit' => 'teacher.exam-papers.edit',
            'update' => 'teacher.exam-papers.update',
            'destroy' => 'teacher.exam-papers.destroy',
        ]);
        
        Route::post('/logout', [App\Http\Controllers\Teacher\TeacherAuthController::class, 'logout'])
            ->name('teacher.logout');
    });
});

// 🔒 BLOCK REGISTRATION - Only Admin can create users
Route::get('/register', function () {
    abort(404);
});
Route::post('/register', function () {
    abort(404);
});

Route::middleware(['auth'])->group(function () {
    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    
    // Profile Routes
    Route::get('/user/two-factor-authentication', function () {
        return redirect()->route('user.two-factor.qr-code');
    })->name('profile.two-factor-authentication');
    
    // Additional routes that need admin prefix
    Route::get('exam-papers/available', [App\Http\Controllers\Admin\ExamPaperController::class, 'availableForClass'])->name('exam-papers.available');
    
    // Student Export/Import Routes
    Route::get('students/export/csv', [App\Http\Controllers\StudentImportExportController::class, 'exportCsv'])->name('students.export.csv');
    Route::post('students/import/csv/preview', [App\Http\Controllers\StudentImportExportController::class, 'previewImportCsv'])->name('students.import.csv.preview');
    Route::post('students/import/csv/apply', [App\Http\Controllers\StudentImportExportController::class, 'applyImportCsv'])->name('students.import.csv.apply');
    Route::post('students/import/csv', [App\Http\Controllers\StudentImportExportController::class, 'importCsv'])->name('students.import.csv');
    
    // Universal Import Wizard Routes
    Route::get('imports/dashboard', [App\Http\Controllers\Admin\UniversalImportController::class, 'dashboard'])->name('imports.dashboard');
    Route::get('imports/history', [App\Http\Controllers\Admin\UniversalImportController::class, 'history'])->name('imports.history');
    Route::get('imports/mapping-profiles', [App\Http\Controllers\Admin\UniversalImportController::class, 'mappingProfiles'])->name('imports.mapping-profiles');
    Route::get('imports/templates', [App\Http\Controllers\Admin\UniversalImportController::class, 'templates'])->name('imports.templates');
    Route::get('imports/wizard/{module}/download-template', [App\Http\Controllers\Admin\UniversalImportController::class, 'downloadTemplate'])->name('imports.download-template');
    Route::get('imports/wizard/{module}/template-fields', [App\Http\Controllers\Admin\UniversalImportController::class, 'templateFields'])->name('imports.wizard.template-fields');
    Route::post('imports/wizard/{module}/template-fields', [App\Http\Controllers\Admin\UniversalImportController::class, 'updateTemplateFields'])->name('imports.wizard.template-fields.update');
    Route::get('imports/wizard/{module}', [App\Http\Controllers\Admin\UniversalImportController::class, 'showWizard'])->name('imports.wizard');
    Route::post('imports/wizard/{module}/upload', [App\Http\Controllers\Admin\UniversalImportController::class, 'upload'])->name('imports.wizard.upload');
    Route::post('imports/wizard/{module}/dry-run', [App\Http\Controllers\Admin\UniversalImportController::class, 'dryRun'])->name('imports.wizard.dry-run');
    Route::post('imports/wizard/{module}/execute', [App\Http\Controllers\Admin\UniversalImportController::class, 'execute'])->name('imports.wizard.execute');
    Route::post('imports/wizard/{module}/rollback', [App\Http\Controllers\Admin\UniversalImportController::class, 'rollback'])->name('imports.wizard.rollback');
    Route::get('imports/wizard/{module}/progress/{uuid}', [App\Http\Controllers\Admin\UniversalImportController::class, 'progress'])->name('imports.wizard.progress');
    Route::get('imports/wizard/{module}/errors/{uuid}/download', [App\Http\Controllers\Admin\UniversalImportController::class, 'downloadErrors'])->name('imports.wizard.errors.download');
    Route::post('imports/wizard/{module}/correct-row', [App\Http\Controllers\Admin\UniversalImportController::class, 'correctRow'])->name('imports.wizard.correct-row');
    
    // Operations & Diagnostics Control Routes
    Route::get('operations/dashboard', [App\Http\Controllers\Admin\OperationsController::class, 'dashboard'])->name('operations.dashboard');
    Route::get('operations/health', [App\Http\Controllers\Admin\OperationsController::class, 'health'])->name('operations.health');
    Route::get('operations/settings', [App\Http\Controllers\Admin\OperationsController::class, 'settings'])->name('operations.settings');
    Route::post('operations/settings', [App\Http\Controllers\Admin\OperationsController::class, 'updateSettings'])->name('operations.settings.update');
    
    // Disaster Recovery (Backups)
    Route::get('operations/backup', [App\Http\Controllers\Admin\OperationsController::class, 'backupIndex'])->name('operations.backup');
    Route::post('operations/backup/run', [App\Http\Controllers\Admin\OperationsController::class, 'backupRun'])->name('operations.backup.run');
    Route::post('operations/backup/restore/{id}', [App\Http\Controllers\Admin\OperationsController::class, 'backupRestore'])->name('operations.backup.restore');
    Route::get('operations/backup/download/{id}', [App\Http\Controllers\Admin\OperationsController::class, 'backupDownload'])->name('operations.backup.download');
    Route::delete('operations/backup/delete/{id}', [App\Http\Controllers\Admin\OperationsController::class, 'backupDelete'])->name('operations.backup.delete');

    // Queue Monitoring
    Route::get('operations/queue', [App\Http\Controllers\Admin\OperationsController::class, 'queueIndex'])->name('operations.queue');
    Route::post('operations/queue/retry/{id}', [App\Http\Controllers\Admin\OperationsController::class, 'queueRetry'])->name('operations.queue.retry');
    Route::post('operations/queue/retry-all', [App\Http\Controllers\Admin\OperationsController::class, 'queueRetryAll'])->name('operations.queue.retry-all');
    Route::post('operations/queue/clear-failed', [App\Http\Controllers\Admin\OperationsController::class, 'queueClearFailed'])->name('operations.queue.clear-failed');

    // Scheduler Dashboard
    Route::get('operations/scheduler', [App\Http\Controllers\Admin\OperationsController::class, 'schedulerIndex'])->name('operations.scheduler');

    // Notification Center
    Route::get('operations/notifications', [App\Http\Controllers\Admin\OperationsController::class, 'notificationsIndex'])->name('operations.notifications');
    Route::post('operations/notifications/retry/{id}', [App\Http\Controllers\Admin\OperationsController::class, 'notificationsRetry'])->name('operations.notifications.retry');

    // Environment Verification
    Route::get('operations/verification', [App\Http\Controllers\Admin\OperationsController::class, 'verificationIndex'])->name('operations.verification');
    Route::post('operations/verification/run', [App\Http\Controllers\Admin\OperationsController::class, 'verificationRun'])->name('operations.verification.run');

    // System Logs Center
    Route::get('operations/logs', [App\Http\Controllers\Admin\OperationsController::class, 'logsIndex'])->name('operations.logs');

    // Activity Timeline
    Route::get('operations/timeline', [App\Http\Controllers\Admin\OperationsController::class, 'timelineIndex'])->name('operations.timeline');

    // SaaS License & Subscription Center
    Route::get('operations/license', [App\Http\Controllers\Admin\OperationsController::class, 'licenseIndex'])->name('operations.license');
    Route::post('operations/license/activate', [App\Http\Controllers\Admin\OperationsController::class, 'licenseActivate'])->name('operations.license.activate');

    // Maintenance Mode
    Route::get('operations/maintenance', [App\Http\Controllers\Admin\OperationsController::class, 'maintenanceIndex'])->name('operations.maintenance');
    Route::post('operations/maintenance/toggle', [App\Http\Controllers\Admin\OperationsController::class, 'maintenanceToggle'])->name('operations.maintenance.toggle');

    // Performance Dashboard
    Route::get('operations/performance', [App\Http\Controllers\Admin\OperationsController::class, 'performanceIndex'])->name('operations.performance');
    
    // Attendance Routes
    Route::get('attendance/student/{studentId}/report', [App\Http\Controllers\AttendanceController::class, 'studentReport'])->name('attendance.student.report');
    
    // Notifications Routes
    Route::get('notifications', [App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/mark-all-read', [App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    
    // Results Routes
    Route::get('results', [App\Http\Controllers\Admin\ResultController::class, 'index'])->name('results.index');
    
    // Teacher Biometric Dashboard
    Route::get('teachers/biometric/dashboard', [App\Http\Controllers\Teacher\BiometricController::class, 'dashboard'])->name('teachers.biometric.dashboard');
    
    // Student Admit Cards Routes
    Route::prefix('student')->name('student.')->group(function () {
        Route::get('/admit-cards', [App\Http\Controllers\StudentAdmitCardController::class, 'index'])->name('admit-cards.index');
        Route::get('/admit-cards/{admitCard}', [App\Http\Controllers\StudentAdmitCardController::class, 'show'])->name('admit-cards.show');
        Route::get('/admit-cards/{admitCard}/download-pdf', [App\Http\Controllers\StudentAdmitCardController::class, 'downloadPdf'])->name('admit-cards.download-pdf');
    });
    
    // Global students routes
    Route::get('students', [App\Http\Controllers\Admin\AdminStudentController::class, 'index'])->name('students.index');
    Route::post('students/{student}/photo', [App\Http\Controllers\Admin\AdminStudentController::class, 'updatePhoto'])->name('students.photo.update');
    // Phase 3G: legacy student create route redirected to canonical admin student create to avoid route-name/API confusion.
    Route::get('students/create', function () {
        return redirect()->route('admin.students.create');
    })->name('students.create');
    
    // Student Daily Teaching Work Routes
    Route::prefix('student')->name('student.')->group(function () {
        Route::get('/daily-teaching-work', [App\Http\Controllers\StudentDailyTeachingWorkController::class, 'index'])->name('daily-teaching-work.index');
        Route::get('/daily-teaching-work/{id}', [App\Http\Controllers\StudentDailyTeachingWorkController::class, 'show'])->name('daily-teaching-work.show');
        Route::get('/daily-teaching-work/{dailyTeachingWork}/download-attachment/{index}', [App\Http\Controllers\StudentDailyTeachingWorkController::class, 'downloadAttachment'])->name('daily-teaching-work.download-attachment');
    });
    
    // Student Results Routes
    Route::prefix('student')->name('student.')->group(function () {
        Route::get('/results', [App\Http\Controllers\StudentResultController::class, 'index'])->name('results.index');
        Route::get('/results/{result}', [App\Http\Controllers\StudentResultController::class, 'show'])->name('results.show');
        Route::get('/results/{result}/generate-pdf', [App\Http\Controllers\StudentResultController::class, 'generatePdf'])->name('results.generate-pdf');
        
        // Exam Papers Routes
        Route::get('/exam-papers', [App\Http\Controllers\Student\StudentExamPaperController::class, 'index'])->name('exam-papers.index');
        Route::get('/exam-papers/{id}/download', [App\Http\Controllers\Student\StudentExamPaperController::class, 'download'])->name('exam-papers.download');
        Route::get('/exam-papers/{id}', [App\Http\Controllers\Student\StudentExamPaperController::class, 'show'])->name('exam-papers.show');
    });
    


    

    
    // User Routes
    Route::resource('users', UserController::class);
    
    // Student Routes (commented out to avoid conflict with admin routes)
    // Route::resource('students', StudentController::class);
    
    // Teacher Routes
    Route::resource('teachers', TeacherController::class);
    Route::post('teachers/{teacher}/photo', [TeacherController::class, 'updatePhoto'])->name('teachers.photo.update');
    
    // Bell Timing Routes -- these literal-path routes must be registered
    // BEFORE Route::resource(), otherwise the resource's show route
    // (GET bell-timing/{bell_timing}) matches first and swallows them
    // (e.g. GET /bell-timing/weekly resolves bell_timing='weekly', 404s
    // on the model lookup instead of ever reaching weeklyTimetable()).
    // Confirmed via direct router match() before/after reordering.
    Route::get('/bell-timing/weekly', [App\Http\Controllers\BellTimingController::class, 'weeklyTimetable'])->name('bell-timing.weekly');
    Route::get('/bell-timing/daily', [App\Http\Controllers\BellTimingController::class, 'todaysSchedule'])->name('bell-timing.daily');
    Route::get('/bell-timing/bulk-create', [App\Http\Controllers\BellTimingController::class, 'bulkCreate'])->name('bell-timing.bulk-create');
    Route::post('/bell-timing/bulk-create', [App\Http\Controllers\BellTimingController::class, 'bulkCreate'])->name('bell-timing.bulk-create.process');
    // Bulk Delete -- same literal-before-resource requirement as the
    // routes above. Selection is always by (class_section, day_of_week,
    // academic_year, semester) tuples, never by raw BellTiming id --
    // preview and confirm both re-resolve the actual ids server-side on
    // every request (see BellTimingController::bulkDeletePreview()/
    // bulkDeleteConfirm()).
    Route::get('/bell-timing/bulk-delete', [App\Http\Controllers\BellTimingController::class, 'bulkDeleteForm'])->name('bell-timing.bulk-delete');
    Route::post('/bell-timing/bulk-delete/preview', [App\Http\Controllers\BellTimingController::class, 'bulkDeletePreview'])->name('bell-timing.bulk-delete.preview');
    Route::post('/bell-timing/bulk-delete/confirm', [App\Http\Controllers\BellTimingController::class, 'bulkDeleteConfirm'])->name('bell-timing.bulk-delete.confirm');
    Route::get('/bell-timing/print/{classSection?}/{academicYear?}', [App\Http\Controllers\BellTimingController::class, 'printTimetable'])->name('bell-timing.print');
    // Same literal-before-resource requirement as above: these two also sit
    // under /bell-timing/... and must be registered before the resource's
    // GET bell-timing/{bell_timing} show route.
    Route::get('/bell-timing/save-as-template', [App\Http\Controllers\BellTimingTemplateController::class, 'saveAsTemplateForm'])->name('bell-timing.save-as-template');
    Route::post('/bell-timing/save-as-template', [App\Http\Controllers\BellTimingTemplateController::class, 'saveAsTemplateStore'])->name('bell-timing.save-as-template.store');
    Route::resource('bell-timing', App\Http\Controllers\BellTimingController::class);

    // Bell Timing Templates -- a completely separate URL segment from
    // /bell-timing/..., so no collision risk with the resource route above,
    // but the same literal-before-resource discipline is kept anyway.
    Route::get('/bell-timing-templates/{bellTimingTemplate}/apply', [App\Http\Controllers\BellTimingTemplateController::class, 'applyForm'])->name('bell-timing-templates.apply.form');
    // Post/Redirect/Get: the POST validates the selection and stashes it in
    // the session, then redirects to the GET route below so the resulting
    // page is safely refreshable/bookmarkable instead of sitting at a
    // POST-only URL (see bell-timing-templates.apply.preview.show).
    Route::post('/bell-timing-templates/{bellTimingTemplate}/apply/preview', [App\Http\Controllers\BellTimingTemplateController::class, 'applyPreview'])->name('bell-timing-templates.apply.preview');
    Route::get('/bell-timing-templates/{bellTimingTemplate}/apply/preview', [App\Http\Controllers\BellTimingTemplateController::class, 'applyPreviewShow'])->name('bell-timing-templates.apply.preview.show');
    Route::post('/bell-timing-templates/{bellTimingTemplate}/apply/confirm', [App\Http\Controllers\BellTimingTemplateController::class, 'applyConfirm'])->name('bell-timing-templates.apply.confirm');
    Route::post('/bell-timing-templates/{bellTimingTemplate}/duplicate', [App\Http\Controllers\BellTimingTemplateController::class, 'duplicate'])->name('bell-timing-templates.duplicate');
    Route::resource('bell-timing-templates', App\Http\Controllers\BellTimingTemplateController::class)
        ->except(['show'])
        ->parameters(['bell-timing-templates' => 'bellTimingTemplate']);

    // Removed legacy user-facing exam-papers routes. All routing is consolidated under Admin namespace.
    
    // (debug route removed)

    // Setup Wizard routes (bypass onboarding check)
    Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified'])->group(function () {
        Route::get('setup-wizard', [App\Http\Controllers\Admin\SetupWizardController::class, 'index'])
            ->name('setup-wizard.index');
        Route::get('setup-wizard/step/{step}', [App\Http\Controllers\Admin\SetupWizardController::class, 'showStep'])
            ->name('setup-wizard');
        Route::post('setup-wizard/step/{step}', [App\Http\Controllers\Admin\SetupWizardController::class, 'submitStep'])
            ->name('setup-wizard.submit');
        Route::post('setup-wizard/complete', [App\Http\Controllers\Admin\SetupWizardController::class, 'completeSetup'])
            ->name('setup-wizard.complete');
        Route::get('setup-wizard/reset', [App\Http\Controllers\Admin\SetupWizardController::class, 'showResetForm'])
            ->name('setup-wizard.reset');
        Route::post('setup-wizard/reset', [App\Http\Controllers\Admin\SetupWizardController::class, 'performReset'])
            ->name('setup-wizard.reset.perform');
    });

    // Admin routes
    Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'redirect.if.not.onboarded'])->group(function () {
        // Admin Dashboard Route
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        
        // Account Management Routes
        Route::get('accounts', [App\Http\Controllers\Admin\AccountManagementController::class, 'index'])->name('accounts.index');
        Route::post('accounts/change-password', [App\Http\Controllers\Admin\AccountManagementController::class, 'changePassword'])->name('accounts.change-password');
        Route::post('accounts/toggle-status', [App\Http\Controllers\Admin\AccountManagementController::class, 'toggleStatus'])->name('accounts.toggle-status');
        Route::post('accounts/sync', [App\Http\Controllers\Admin\AccountManagementController::class, 'syncAccounts'])->name('accounts.sync');
        
        // Admin specific students view (class & section wise)
        Route::get('students', [App\Http\Controllers\Admin\AdminStudentController::class, 'index'])->name('students.index');
        Route::get('students-class-wise', [App\Http\Controllers\Admin\AdminStudentController::class, 'index'])->name('admin.students.class-wise');
        
        // Direct student CRUD routes for intuitive access
        Route::get('students/create', [App\Http\Controllers\Admin\AdminStudentController::class, 'create'])->name('students.create');
        Route::post('students', [App\Http\Controllers\Admin\AdminStudentController::class, 'store'])->name('students.store');
        // Must come before the {student} wildcard routes below.
        Route::post('students/bulk-destroy', [App\Http\Controllers\Admin\AdminStudentController::class, 'bulkDestroy'])->name('students.bulk-destroy');
        Route::get('students/export/udise', [App\Http\Controllers\Admin\AdminStudentController::class, 'exportUdise'])->name('students.export.udise');
        Route::get('students/{student}', [App\Http\Controllers\Admin\AdminStudentController::class, 'show'])->name('students.show');
        Route::get('students/{student}/edit', [App\Http\Controllers\Admin\AdminStudentController::class, 'edit'])->name('students.edit');
        Route::put('students/{student}', [App\Http\Controllers\Admin\AdminStudentController::class, 'update'])->name('students.update');
        Route::delete('students/{student}', [App\Http\Controllers\Admin\AdminStudentController::class, 'destroy'])->name('students.destroy');
        Route::post('students/{student}/apaar-consent', [App\Http\Controllers\Admin\AdminStudentController::class, 'recordApaarConsent'])->name('students.apaar-consent');

        // Parent Directory & Management
        Route::get('parents', [\App\Http\Controllers\Admin\AdminParentController::class, 'index'])->name('parents.index');
        Route::get('parents/{id}', [\App\Http\Controllers\Admin\AdminParentController::class, 'show'])->name('parents.show');
        Route::put('parents/{id}', [\App\Http\Controllers\Admin\AdminParentController::class, 'update'])->name('parents.update');
        Route::post('parents/{id}/reset-password', [\App\Http\Controllers\Admin\AdminParentController::class, 'resetPassword'])->name('parents.reset-password');
        
        // Legacy CRUD routes (maintaining backward compatibility)
        Route::get('students-crud', [App\Http\Controllers\Admin\AdminStudentController::class, 'list'])->name('admin.students.list');
        Route::get('students-crud/create', [App\Http\Controllers\Admin\AdminStudentController::class, 'create'])->name('students-legacy.create');
        Route::post('students-crud', [App\Http\Controllers\Admin\AdminStudentController::class, 'store'])->name('students-legacy.store');
        Route::get('students-crud/{student}', [App\Http\Controllers\Admin\AdminStudentController::class, 'show'])->name('students-legacy.show');
        Route::get('students-crud/{student}/edit', [App\Http\Controllers\Admin\AdminStudentController::class, 'edit'])->name('students-legacy.edit');
        Route::put('students-crud/{student}', [App\Http\Controllers\Admin\AdminStudentController::class, 'update'])->name('students-legacy.update');
        Route::delete('students-crud/{student}', [App\Http\Controllers\Admin\AdminStudentController::class, 'destroy'])->name('students-legacy.destroy');

        // Financial Year Closing -- gated by permission inside the controller
        // itself (view-year-closing/manage-year-closing), not a hardcoded
        // role. Must be registered before the 'fees' resource below (which
        // defines a GET fees/{fee} wildcard for 'show') so "year-closing"
        // isn't swallowed as a fee ID.
        Route::get('fees/year-closing', [App\Http\Controllers\Admin\FinancialYearClosingController::class, 'index'])->name('fees.year-closing.index');
        Route::post('fees/year-closing/stage', [App\Http\Controllers\Admin\FinancialYearClosingController::class, 'stage'])->name('fees.year-closing.stage');
        Route::post('fees/year-closing/confirm', [App\Http\Controllers\Admin\FinancialYearClosingController::class, 'confirm'])->name('fees.year-closing.confirm');
        Route::post('fees/year-closing/rollback', [App\Http\Controllers\Admin\FinancialYearClosingController::class, 'rollback'])->name('fees.year-closing.rollback');

        // Fee Structures CRUD -- gated by permission inside the controller
        // itself (view-fee-structures/create-fee-structures/
        // edit-fee-structures/delete-fee-structures), not a hardcoded role.
        Route::post('fee-structures/copy', [App\Http\Controllers\Admin\FeeStructureController::class, 'copyStructure'])->name('fee-structures.copy');
        Route::post('fee-structures/display-settings', [App\Http\Controllers\Admin\FeeStructureController::class, 'updateDisplaySettings'])->name('fee-structures.display-settings.update');
        Route::resource('fee-structures', App\Http\Controllers\Admin\FeeStructureController::class);
        Route::put('fee-structures/{id}/activate', [App\Http\Controllers\Admin\FeeStructureController::class, 'activate'])->name('fee-structures.activate');
        Route::put('fee-structures/{id}/deactivate', [App\Http\Controllers\Admin\FeeStructureController::class, 'deactivate'])->name('fee-structures.deactivate');

        // Fee Demand Register + Daily Collection Register -- part of
        // FeeCollectionController, gated by view-fees below.
        Route::get('fees/demand-register', [App\Http\Controllers\Admin\FeeCollectionController::class, 'demandRegister'])->name('fees.demand-register');
        Route::get('fees/demand-register/export', [App\Http\Controllers\Admin\FeeCollectionController::class, 'exportDemandRegister'])->name('fees.demand-register.export');
        Route::get('fees/collection-register', [App\Http\Controllers\Admin\FeeCollectionController::class, 'collectionRegister'])->name('fees.collection-register');
        Route::get('fees/collection-register/export', [App\Http\Controllers\Admin\FeeCollectionController::class, 'exportCollectionRegister'])->name('fees.collection-register.export');

        // Cashier Closing Shift Routes -- gated by permission inside the
        // controller (view-cashier-closing/manage-cashier-closing).
        Route::resource('fees/cashier-closings', App\Http\Controllers\Admin\CashierClosingController::class)->only(['index', 'create', 'store', 'show'])->names('fees.cashier-closings');

        // Defaulter Management Workflow -- gated by permission inside the
        // controller (view-defaulters/manage-defaulters).
        Route::get('fees/defaulters/dashboard', [App\Http\Controllers\Admin\DefaulterController::class, 'dashboard'])->name('fees.defaulters.dashboard');
        Route::get('fees/defaulters/export', [App\Http\Controllers\Admin\DefaulterController::class, 'export'])->name('fees.defaulters.export');
        Route::get('fees/defaulters', [App\Http\Controllers\Admin\DefaulterController::class, 'index'])->name('fees.defaulters.index');
        Route::post('fees/defaulters/bulk-action', [App\Http\Controllers\Admin\DefaulterController::class, 'bulkAction'])->name('fees.defaulters.bulk-action');
        Route::post('fees/defaulters/{id}/action', [App\Http\Controllers\Admin\DefaulterController::class, 'takeAction'])->name('fees.defaulters.action');
        Route::post('fees/defaulters/{id}/override', [App\Http\Controllers\Admin\DefaulterController::class, 'override'])->name('fees.defaulters.override');
        Route::get('fees/defaulters/{studentId}/history', [App\Http\Controllers\Admin\DefaulterController::class, 'history'])->name('fees.defaulters.history');
        Route::post('fees/defaulters/{id}/exam-override', [App\Http\Controllers\Admin\DefaulterController::class, 'grantExamOverride'])->name('fees.defaulters.exam-override.grant');
        Route::delete('fees/defaulters/{id}/exam-override', [App\Http\Controllers\Admin\DefaulterController::class, 'revokeExamOverride'])->name('fees.defaulters.exam-override.revoke');

        // Enterprise Finance Reporting Portal -- gated by permission inside
        // the controller (view-finance-reports).
        Route::get('fees/reports', [App\Http\Controllers\Admin\FinanceReportController::class, 'index'])->name('fees.reports.index');
        Route::get('fees/reports/export', [App\Http\Controllers\Admin\FinanceReportController::class, 'export'])->name('fees.reports.export');

        // Professional Accountant Dashboard -- gated by permission inside
        // the controller (view-fees).
        Route::get('fees/dashboard', [App\Http\Controllers\Admin\AccountantDashboardController::class, 'index'])->name('fees.dashboard');

        // Fee Collection -- gated by permission inside the controller itself
        // (view-fees/can-manage-fees), not a hardcoded role.
        Route::resource('fees', App\Http\Controllers\Admin\FeeCollectionController::class)->only(['index', 'store', 'show']);
        Route::post('fees/search-students', [App\Http\Controllers\Admin\FeeCollectionController::class, 'searchStudents'])->name('fees.search.students');
        Route::get('fees/student/{id}/dashboard', [App\Http\Controllers\Admin\FeeCollectionController::class, 'getStudentFeeDashboard'])->name('fees.student-dashboard');
        Route::get('fees/collect/{studentId}', [App\Http\Controllers\Admin\FeeCollectionController::class, 'createCollectionForm'])->name('fees.collect.form');
        Route::post('fees/process-collection', [App\Http\Controllers\Admin\FeeCollectionController::class, 'processCollection'])->name('fees.process.collection');
        Route::get('fees/receipt/{id}', [App\Http\Controllers\Admin\FeeCollectionController::class, 'getReceipt'])->name('fees.receipt');
        Route::post('fees/receipt/{id}/reverse', [App\Http\Controllers\Admin\FeeCollectionController::class, 'reverseCollection'])->name('fees.reverse');
        Route::get('fees/receipt/{id}/pdf', [App\Http\Controllers\Admin\FeeReceiptController::class, 'downloadPdf'])->name('fees.receipt.pdf');
        Route::get('fees/generate-upi-qr', [App\Http\Controllers\Admin\FeeCollectionController::class, 'generateUpiQr'])->name('fees.generate-upi-qr');
        Route::get('fees/reversal-requests', [App\Http\Controllers\Admin\FeeCollectionController::class, 'listReversalRequests'])->name('fees.reversal-requests.index');
        Route::post('fees/reversal-requests/{id}/approve', [App\Http\Controllers\Admin\FeeCollectionController::class, 'approveReversal'])->name('fees.reversal-requests.approve');
        Route::post('fees/reversal-requests/{id}/reject', [App\Http\Controllers\Admin\FeeCollectionController::class, 'rejectReversal'])->name('fees.reversal-requests.reject');

        // Security Deposit Refund Queue -- gated by permission inside the
        // controller (view-security-deposits/manage-security-deposits).
        Route::prefix('security-deposits')->name('security-deposits.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\SecurityDepositController::class, 'index'])->name('index');
            Route::post('/{id}/resolve', [App\Http\Controllers\Admin\SecurityDepositController::class, 'resolve'])->name('resolve');
        });

        // Generic Payment Info (QR + bank details for walk-ins/notice board)
        // -- gated by permission inside the controller (view-payment-info).
        Route::get('payment-info', [App\Http\Controllers\Admin\PaymentInfoController::class, 'show'])->name('payment-info.show');

        Route::middleware(['role:accountant'])->group(function () {
            // Discount Approval Routes
            Route::get('discount-approvals', [App\Http\Controllers\Admin\DiscountApprovalController::class, 'index'])->name('discount-approvals.index');
            Route::post('discount-approvals', [App\Http\Controllers\Admin\DiscountApprovalController::class, 'store'])->name('discount-approvals.store');
            Route::post('discount-approvals/{id}/verify', [App\Http\Controllers\Admin\DiscountApprovalController::class, 'verify'])->name('discount-approvals.verify');
            Route::post('discount-approvals/{id}/reject', [App\Http\Controllers\Admin\DiscountApprovalController::class, 'reject'])->name('discount-approvals.reject');
                
            // Student Financial Account Routes
            Route::get('financial-accounts', [App\Http\Controllers\Admin\StudentFinancialAccountController::class, 'index'])->name('financial-accounts.index');
            Route::get('financial-accounts/{id}', [App\Http\Controllers\Admin\StudentFinancialAccountController::class, 'show'])->name('financial-accounts.show');
            Route::post('financial-accounts/{id}/adjustment', [App\Http\Controllers\Admin\StudentFinancialAccountController::class, 'postAdjustment'])->name('financial-accounts.adjustment');
            Route::get('financial-accounts/{id}/export-pdf', [App\Http\Controllers\Admin\StudentFinancialAccountController::class, 'exportPdf'])->name('financial-accounts.export.pdf');
            Route::get('financial-accounts/{id}/export-excel', [App\Http\Controllers\Admin\StudentFinancialAccountController::class, 'exportExcel'])->name('financial-accounts.export.excel');

            // Parent Routes (moved to main route group)
                
            // Fee Automation Routes
            Route::get('fees/pending', [App\Http\Controllers\Admin\FeeAutomationController::class, 'pendingFees'])->name('fees.pending');
            Route::get('fees/defaulters-list', [App\Http\Controllers\Admin\FeeAutomationController::class, 'defaulters'])->name('fees.defaulters');
            Route::get('fee-dashboard', [App\Http\Controllers\Admin\FeeAutomationController::class, 'feeDashboard'])->name('fee-dashboard');
            Route::post('fees/send-whatsapp-reminder', [App\Http\Controllers\Admin\FeeAutomationController::class, 'sendWhatsappReminder'])->name('fees.send-whatsapp-reminder');
        });

        // Reconciliation Center -- gated by permission inside the controller
        // itself (view-reconciliation/manage-reconciliation), not a
        // hardcoded role.
        Route::prefix('reconciliation')->name('finance.reconciliation.')->group(function () {
            Route::get('/unresolved', [App\Http\Controllers\Admin\FinanceReconciliationController::class, 'unresolved'])->name('unresolved');
            Route::get('/overpayments', [App\Http\Controllers\Admin\FinanceReconciliationController::class, 'overpayments'])->name('overpayments');
            Route::get('/refunds', [App\Http\Controllers\Admin\FinanceReconciliationController::class, 'refunds'])->name('refunds');
            Route::get('/orphans', [App\Http\Controllers\Admin\FinanceReconciliationController::class, 'orphans'])->name('orphans');
            Route::get('/mismatches', [App\Http\Controllers\Admin\FinanceReconciliationController::class, 'mismatches'])->name('mismatches');
            Route::post('/bulk-assign', [App\Http\Controllers\Admin\FinanceReconciliationController::class, 'bulkAssign'])->name('bulk-assign');
            Route::post('/rebuild-ledger', [App\Http\Controllers\Admin\FinanceReconciliationController::class, 'rebuildLedger'])->name('rebuild-ledger');
            Route::post('/issue-refund', [App\Http\Controllers\Admin\FinanceReconciliationController::class, 'issueRefund'])->name('issue-refund');
        });

        // UPI Payment Claim Matching Queue -- gated by permission inside the
        // controller itself (view-upi-matching/manage-upi-matching), not a
        // hardcoded role.
        Route::prefix('payment-claims')->name('payment-claims.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\PaymentClaimMatchingController::class, 'queue'])->name('queue');
            Route::post('/run-matching', [App\Http\Controllers\Admin\PaymentClaimMatchingController::class, 'runMatching'])->name('run-matching');
            Route::post('/{claimId}/approve', [App\Http\Controllers\Admin\PaymentClaimMatchingController::class, 'approve'])->name('approve');
            Route::post('/{id}/reject', [App\Http\Controllers\Admin\PaymentClaimMatchingController::class, 'reject'])->name('reject');
        });

        // Family entity + sibling-discount link confirmation -- gated by
        // permission inside the controllers themselves (view-families/
        // manage-families), not a hardcoded role.
        Route::resource('families', App\Http\Controllers\Admin\FamilyController::class)->only(['index', 'show']);
        Route::prefix('family-link-suggestions')->name('family-link-suggestions.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\FamilyLinkSuggestionController::class, 'index'])->name('index');
            Route::post('/{id}/confirm', [App\Http\Controllers\Admin\FamilyLinkSuggestionController::class, 'confirm'])->name('confirm');
            Route::post('/{id}/dismiss', [App\Http\Controllers\Admin\FamilyLinkSuggestionController::class, 'dismiss'])->name('dismiss');
        });

        // Fee Head Master -- gated by permission inside the controllers
        // themselves (view-fee-types/manage-fee-types), not a hardcoded role.
        Route::get('fee-types/master', [App\Http\Controllers\Admin\FeeStructureController::class, 'feeTypeMaster'])->name('fee-types.master');
        Route::post('fee-types/master', [App\Http\Controllers\Admin\FeeStructureController::class, 'updateFeeTypeMaster'])->name('fee-types.update-master');
        Route::resource('fee-types', App\Http\Controllers\Admin\FeeTypeController::class)->except(['show']);
        Route::put('fee-types/{feeType}/activate', [App\Http\Controllers\Admin\FeeTypeController::class, 'activate'])->name('fee-types.activate');
        Route::put('fee-types/{feeType}/deactivate', [App\Http\Controllers\Admin\FeeTypeController::class, 'deactivate'])->name('fee-types.deactivate');

        // Discount rule CRUD -- gated by permission inside the controller
        // itself (view-discount-rules/manage-discount-rules), not a
        // hardcoded role, so the admin can delegate this financial-policy
        // duty via Manage Permissions if they choose to.
        Route::resource('discount-rules', App\Http\Controllers\Admin\DiscountRuleController::class)->except(['show']);

        // Advance-rebate rule CRUD + manual override -- gated by permission
        // inside the controller itself (view-advance-rebate-rules/
        // manage-advance-rebate-rules), same financial-policy tier as
        // discount rules.
        Route::resource('advance-rebate-rules', App\Http\Controllers\Admin\AdvanceRebateRuleController::class)->except(['show']);
        Route::post('students/{student}/advance-rebate-override', [App\Http\Controllers\Admin\AdvanceRebateRuleController::class, 'manualOverride'])->name('advance-rebate-rules.manual-override');

        // Payment Settings Routes
        Route::get('settings/payment', [App\Http\Controllers\Admin\PaymentSettingsController::class, 'showPaymentSettings'])->name('settings.payment');
        Route::post('settings/payment', [App\Http\Controllers\Admin\PaymentSettingsController::class, 'updatePaymentSettings'])->name('settings.payment.update');
        
        // Homework & Notice Routes
        Route::resource('homework-notices', App\Http\Controllers\Admin\HomeworkNoticeController::class);
        Route::get('homework-notices/upcoming', [App\Http\Controllers\Admin\HomeworkNoticeController::class, 'upcoming'])->name('homework-notices.upcoming');
        
        // ID Card Routes
        Route::resource('id-cards', App\Http\Controllers\Admin\IdCardController::class);
        Route::get('id-cards/{id}/print', [App\Http\Controllers\Admin\IdCardController::class, 'print'])->name('id-cards.print');
        Route::post('id-cards/generate/{studentId}', [App\Http\Controllers\Admin\IdCardController::class, 'generateForStudent'])->name('id-cards.generate-for-student');
        
        // Legacy teacher bulk upload — retired in favor of the Universal Import Engine's
        // Teacher Import (mapping, dry-run preview, conflict resolution, rollback, history).
        // Routes kept as redirects so old bookmarks/links don't 404.
        Route::get('teachers/bulk-upload', fn () => redirect()->route('imports.wizard', ['module' => 'teachers']))->name('teachers.bulk-upload');
        Route::get('teachers/bulk-upload/sample', fn () => redirect()->route('imports.download-template', ['module' => 'teachers']))->name('teachers.bulk-upload.sample');

        Route::resource('teachers', TeacherController::class);
        Route::post('teachers/{teacher}/toggle-exam-head', [TeacherController::class, 'toggleExamHead'])->name('teachers.toggle-exam-head');
        Route::post('attendance/preflight', [AttendanceController::class, 'preflight'])->name('attendance.preflight');
        Route::post('attendance/preflight-view', [AttendanceController::class, 'preflightView'])->name('attendance.preflight-view');
        Route::resource('attendance', AttendanceController::class);
        Route::get('attendance/report/{id}', [AttendanceController::class, 'report'])->name('attendance.report');
        // Exam Paper Routes (Admin facing)
        // Additional Exam Paper Routes (must be defined BEFORE resource route)
        Route::post('exam-papers/{examPaper}/submit', [App\Http\Controllers\Admin\ExamPaperController::class, 'submit'])->name('exam-papers.submit');
        Route::post('exam-papers/{examPaper}/approve', [App\Http\Controllers\Admin\ExamPaperController::class, 'approve'])->name('exam-papers.approve');
        Route::post('exam-papers/{examPaper}/exam-approve', [App\Http\Controllers\Admin\ExamPaperController::class, 'examApprove'])->name('exam-papers.exam-approve');
        Route::post('exam-papers/{examPaper}/publish', [App\Http\Controllers\Admin\ExamPaperController::class, 'publish'])->name('exam-papers.publish');
        Route::post('exam-papers/{examPaper}/lock', [App\Http\Controllers\Admin\ExamPaperController::class, 'lock'])->name('exam-papers.lock');
        Route::get('exam-papers/{examPaper}/print', [App\Http\Controllers\Admin\ExamPaperController::class, 'print'])->name('exam-papers.print');
        Route::post('exam-papers/{examPaper}/clone', [App\Http\Controllers\Admin\ExamPaperController::class, 'clone'])->name('exam-papers.clone');
        
        // Admin exam paper additional routes
        Route::get('exam-papers', [App\Http\Controllers\Admin\ExamPaperController::class, 'index'])->name('exam-papers.index');
        Route::get('/exam-papers/{id}/approve', [App\Http\Controllers\Admin\ExamPaperController::class, 'showApproveConfirmation'])->name('exam-papers.approve.confirm');
        Route::delete('/exam-papers/{id}', [App\Http\Controllers\Admin\ExamPaperController::class, 'destroy'])->name('exam-papers.destroy');
        
        // Additional exam paper routes
        Route::get('exam-papers/available-for-class', [App\Http\Controllers\Admin\ExamPaperController::class, 'availableForClass'])->name('exam-papers.available-for-class');
        Route::get('exam-papers/search', [App\Http\Controllers\Admin\ExamPaperController::class, 'search'])->name('exam-papers.search');
        Route::get('exam-papers/upcoming', [App\Http\Controllers\Admin\ExamPaperController::class, 'upcoming'])->name('exam-papers.upcoming');
        Route::get('exam-papers/{examPaper}/download', [App\Http\Controllers\Admin\ExamPaperController::class, 'download'])->name('exam-papers.download');
        Route::get('exam-papers/{id}', [App\Http\Controllers\Admin\ExamPaperController::class, 'show'])->name('exam-papers.show');
        Route::get('exam-papers/{id}/edit', [App\Http\Controllers\Admin\ExamPaperController::class, 'edit'])->name('exam-papers.edit');
        Route::put('exam-papers/{id}', [App\Http\Controllers\Admin\ExamPaperController::class, 'update'])->name('exam-papers.update');
        
        // Admin Marks Routes - MUST be before resource routes
        Route::get('exams/marks', [App\Http\Controllers\Admin\AdminMarksController::class, 'index'])->name('exams.marks.index');
        
        // Result Monitoring Routes - MUST be before resource routes
        Route::get('exams/result-monitor', [App\Http\Controllers\Admin\ResultMonitorController::class, 'index'])->name('result.monitor');
        Route::post('exams/result-status', [App\Http\Controllers\Admin\ResultMonitorController::class, 'getResultStatus'])->name('result.status');
        Route::get('exams/class-results', [App\Http\Controllers\Admin\ResultMonitorController::class, 'classResultsView'])->name('class.results');
        Route::post('exams/class-results-data', [App\Http\Controllers\Admin\ResultMonitorController::class, 'classResults'])->name('class.results.data');
            
        // Admin Result Monitor Routes (New)
        // REMOVED - Fake routes returning plain text
        // Route::get('exams/result-monitor', [App\Http\Controllers\Admin\AdminExamController::class, 'resultMonitor'])->name('result.monitor');
        // Route::get('exams/class-results', [App\Http\Controllers\Admin\AdminExamController::class, 'classResults'])->name('class.results');
        
        // Exam Seating, Invigilators, and Relieving Arrangements
        Route::get('exams/arrangements', [App\Http\Controllers\Admin\ExamArrangementController::class, 'index'])->name('exams.arrangements.index');
        Route::get('exams/{exam}/arrangements/seating', [App\Http\Controllers\Admin\ExamArrangementController::class, 'seatingIndex'])->name('exams.arrangements.seating');
        Route::post('exams/{exam}/arrangements/seating/generate', [App\Http\Controllers\Admin\ExamArrangementController::class, 'generateSeating'])->name('exams.arrangements.seating.generate');
        Route::post('exams/{exam}/arrangements/seating/save', [App\Http\Controllers\Admin\ExamArrangementController::class, 'saveSeating'])->name('exams.arrangements.seating.save');
        Route::get('exams/{exam}/arrangements/invigilators', [App\Http\Controllers\Admin\ExamArrangementController::class, 'invigilatorIndex'])->name('exams.arrangements.invigilators');
        Route::post('exams/{exam}/arrangements/invigilators/save', [App\Http\Controllers\Admin\ExamArrangementController::class, 'saveInvigilators'])->name('exams.arrangements.invigilators.save');
        Route::get('exams/{exam}/arrangements/relieving', [App\Http\Controllers\Admin\ExamArrangementController::class, 'relievingIndex'])->name('exams.arrangements.relieving');
        Route::post('exams/{exam}/arrangements/relieving/save', [App\Http\Controllers\Admin\ExamArrangementController::class, 'saveRelieving'])->name('exams.arrangements.relieving.save');
        
        // Exam Management Routes - MUST be after specific routes
        Route::resource('exams', App\Http\Controllers\Admin\ExamController::class);
        
        // Result Management Routes
        Route::resource('results', App\Http\Controllers\Admin\ResultController::class);
        Route::get('results/{result}/download', [App\Http\Controllers\Admin\ResultController::class, 'download'])->name('results.download');
        Route::get('results/student/{studentId}', [App\Http\Controllers\Admin\ResultController::class, 'getStudentResults'])->name('results.student-results');
        
        // Enhanced Result Management Routes
        Route::get('enhanced-results', [App\Http\Controllers\Admin\EnhancedResultController::class, 'index'])->name('enhanced-results.index');
        Route::get('enhanced-results/student/{studentId}/{examId}', [App\Http\Controllers\Admin\EnhancedResultController::class, 'showStudentResult'])->name('enhanced-results.student');
        Route::get('enhanced-results/report', [App\Http\Controllers\Admin\EnhancedResultController::class, 'generateOptimizedReport'])->name('enhanced-results.report');
        Route::get('enhanced-results/analysis/{examId}', [App\Http\Controllers\Admin\EnhancedResultController::class, 'generatePerformanceAnalysis'])->name('enhanced-results.analysis');
        Route::post('enhanced-results/bulk', [App\Http\Controllers\Admin\EnhancedResultController::class, 'bulkOperations'])->name('enhanced-results.bulk');
        Route::get('enhanced-results/subject-comparison', [App\Http\Controllers\Admin\EnhancedResultController::class, 'subjectComparison'])->name('enhanced-results.subject-comparison');
        Route::get('enhanced-results/trend-analysis', [App\Http\Controllers\Admin\EnhancedResultController::class, 'trendAnalysis'])->name('enhanced-results.trend-analysis');
        Route::get('enhanced-results/accessible/{studentId}/{examId}', [App\Http\Controllers\Admin\EnhancedResultController::class, 'accessibleResultView'])->name('enhanced-results.accessible');
        
        // Uploaded Marks Management Routes
        Route::get('uploaded-marks', [App\Http\Controllers\Admin\AdminUploadedMarksController::class, 'index'])->name('uploaded-marks.index');
        Route::get('uploaded-marks/export', [App\Http\Controllers\Admin\AdminUploadedMarksController::class, 'exportToExcel'])->name('uploaded-marks.export');
        Route::post('uploaded-marks/delete/{id}', [App\Http\Controllers\Admin\AdminUploadedMarksController::class, 'deleteResult'])->name('uploaded-marks.delete');
        Route::post('uploaded-marks/unlock/{id}', [App\Http\Controllers\Admin\AdminUploadedMarksController::class, 'unlockResult'])->name('uploaded-marks.unlock');
        
        // Lesson Plan Management Routes
        Route::resource('lesson-plans', App\Http\Controllers\Admin\LessonPlanController::class);
        Route::get('lesson-plans/{lessonPlan}/show', [App\Http\Controllers\Admin\LessonPlanController::class, 'show'])->name('admin.lesson-plans.show');
        
        // Professional Lesson Plan Admin Routes
        Route::resource('professional-lesson-plans', App\Http\Controllers\Admin\ProfessionalLessonPlanController::class)->names([
            'index' => 'admin.professional-lesson-plans.index',
            'show' => 'admin.professional-lesson-plans.show',
            'destroy' => 'admin.professional-lesson-plans.destroy',
        ])->except(['create', 'store', 'edit', 'update']);
        
        // Homework Management Routes -- Admin\HomeworkController deleted
        // (B10): only implemented index/show out of the full resource
        // (create/store/edit/update/destroy all fatal'd), its show() had
        // the same route-model-binding name mismatch bug found on
        // ClassTeacherAssignmentController ($homeworkNotice vs {homework}),
        // and it was unreferenced by any view outside its own two pages.
        // HomeworkNoticeController (admin.homework-notices.*) is the real,
        // fully-implemented survivor -- see routes below.

        // Professional Homework Admin Routes
        Route::resource('professional-homework', App\Http\Controllers\Admin\ProfessionalHomeworkController::class)->names([
            'index' => 'admin.professional-homework.index',
            'show' => 'admin.professional-homework.show',
            'destroy' => 'admin.professional-homework.destroy',
        ])->except(['create', 'store', 'edit', 'update']);
        
        // Professional Result Features
        Route::get('results/report-card/{studentId}/{examId}', [App\Http\Controllers\Admin\ResultController::class, 'generateReportCard'])->name('results.report-card');
        Route::get('results/report-card/{studentId}/{examId}/print', [App\Http\Controllers\Admin\ResultController::class, 'generateReportCard'])->name('results.report-card.print');
        Route::get('results/professional-format/{studentId}/{examId}', [App\Http\Controllers\Admin\ResultController::class, 'generateProfessionalFormat'])->name('results.professional-format');
        Route::get('results/cbse-professional-format/{studentId}/{examId}', [App\Http\Controllers\Admin\ResultController::class, 'generateCBSEProfessionalFormat'])->name('results.cbse-professional-format');
        Route::post('results/rankings/{examId}', [App\Http\Controllers\Admin\ResultController::class, 'generateRankings'])->name('results.generate-rankings');
            
        // Exam Head Dashboard Route
        Route::get('exam-head/dashboard', [App\Http\Controllers\ExamHead\ExamHeadDashboardController::class, 'index'])->middleware('exam.head')->name('exam.head.dashboard');
        Route::get('results/toppers/{examId}', [App\Http\Controllers\Admin\ResultController::class, 'getClassToppers'])->name('results.toppers');
        Route::post('results/lock/{examId}', [App\Http\Controllers\Admin\ResultController::class, 'lockResults'])->name('results.lock');
        Route::post('results/unlock/{examId}', [App\Http\Controllers\Admin\ResultController::class, 'unlockResults'])->name('results.unlock');
        Route::get('results/statistics/{examId}', [App\Http\Controllers\Admin\ResultController::class, 'getStatistics'])->name('results.statistics');
        
        // Bulk Operations
        Route::get('results/bulk-import/{examId}', [App\Http\Controllers\Admin\ResultController::class, 'showBulkImport'])->name('results.bulk-import');
        Route::post('results/bulk-import/{examId}', [App\Http\Controllers\Admin\ResultController::class, 'processBulkImport'])->name('results.process-bulk-import');
        Route::get('results/download-template', [App\Http\Controllers\Admin\ResultController::class, 'downloadSampleTemplate'])->name('results.download-template');
        Route::get('results/export/{examId}', [App\Http\Controllers\Admin\ResultController::class, 'exportResults'])->name('results.export');
        
        // Admit Card Management Routes
        Route::resource('admit-cards', App\Http\Controllers\Admin\AdmitCardController::class);
        Route::get('admit-cards/generate/{examId?}', [App\Http\Controllers\Admin\AdmitCardController::class, 'generate'])->name('admit-cards.generate');
        Route::get('admit-cards/preview/{studentId}/{examId?}', [App\Http\Controllers\Admin\AdmitCardController::class, 'preview'])->name('admit-cards.preview');
        Route::post('admit-cards/regenerate/{studentId}/{examId?}', [App\Http\Controllers\Admin\AdmitCardController::class, 'regenerate'])->name('admit-cards.regenerate');
        
        // Admit Card Format Management Routes
        Route::resource('admit-card-formats', App\Http\Controllers\Admin\AdmitCardFormatController::class);
        Route::get('admit-card-formats/{format}/preview', [App\Http\Controllers\Admin\AdmitCardFormatController::class, 'preview'])->name('admit-card-formats.preview');
        Route::post('admit-card-formats/{format}/set-default', [App\Http\Controllers\Admin\AdmitCardFormatController::class, 'setDefault'])->name('admit-card-formats.set-default');
        
        // Exam Paper Template Management Routes
        Route::resource('exam-paper-templates', App\Http\Controllers\Admin\ExamPaperTemplateController::class);
        Route::get('exam-paper-templates/{template}/preview', [App\Http\Controllers\Admin\ExamPaperTemplateController::class, 'preview'])->name('exam-paper-templates.preview');
        Route::post('exam-paper-templates/{template}/set-default', [App\Http\Controllers\Admin\ExamPaperTemplateController::class, 'setDefault'])->name('exam-paper-templates.set-default');
        Route::post('exam-paper-templates/{template}/toggle-status', [App\Http\Controllers\Admin\ExamPaperTemplateController::class, 'toggleStatus'])->name('exam-paper-templates.toggle-status');
        
        // Section Management Routes
        Route::resource('sections', App\Http\Controllers\Admin\SectionController::class);

        // Academic Calendar / Events / Holidays Routes
        Route::resource('academic-events', App\Http\Controllers\Admin\AcademicEventController::class);

        // Class Management Routes -- ClassController retired in favor of
        // SchoolClassController (see A3 of the Academic module rebuild);
        // canonical registration is the 'school-classes' resource below.

        // Grading System Management Routes
        Route::resource('grading-systems', App\Http\Controllers\Admin\GradingSystemController::class);
        
        // Result Format Management Routes
        Route::resource('result-formats', App\Http\Controllers\Admin\ResultFormatController::class);
        
        // Examination Pattern Management Routes
        Route::resource('examination-patterns', App\Http\Controllers\Admin\ExaminationPatternController::class);
        
        // Student Status Management Routes
        Route::resource('student-statuses', App\Http\Controllers\Admin\StudentStatusController::class);
        
        // Permission Management Routes
        Route::resource('permissions', App\Http\Controllers\Admin\PermissionController::class);
        
        // Document Format Management Routes
        Route::resource('document-formats', App\Http\Controllers\Admin\DocumentFormatController::class);
        Route::post('document-formats/{documentFormat}/set-default', [App\Http\Controllers\Admin\DocumentFormatController::class, 'setDefault'])->name('document-formats.set-default');
        
        // Subject Management Routes
        Route::resource('subjects', App\Http\Controllers\Admin\SubjectController::class);
        
        // Academic Session Management Routes
        Route::resource('academic-sessions', App\Http\Controllers\Admin\AcademicSessionController::class);
        
        // Bell Schedule Management Routes
        Route::resource('bell-schedules', App\Http\Controllers\BellScheduleController::class);
        Route::get('bell-schedules/live-monitor', [App\Http\Controllers\BellScheduleController::class, 'liveMonitor'])->name('bell-schedules.live-monitor');
        
        // Special Day Override Management Routes
        Route::resource('special-day-overrides', App\Http\Controllers\SpecialDayOverrideController::class);
        
        // Teacher Substitution Management Routes
        // Literal-path routes registered BEFORE the resource() call so
        // they don't get swallowed by its GET {teacher_substitution} show
        // route (same class of ordering bug fixed for bell-timing routes
        // during remediation).
        Route::get('teacher-substitutions/today', [App\Http\Controllers\Admin\TeacherSubstitutionController::class, 'today'])->name('teacher-substitutions.today');
        Route::get('teacher-substitutions/absence-overview', [App\Http\Controllers\Admin\TeacherSubstitutionController::class, 'absenceOverview'])->name('teacher-substitutions.absence-overview');
        Route::get('teacher-substitutions/absent-today', [App\Http\Controllers\Admin\TeacherSubstitutionController::class, 'absentToday'])->name('teacher-substitutions.absent-today');
        Route::post('teacher-substitutions/assign-from-slot', [App\Http\Controllers\Admin\TeacherSubstitutionController::class, 'assignFromSlot'])->name('teacher-substitutions.assign-from-slot');
        Route::get('teacher-substitutions/arrangement-sheet', [App\Http\Controllers\Admin\TeacherSubstitutionController::class, 'arrangementSheetPdf'])->name('teacher-substitutions.arrangement-sheet');
        // Was routed to nonexistent rules()/updateRules() controller
        // methods (a rename-drift bug -- the real, working method is
        // substitutionRules(), unreachable by any route until now). The
        // rules view has no submitting form yet (its checkboxes have no
        // real backend to save to), so only the GET/display route is
        // restored here -- inventing an update endpoint for a form that
        // doesn't submit anything would be fabricating a feature.
        Route::get('teacher-substitutions/rules', [App\Http\Controllers\Admin\TeacherSubstitutionController::class, 'substitutionRules'])->name('teacher-substitutions.rules');
        Route::resource('teacher-substitutions', App\Http\Controllers\Admin\TeacherSubstitutionController::class);
        
                // Teacher Attendance Management Routes - Specific routes must come before resource route to avoid conflicts
        Route::get('teacher-attendance/reports', [App\Http\Controllers\Admin\TeacherAttendanceController::class, 'reports'])->name('teacher-attendance.reports');
        Route::post('teacher-attendance/mark-all-present', [App\Http\Controllers\Admin\TeacherAttendanceController::class, 'markAllPresent'])->name('teacher-attendance.mark-all-present');
        Route::get('teacher-attendance/export', [App\Http\Controllers\Admin\TeacherAttendanceController::class, 'export'])->name('teacher-attendance.export');
        Route::post('teacher-attendance/update-attendance/{teacherId}', [App\Http\Controllers\Admin\TeacherAttendanceController::class, 'updateAttendance'])->name('teacher-attendance.update-attendance');
        Route::resource('teacher-attendance', App\Http\Controllers\Admin\TeacherAttendanceController::class);
        // Class Teacher Assignment Management Routes
        Route::resource('class-teacher-assignments', App\Http\Controllers\ClassTeacherAssignmentController::class);
        // getTeacherClasses/getStudentClassTeacher routes removed (see A4):
        // neither method ever existed on the controller.
        
        // Teacher Subject Assignment Management Routes
        Route::resource('teacher-subject-assignments', App\Http\Controllers\Admin\TeacherSubjectAssignmentController::class);
        
        // Teacher Class Assignment Management Routes
        Route::resource('teacher-class-assignments', App\Http\Controllers\Admin\TeacherClassAssignmentController::class);
        
        // Teacher Class Subject Assignment Management Routes removed (see
        // A4): TeacherClassSubjectAssignmentController was a raw-DB::table()
        // duplicate of Admin\TeacherSubjectAssignmentController, which
        // manages the same table via Eloquent with a max-2-classes rule.
        
        // Admin Configuration Routes
        Route::get('configurations', [App\Http\Controllers\Admin\AdminConfigurationController::class, 'index'])->name('configurations.index');
        Route::post('configurations/update', [App\Http\Controllers\Admin\AdminConfigurationController::class, 'update'])->name('configurations.update');
        Route::post('configurations/reset-defaults', [App\Http\Controllers\Admin\AdminConfigurationController::class, 'resetToDefaults'])->name('configurations.reset-defaults');
        Route::post('configurations/{id}/toggle', [App\Http\Controllers\Admin\AdminConfigurationController::class, 'toggle'])->name('configurations.toggle');
        
        // Student Promotion Management Routes
        // IMPORTANT: Custom routes MUST come BEFORE resource route to avoid conflicts
        Route::get('student-promotions/class/{class}/students', [App\Http\Controllers\Admin\StudentPromotionController::class, 'getStudentsByClass'])->name('student-promotions.get-students');
        Route::get('student-promotions/destination-classes/{class}', [App\Http\Controllers\Admin\StudentPromotionController::class, 'getDestinationClasses'])->name('student-promotions.get-destination-classes');
        Route::get('student-promotions/student/{studentId}/history', [App\Http\Controllers\Admin\StudentPromotionController::class, 'studentHistory'])->name('student-promotions.history');
        Route::post('student-promotions/student/{studentId}/passed-out', [App\Http\Controllers\Admin\StudentPromotionController::class, 'markAsPassedOut'])->name('student-promotions.passed-out');
        // Phase 3O: limit student promotion resource routes to implemented methods.
        // Custom AJAX/history/passed-out routes remain defined separately.
        Route::resource('student-promotions', App\Http\Controllers\Admin\StudentPromotionController::class)->only(['index', 'create', 'store']);
        
        // Inventory Management Routes
        Route::get('inventory', [App\Http\Controllers\Admin\InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/dashboard', [App\Http\Controllers\Admin\InventoryController::class, 'index'])->name('inventory.dashboard');
        Route::get('inventory/asset-master', [App\Http\Controllers\Admin\InventoryController::class, 'assetMaster'])->name('inventory.asset-master');
        Route::get('inventory/furniture', [App\Http\Controllers\Admin\InventoryController::class, 'furnitureManagement'])->name('inventory.furniture');
        Route::get('inventory/lab-equipment', [App\Http\Controllers\Admin\InventoryController::class, 'labEquipmentManagement'])->name('inventory.lab-equipment');
        Route::get('inventory/electronics', [App\Http\Controllers\Admin\InventoryController::class, 'electronicsManagement'])->name('inventory.electronics');
        Route::get('inventory/reports', [App\Http\Controllers\Admin\InventoryController::class, 'reports'])->name('inventory.reports');
        Route::get('inventory/audit-logs', [App\Http\Controllers\Admin\InventoryController::class, 'auditLogs'])->name('inventory.audit-logs');
        Route::get('inventory/audit-logs/export', [App\Http\Controllers\Admin\InventoryController::class, 'exportAuditLogs'])->name('inventory.audit-logs.export');
        
        // Asset Management Routes
        Route::resource('assets', App\Http\Controllers\Admin\AssetController::class);
        Route::put('assets/{asset}/issue', [App\Http\Controllers\Admin\AssetController::class, 'issue'])->name('assets.issue');
        Route::put('assets/{asset}/return', [App\Http\Controllers\Admin\AssetController::class, 'return'])->name('assets.return');
        
        // Asset Category Management Routes
        Route::resource('inventory/categories', App\Http\Controllers\Admin\AssetCategoryController::class)
        ->names([
            'index' => 'inventory.categories.index',
            'create' => 'inventory.categories.create',
            'store' => 'inventory.categories.store',
            'show' => 'inventory.categories.show',
            'edit' => 'inventory.categories.edit',
            'update' => 'inventory.categories.update',
            'destroy' => 'inventory.categories.destroy'
        ]);

        
        // Teacher Biometric Routes
        Route::resource('teacher-biometrics', App\Http\Controllers\Admin\TeacherBiometricController::class);
        Route::post('teacher-biometrics/upload', [App\Http\Controllers\Admin\TeacherBiometricController::class, 'upload'])->name('teacher-biometrics.upload');
        Route::get('teacher-biometrics/settings', [App\Http\Controllers\Admin\TeacherBiometricController::class, 'settings'])->name('teacher-biometrics.settings');
        Route::post('teacher-biometrics/settings', [App\Http\Controllers\Admin\TeacherBiometricController::class, 'updateSettings'])->name('teacher-biometrics.settings.update');
        Route::get('teacher-biometrics/reports', [App\Http\Controllers\Admin\TeacherBiometricController::class, 'reports'])->name('teacher-biometrics.reports');
        Route::get('teacher-biometrics/export', [App\Http\Controllers\Admin\TeacherBiometricController::class, 'export'])->name('teacher-biometrics.export');
        
        // Biometric Devices Routes
        Route::resource('biometric-devices', App\Http\Controllers\Admin\BiometricDeviceController::class);
        Route::post('biometric-devices/{device}/test-connection', [App\Http\Controllers\Admin\BiometricDeviceController::class, 'testConnection'])->name('biometric-devices.test-connection');
        Route::post('biometric-devices/{device}/sync', [App\Http\Controllers\Admin\BiometricDeviceController::class, 'sync'])->name('biometric-devices.sync');
        Route::get('biometric-devices/{device}/logs', [App\Http\Controllers\Admin\BiometricDeviceController::class, 'syncLogs'])->name('biometric-devices.logs');
        
        // Sync Monitor Routes
        Route::get('sync-monitor', [App\Http\Controllers\Admin\SyncMonitorController::class, 'index'])->name('sync-monitor.index');
        Route::get('sync-monitor/statistics', [App\Http\Controllers\Admin\SyncMonitorController::class, 'statistics'])->name('sync-monitor.statistics');
        Route::post('sync-monitor/sync-all', [App\Http\Controllers\Admin\SyncMonitorController::class, 'syncAll'])->name('sync-monitor.sync-all');
        
        // Analytics Routes
        Route::get('analytics', [App\Http\Controllers\Admin\AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('analytics/attendance-heatmap', [App\Http\Controllers\Admin\AnalyticsController::class, 'attendanceHeatmap'])->name('analytics.attendance-heatmap');
        Route::get('analytics/late-arrivals', [App\Http\Controllers\Admin\AnalyticsController::class, 'lateArrivals'])->name('analytics.late-arrivals');
        Route::get('analytics/early-departures', [App\Http\Controllers\Admin\AnalyticsController::class, 'earlyDepartures'])->name('analytics.early-departures');
        Route::get('analytics/discipline-score', [App\Http\Controllers\Admin\AnalyticsController::class, 'disciplineScore'])->name('analytics.discipline-score');
        
        // Reports Routes
        Route::get('reports', [App\Http\Controllers\Admin\ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/terminal-status-reconciliation', [App\Http\Controllers\Admin\TerminalStatusReconciliationReportController::class, 'index'])->name('reports.terminal-status-reconciliation');
        Route::post('reports/generate', [App\Http\Controllers\Admin\ReportController::class, 'generate'])->name('reports.generate');
        Route::get('reports/templates', [App\Http\Controllers\Admin\ReportController::class, 'templates'])->name('reports.templates');
        
        // Advanced Reporting Routes
        Route::get('advanced-reports/dashboard', [App\Http\Controllers\Admin\AdvancedReportController::class, 'dashboard'])->name('advanced-reports.dashboard');
        Route::resource('advanced-reports', App\Http\Controllers\Admin\AdvancedReportController::class);
        Route::get('advanced-reports/{advancedReport}/export/{format}', [App\Http\Controllers\Admin\AdvancedReportController::class, 'export'])->name('advanced-reports.export');
        
        // Language Management Routes
        Route::resource('languages', App\Http\Controllers\Admin\LanguageController::class);
        Route::get('languages/{language}/translations', [App\Http\Controllers\Admin\LanguageController::class, 'translations'])->name('languages.translations');
        Route::post('languages/{language}/translations', [App\Http\Controllers\Admin\LanguageController::class, 'storeTranslation'])->name('languages.translations.store');
        Route::put('languages/{language}/translations/{translation}', [App\Http\Controllers\Admin\LanguageController::class, 'updateTranslation'])->name('languages.translations.update');
        Route::delete('languages/{language}/translations/{translation}', [App\Http\Controllers\Admin\LanguageController::class, 'destroyTranslation'])->name('languages.translations.destroy');
        Route::get('languages/switch/{code}', [App\Http\Controllers\Admin\LanguageController::class, 'switchLanguage'])->name('languages.switch');
        Route::get('languages/{language}/export', [App\Http\Controllers\Admin\LanguageController::class, 'exportTranslations'])->name('languages.export');
        Route::post('languages/{language}/import', [App\Http\Controllers\Admin\LanguageController::class, 'importTranslations'])->name('languages.import');
        
        // Notification System Routes
        Route::resource('notification-settings', App\Http\Controllers\Admin\NotificationSettingController::class);
        Route::get('notification-settings/logs', [App\Http\Controllers\Admin\NotificationSettingController::class, 'logs'])->name('admin.notification-settings.logs');
        Route::post('notification-settings/{notificationSetting}/test', [App\Http\Controllers\Admin\NotificationSettingController::class, 'sendTest'])->name('admin.notification-settings.test');
        Route::post('notification-settings/send-bulk', [App\Http\Controllers\Admin\NotificationSettingController::class, 'sendBulk'])->name('admin.notification-settings.send-bulk');
        
        // Performance Analytics Routes
        Route::get('performance-analytics', [App\Http\Controllers\Admin\PerformanceAnalyticsController::class, 'index'])->name('admin.performance-analytics.index');
        Route::get('performance-analytics/dashboard', [App\Http\Controllers\Admin\PerformanceAnalyticsController::class, 'dashboard'])->name('admin.performance-analytics.dashboard');
        Route::get('performance-analytics/export/{format}', [App\Http\Controllers\Admin\PerformanceAnalyticsController::class, 'export'])->name('admin.performance-analytics.export');
        
        // Notification Routes
        Route::resource('notifications', App\Http\Controllers\Admin\NotificationTemplateController::class);
        Route::post('notifications/test', [App\Http\Controllers\Admin\NotificationTemplateController::class, 'test'])->name('notifications.test');
        
        // Performance Routes
        Route::get('performance', [App\Http\Controllers\Admin\PerformanceController::class, 'index'])->name('performance.index');
        Route::get('performance/scores', [App\Http\Controllers\Admin\PerformanceController::class, 'scores'])->name('performance.scores');
        Route::post('performance/calculate', [App\Http\Controllers\Admin\PerformanceController::class, 'calculate'])->name('performance.calculate');
        
        // Certificate Management Routes
        Route::resource('certificates', App\Http\Controllers\Admin\CertificateController::class);
        Route::put('certificates/{certificate}/approve', [App\Http\Controllers\Admin\CertificateController::class, 'approve'])->name('certificates.approve');
        Route::put('certificates/{certificate}/publish', [App\Http\Controllers\Admin\CertificateController::class, 'publish'])->name('certificates.publish');
        Route::put('certificates/{certificate}/lock', [App\Http\Controllers\Admin\CertificateController::class, 'lock'])->name('certificates.lock');
        Route::put('certificates/{certificate}/revoke', [App\Http\Controllers\Admin\CertificateController::class, 'revoke'])->name('certificates.revoke');
        Route::get('certificates/{certificate}/preview', [App\Http\Controllers\Admin\CertificateController::class, 'preview'])->name('certificates.preview');
        Route::get('certificates/{certificate}/download-pdf', [App\Http\Controllers\Admin\CertificateController::class, 'downloadPdf'])->name('certificates.download-pdf');

        Route::resource('certificate-templates', App\Http\Controllers\Admin\CertificateTemplateController::class);
        Route::post('certificate-templates/{certificateTemplate}/set-default', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'setDefault'])->name('certificate-templates.set-default');
        
        // Backup Management Routes
        Route::resource('backups', App\Http\Controllers\Admin\BackupController::class);
        Route::get('backups/{backup}/download', [App\Http\Controllers\Admin\BackupController::class, 'download'])->name('backups.download');
        Route::post('backups/schedule', [App\Http\Controllers\Admin\BackupController::class, 'schedule'])->name('backups.schedule');
        
        // Daily Teaching Work Routes
        Route::resource('daily-teaching-work', App\Http\Controllers\Admin\DailyTeachingWorkController::class);
        Route::get('daily-teaching-work/{dailyTeachingWork}/download-attachment/{index}', [App\Http\Controllers\Admin\DailyTeachingWorkController::class, 'downloadAttachment'])->name('daily-teaching-work.download-attachment');
        
        // Syllabi Routes
        Route::resource('syllabi', App\Http\Controllers\Admin\SyllabusController::class);
        Route::get('syllabi/progress-report', [App\Http\Controllers\Admin\SyllabusController::class, 'progressReport'])->name('syllabi.progress-report');
        
        // Language Settings Routes
        Route::resource('language-settings', App\Http\Controllers\Admin\LanguageSettingController::class);
        
        // Budget Management Routes
        Route::get('budget', [App\Http\Controllers\Admin\BudgetDashboardController::class, 'index'])->name('budget.index');
        Route::get('budget/analytics', [App\Http\Controllers\Admin\BudgetDashboardController::class, 'analytics'])->name('budget.analytics');
        Route::get('budget/reports', [App\Http\Controllers\Admin\BudgetDashboardController::class, 'reports'])->name('budget.reports');
        
        Route::resource('budgets', App\Http\Controllers\Admin\BudgetController::class);
        Route::put('budgets/{budget}/approve', [App\Http\Controllers\Admin\BudgetController::class, 'approve'])->name('budget.approve');
        Route::put('budgets/{budget}/lock', [App\Http\Controllers\Admin\BudgetController::class, 'lock'])->name('budget.lock');
        Route::put('budgets/{budget}/close', [App\Http\Controllers\Admin\BudgetController::class, 'close'])->name('budget.close');
        
        Route::resource('expenses', App\Http\Controllers\Admin\ExpenseController::class);
        Route::put('expenses/{expense}/approve', [App\Http\Controllers\Admin\ExpenseController::class, 'approve'])->name('expense.approve');
        Route::put('expenses/{expense}/reject', [App\Http\Controllers\Admin\ExpenseController::class, 'reject'])->name('expense.reject');
        
        Route::resource('budget-categories', App\Http\Controllers\Admin\BudgetCategoryController::class);
        Route::put('budget-categories/{budgetCategory}/toggle-active', [App\Http\Controllers\Admin\BudgetCategoryController::class, 'toggleActive'])->name('budget-category.toggle-active');
        
        // Audit Logs Routes
        Route::resource('audit-logs', App\Http\Controllers\AuditLogController::class);
        Route::get('audit-logs/student-history/{studentId}', [App\Http\Controllers\AuditLogController::class, 'studentHistory'])->name('audit-logs.student-history');
        
        // Field Permissions Routes
        Route::resource('field-permissions', App\Http\Controllers\FieldPermissionController::class);
        
        // Role & Permission Management Routes
        Route::resource('role-permissions', App\Http\Controllers\RolePermissionController::class);
        Route::get('role-permissions/user/{userId}/edit', [App\Http\Controllers\RolePermissionController::class, 'editUserRoles'])->name('role-permissions.edit-user');
        Route::put('role-permissions/user/{userId}', [App\Http\Controllers\RolePermissionController::class, 'updateUserRoles'])->name('role-permissions.update-user');
        
        // Class Teacher Control Routes
        Route::get('class-teacher-control/student-records', [App\Http\Controllers\Admin\ClassTeacherController::class, 'studentRecords'])->name('class-teacher-control.student-records');
        Route::get('class-teacher-control/student-records/{id}/edit', [App\Http\Controllers\Admin\ClassTeacherController::class, 'editStudent'])->name('class-teacher-control.edit-student');
        Route::put('class-teacher-control/student-records/{id}', [App\Http\Controllers\Admin\ClassTeacherController::class, 'updateStudent'])->name('class-teacher-control.update-student');
        
        // Quarantined in Phase 1H: duplicate fee-structures resource; canonical registration already exists above.
        // Route::resource('fee-structures', App\Http\Controllers\Admin\FeeStructureController::class);
        
        // School Classes Routes
        Route::resource('school-classes', App\Http\Controllers\Admin\SchoolClassController::class);
        Route::delete('school-classes/{schoolClass}/with-students', [App\Http\Controllers\Admin\SchoolClassController::class, 'destroyWithStudents'])->name('school-classes.destroy-with-students');
        Route::post('school-classes/{id}/restore', [App\Http\Controllers\Admin\SchoolClassController::class, 'restore'])->name('school-classes.restore');

        // Lesson Plan Management Routes (admin prefixed)
        Route::resource('lesson-plans', App\Http\Controllers\Admin\LessonPlanController::class);
        Route::get('lesson-plans/compliance', [App\Http\Controllers\Admin\LessonPlanController::class, 'compliance'])->name('lesson-plans.compliance');
        Route::get('lesson-plans/reports', [App\Http\Controllers\Admin\LessonPlanController::class, 'reports'])->name('lesson-plans.reports');
        Route::get('lesson-plans/dashboard-stats', [App\Http\Controllers\Admin\LessonPlanController::class, 'dashboardStats'])->name('lesson-plans.dashboard-stats');
        Route::get('lesson-plans/export-pdf', [App\Http\Controllers\Admin\LessonPlanController::class, 'exportPdf'])->name('lesson-plans.export-pdf');
        Route::get('lesson-plans/subject-progress', [App\Http\Controllers\Admin\LessonPlanController::class, 'subjectProgress'])->name('lesson-plans.subject-progress');
        
        // Library Management Routes (admin prefixed)
        Route::get('library/dashboard', [App\Http\Controllers\Admin\BookController::class, 'dashboard'])->name('library.dashboard');
        Route::get('library/reports', [App\Http\Controllers\Admin\BookIssueController::class, 'reports'])->name('library.reports');
        Route::get('library/export/{type?}', [App\Http\Controllers\Admin\BookIssueController::class, 'exportReport'])->name('library.export');
        Route::get('library/return/{id}', [App\Http\Controllers\Admin\BookIssueController::class, 'returnBook'])->name('library.return');
        
        // Library Settings Routes
        Route::resource('library-settings', App\Http\Controllers\Admin\LibrarySettingController::class);
        
        // Route::middleware(['role:accountant'])->group(function () {
        //     // Professional Fee Management Routes
        //     Route::get('fee-management/dashboard', [App\Http\Controllers\Admin\ProfessionalFeeManagementController::class, 'dashboard'])->name('fee-management.dashboard');
        //     Route::get('fee-management/fee-heads', [App\Http\Controllers\Admin\ProfessionalFeeManagementController::class, 'feeHeads'])->name('fee-management.fee-heads');
        //     // Quarantined in Phase 1H: professional fee write route is unlinked and schema/model contracts are unsafe.
        //     // Route::post('fee-management/fee-heads', [App\Http\Controllers\Admin\ProfessionalFeeManagementController::class, 'createFeeHead'])->name('fee-management.fee-heads.store');
        //     Route::get('fee-management/structures/create', [App\Http\Controllers\Admin\ProfessionalFeeManagementController::class, 'createFeeStructure'])->name('fee-management.structures.create');
        //     // Quarantined in Phase 1H: professional fee write route is unlinked and schema/model contracts are unsafe.
        //     // Route::post('fee-management/structures', [App\Http\Controllers\Admin\ProfessionalFeeManagementController::class, 'storeFeeStructure'])->name('fee-management.structures.store');
        //     // Quarantined in Phase 1H: professional fee write route is unlinked and schema/model contracts are unsafe.
        //     // Route::post('fee-management/assign-student', [App\Http\Controllers\Admin\ProfessionalFeeManagementController::class, 'assignToStudent'])->name('fee-management.assign-student');
        //     Route::get('fee-management/reports/collections', [App\Http\Controllers\Admin\ProfessionalFeeManagementController::class, 'collectionReport'])->name('fee-management.reports.collections');
        //     Route::get('fee-management/defaulters', [App\Http\Controllers\Admin\ProfessionalFeeManagementController::class, 'defaulters'])->name('fee-management.defaulters');
        //     Route::get('fee-management/receipt/{collectionId}', [App\Http\Controllers\Admin\ProfessionalFeeManagementController::class, 'generateReceipt'])->name('fee-management.receipt');
        //     Route::get('fee-management/forecasting', [App\Http\Controllers\Admin\ProfessionalFeeManagementController::class, 'forecasting'])->name('fee-management.forecasting');
        //     // Quarantined in Phase 1H: professional fee write route is unlinked and schema/model contracts are unsafe.
        //     // Route::post('fee-management/bulk-assign', [App\Http\Controllers\Admin\ProfessionalFeeManagementController::class, 'bulkAssign'])->name('fee-management.bulk-assign');
        //     Route::get('fee-management/preview/{feeStructureId}', [App\Http\Controllers\Admin\ProfessionalFeeManagementController::class, 'previewStructure'])->name('fee-management.preview');
        //     Route::get('fee-management/export', [App\Http\Controllers\Admin\ProfessionalFeeManagementController::class, 'exportData'])->name('fee-management.export');
        // });
        
        // Professional Dashboard Routes
        Route::get('dashboards/professional', [App\Http\Controllers\Admin\ProfessionalDashboardController::class, 'adminDashboard'])->name('dashboards.professional');
        Route::get('dashboards/real-time-updates', [App\Http\Controllers\Admin\ProfessionalDashboardController::class, 'getRealTimeUpdates'])->name('dashboards.real-time');
        Route::post('dashboards/refresh-section', [App\Http\Controllers\Admin\ProfessionalDashboardController::class, 'refreshSection'])->name('dashboards.refresh-section');
        Route::get('dashboards/widgets', [App\Http\Controllers\Admin\ProfessionalDashboardController::class, 'getDashboardWidgets'])->name('dashboards.widgets');
        Route::post('dashboards/preferences', [App\Http\Controllers\Admin\ProfessionalDashboardController::class, 'savePreferences'])->name('dashboards.preferences');
        Route::get('dashboards/export', [App\Http\Controllers\Admin\ProfessionalDashboardController::class, 'exportDashboardData'])->name('dashboards.export');
        Route::get('dashboards/notifications', [App\Http\Controllers\Admin\ProfessionalDashboardController::class, 'getNotifications'])->name('dashboards.notifications');
        Route::post('dashboards/notifications/{id}/read', [App\Http\Controllers\Admin\ProfessionalDashboardController::class, 'markNotificationRead'])->name('dashboards.notifications.read');
        
        // Academic Sessions Additional Routes
        Route::post('academic-sessions/{academic_session}/restore', [App\Http\Controllers\Admin\AcademicSessionController::class, 'restore'])->name('academic-sessions.restore');
        Route::post('academic-sessions/{academic_session}/set-current', [App\Http\Controllers\Admin\AcademicSessionController::class, 'setCurrent'])->name('academic-sessions.set-current');
        
        // Security Management Routes
        Route::get('security', [App\Http\Controllers\Admin\SecurityController::class, 'index'])->name('security.index');
        Route::post('security/audit', [App\Http\Controllers\Admin\SecurityController::class, 'performAudit'])->name('security.audit');
        Route::get('security/history', [App\Http\Controllers\Admin\SecurityController::class, 'auditHistory'])->name('security.history');
        Route::get('security/report', [App\Http\Controllers\Admin\SecurityController::class, 'generateReport'])->name('security.report');
        Route::get('security/configure', [App\Http\Controllers\Admin\SecurityController::class, 'configure'])->name('security.configure');
        Route::post('security/settings', [App\Http\Controllers\Admin\SecurityController::class, 'updateSettings'])->name('security.settings');
        Route::get('security/monitor', [App\Http\Controllers\Admin\SecurityController::class, 'monitorEvents'])->name('security.monitor');
        Route::get('security/recommendations', [App\Http\Controllers\Admin\SecurityController::class, 'getRecommendations'])->name('security.recommendations');
        Route::get('security/test', [App\Http\Controllers\Admin\SecurityController::class, 'testSecurity'])->name('security.test');
        
        // Sections Additional Routes
        Route::post('sections/{section}/restore', [App\Http\Controllers\Admin\SectionController::class, 'restore'])->name('sections.restore');
        
        // Subjects Additional Routes
        Route::post('subjects/{subject}/restore', [App\Http\Controllers\Admin\SubjectController::class, 'restore'])->name('subjects.restore');
        
        // Admit Cards Additional Routes
        Route::post('admit-cards/bulk-publish', [App\Http\Controllers\Admin\AdmitCardController::class, 'bulkPublish'])->name('admit-cards.bulk-publish');
        Route::post('admit-cards/bulk-lock', [App\Http\Controllers\Admin\AdmitCardController::class, 'bulkLock'])->name('admit-cards.bulk-lock');
        Route::post('admit-cards/bulk-revoke', [App\Http\Controllers\Admin\AdmitCardController::class, 'bulkRevoke'])->name('admit-cards.bulk-revoke');
        Route::post('admit-cards/block-defaulters', [App\Http\Controllers\Admin\AdmitCardController::class, 'blockDefaulters'])->name('admit-cards.block-defaulters');
        Route::post('admit-cards/unblock-cleared', [App\Http\Controllers\Admin\AdmitCardController::class, 'unblockCleared'])->name('admit-cards.unblock-cleared');
        Route::put('admit-cards/{admit_card}/publish', [App\Http\Controllers\Admin\AdmitCardController::class, 'publish'])->name('admit-cards.publish');
        Route::put('admit-cards/{admit_card}/lock', [App\Http\Controllers\Admin\AdmitCardController::class, 'lock'])->name('admit-cards.lock');
        Route::put('admit-cards/{admit_card}/revoke', [App\Http\Controllers\Admin\AdmitCardController::class, 'revoke'])->name('admit-cards.revoke');
        
        // Teacher Substitutions Additional Routes
        // Wildcard renamed teacher_substitution to match the controller's
        // $teacherSubstitution parameter (implicit binding only matches by
        // exact name or its snake_case form -- {substitution} silently
        // failed to bind, same class of bug fixed in ClassTeacherAssignmentController
        // during the remediation phase).
        Route::post('teacher-substitutions/{teacher_substitution}/assign', [App\Http\Controllers\Admin\TeacherSubstitutionController::class, 'assignSubstitute'])->name('teacher-substitutions.assign');
        Route::post('teacher-substitutions/{teacher_substitution}/approve', [App\Http\Controllers\Admin\TeacherSubstitutionController::class, 'approveSubstitute'])->name('teacher-substitutions.approve');
        Route::post('teacher-substitutions/{teacher_substitution}/cancel', [App\Http\Controllers\Admin\TeacherSubstitutionController::class, 'cancelSubstitute'])->name('teacher-substitutions.cancel');
        
        // User Roles Management
        Route::put('user-roles/{user}', [App\Http\Controllers\RolePermissionController::class, 'update'])->name('user-roles.update');
        
        // Library Management Routes (admin prefixed)
        Route::resource('books', App\Http\Controllers\Admin\BookController::class);
        Route::resource('book-issues', App\Http\Controllers\Admin\BookIssueController::class);
        
        // Budget Management Routes (non-admin prefixed) - already defined earlier
        
        // Inventory Management Routes
        Route::get('inventory/assets', [App\Http\Controllers\Admin\AssetController::class, 'index'])->name('inventory.assets.index');
        Route::get('inventory/assets/create', [App\Http\Controllers\Admin\AssetController::class, 'create'])->name('inventory.assets.create');
        Route::post('inventory/assets', [App\Http\Controllers\Admin\AssetController::class, 'store'])->name('inventory.assets.store');
        Route::get('inventory/assets/{asset}', [App\Http\Controllers\Admin\AssetController::class, 'show'])->name('inventory.assets.show');
        Route::get('inventory/assets/{asset}/edit', [App\Http\Controllers\Admin\AssetController::class, 'edit'])->name('inventory.assets.edit');
        Route::put('inventory/assets/{asset}', [App\Http\Controllers\Admin\AssetController::class, 'update'])->name('inventory.assets.update');
        Route::delete('inventory/assets/{asset}', [App\Http\Controllers\Admin\AssetController::class, 'destroy'])->name('inventory.assets.destroy');
        
        Route::get('inventory/furniture', [App\Http\Controllers\Admin\InventoryController::class, 'furnitureManagement'])->name('inventory.furniture');
        Route::get('inventory/lab-equipment', [App\Http\Controllers\Admin\InventoryController::class, 'labEquipmentManagement'])->name('inventory.lab-equipment');
        Route::get('inventory/electronics', [App\Http\Controllers\Admin\InventoryController::class, 'electronicsManagement'])->name('inventory.electronics');
        
        // Inventory Reports Routes
        Route::get('inventory/reports', [App\Http\Controllers\Admin\InventoryController::class, 'reports'])->name('inventory.reports');
        Route::get('inventory/reports/valuation', [App\Http\Controllers\Admin\InventoryController::class, 'valuationReport'])->name('inventory.reports.valuation');
        Route::get('inventory/reports/category-distribution', [App\Http\Controllers\Admin\InventoryController::class, 'categoryDistributionReport'])->name('inventory.reports.category-distribution');
        Route::get('inventory/reports/damaged', [App\Http\Controllers\Admin\InventoryController::class, 'damagedReport'])->name('inventory.reports.damaged');
        Route::get('inventory/reports/location', [App\Http\Controllers\Admin\InventoryController::class, 'locationReport'])->name('inventory.reports.location');
        Route::get('inventory/reports/maintenance', [App\Http\Controllers\Admin\InventoryController::class, 'maintenanceReport'])->name('inventory.reports.maintenance');
        Route::get('inventory/reports/warranty', [App\Http\Controllers\Admin\InventoryController::class, 'warrantyReport'])->name('inventory.reports.warranty');
        Route::get('inventory/reports/export', [App\Http\Controllers\Admin\InventoryController::class, 'exportReport'])->name('inventory.reports.export');
        
        // Language Settings Routes
        Route::put('language-settings/{setting}/set-default', [App\Http\Controllers\Admin\LanguageSettingController::class, 'setDefault'])->name('language-settings.set-default');
        Route::put('language-settings/{setting}/toggle-status', [App\Http\Controllers\Admin\LanguageSettingController::class, 'toggleStatus'])->name('language-settings.toggle-status');
        
        // Budget Management Additional Routes
        Route::put('budgets/{budget}/approve', [App\Http\Controllers\Admin\BudgetController::class, 'approve'])->name('budget.approve');
        Route::put('budgets/{budget}/lock', [App\Http\Controllers\Admin\BudgetController::class, 'lock'])->name('budget.lock');
        Route::put('budgets/{budget}/close', [App\Http\Controllers\Admin\BudgetController::class, 'close'])->name('budget.close');
        
        // Additional Budget Routes (already handled by resource)
        
        // Additional Expense Routes
        Route::get('expenses/create/{budgetId}', [App\Http\Controllers\Admin\ExpenseController::class, 'createWithBudget'])->name('expenses.create-with-budget');
        Route::get('expenses/{expense}', [App\Http\Controllers\Admin\ExpenseController::class, 'show'])->name('expenses.show');
        
        // Class Management Additional Routes -- removed (see A3): all 4
        // pointed to ClassController methods that never existed, and no
        // view renders a GET route for the forms that posted to them.

        // Class Teacher Control Routes removed (see A4): assignedClasses/
        // unassignedClasses/teacherAssignments/assignTeacher/removeAssignment
        // never existed on ClassTeacherAssignmentController.

        // Additional Inventory Routes
        Route::get('inventory/audit-logs', [App\Http\Controllers\Admin\InventoryController::class, 'auditLogs'])->name('admin.inventory.audit-logs');
        Route::get('inventory/electronics', [App\Http\Controllers\Admin\InventoryController::class, 'electronicsManagement'])->name('inventory.electronics');
        Route::get('inventory/furniture', [App\Http\Controllers\Admin\InventoryController::class, 'furnitureManagement'])->name('inventory.furniture');
        Route::get('inventory/lab-equipment', [App\Http\Controllers\Admin\InventoryController::class, 'labEquipmentManagement'])->name('inventory.lab-equipment');
        Route::get('inventory/reports', [App\Http\Controllers\Admin\InventoryController::class, 'reports'])->name('inventory.reports');
        Route::get('inventory/reports/valuation', [App\Http\Controllers\Admin\InventoryController::class, 'valuationReport'])->name('inventory.reports.valuation');
        Route::get('inventory/reports/category-distribution', [App\Http\Controllers\Admin\InventoryController::class, 'categoryDistributionReport'])->name('inventory.reports.category-distribution');
        Route::get('inventory/reports/damaged', [App\Http\Controllers\Admin\InventoryController::class, 'damagedReport'])->name('inventory.reports.damaged');
        Route::get('inventory/reports/location', [App\Http\Controllers\Admin\InventoryController::class, 'locationReport'])->name('inventory.reports.location');
        Route::get('inventory/reports/maintenance', [App\Http\Controllers\Admin\InventoryController::class, 'maintenanceReport'])->name('inventory.reports.maintenance');
        Route::get('inventory/reports/warranty', [App\Http\Controllers\Admin\InventoryController::class, 'warrantyReport'])->name('inventory.reports.warranty');
        Route::get('inventory/reports/export', [App\Http\Controllers\Admin\InventoryController::class, 'exportReport'])->name('inventory.reports.export');
        Route::get('inventory/audit-logs', [App\Http\Controllers\Admin\InventoryController::class, 'auditLogs'])->name('inventory.audit-logs');
        Route::get('inventory/audit-logs/export', [App\Http\Controllers\Admin\InventoryController::class, 'exportAuditLogs'])->name('inventory.audit-logs.export');
        
        
        
        // Additional Inventory Electronics/Furniture/Lab-Equipment Routes
        Route::get('inventory/electronics', [App\Http\Controllers\Admin\InventoryController::class, 'electronicsManagement'])->name('inventory.electronics');
        Route::get('inventory/furniture', [App\Http\Controllers\Admin\InventoryController::class, 'furnitureManagement'])->name('inventory.furniture');
        Route::get('inventory/lab-equipment', [App\Http\Controllers\Admin\InventoryController::class, 'labEquipmentManagement'])->name('inventory.lab-equipment');
        
        // Additional Inventory Reports Routes
        Route::get('inventory/reports', [App\Http\Controllers\Admin\InventoryController::class, 'reports'])->name('inventory.reports');
        Route::get('inventory/reports/valuation', [App\Http\Controllers\Admin\InventoryController::class, 'valuationReport'])->name('inventory.reports.valuation');
        Route::get('inventory/reports/category-distribution', [App\Http\Controllers\Admin\InventoryController::class, 'categoryDistributionReport'])->name('inventory.reports.category-distribution');
        Route::get('inventory/reports/damaged', [App\Http\Controllers\Admin\InventoryController::class, 'damagedReport'])->name('inventory.reports.damaged');
        Route::get('inventory/reports/location', [App\Http\Controllers\Admin\InventoryController::class, 'locationReport'])->name('inventory.reports.location');
        Route::get('inventory/reports/maintenance', [App\Http\Controllers\Admin\InventoryController::class, 'maintenanceReport'])->name('inventory.reports.maintenance');
        Route::get('inventory/reports/warranty', [App\Http\Controllers\Admin\InventoryController::class, 'warrantyReport'])->name('inventory.reports.warranty');
        Route::get('inventory/reports/export', [App\Http\Controllers\Admin\InventoryController::class, 'exportReport'])->name('inventory.reports.export');
        
        // Additional Inventory Audit Logs Routes
        Route::get('inventory/audit-logs', [App\Http\Controllers\Admin\InventoryController::class, 'auditLogs'])->name('inventory.audit-logs');
        Route::get('inventory/audit-logs/export', [App\Http\Controllers\Admin\InventoryController::class, 'exportAuditLogs'])->name('inventory.audit-logs.export');
        
        // Class Management Routes -- ClassController retired (see A3), was
        // a duplicate registration of the block above.

        // Class Teacher Control Routes -- removed (see A4), duplicate of the
        // block above (also dead: none of these methods ever existed).

        // Additional Expenses Routes (already defined earlier)
        
        // Academic Sessions Additional Routes (already handled by resource)
        
        // Exam details AJAX route for result entry
        Route::get('exams/{exam}/details', [App\Http\Controllers\Admin\ExamController::class, 'getExamDetails'])
            ->name('exams.details');
        
        // Subject auto-load AJAX route for result entry
        Route::get('/get-subject-by-exam/{examId}', [App\Http\Controllers\Admin\ResultController::class, 'getSubjectByExam'])->name('results.get-subject-by-exam');
        
        // Generate Report Card page
        Route::get('/generate-report-card', [App\Http\Controllers\Admin\ResultController::class, 'showGenerateReportCardForm'])->name('results.generate-form');
        Route::post('/generate-report-card', [App\Http\Controllers\Admin\ResultController::class, 'generateReportCardFromForm'])->name('results.generate');
        
    });
    
});

// Generate Final Result (CBSE Style) - Outside admin group for proper naming
Route::get('/admin/results/final-result/{studentId}/{examId}', [App\Http\Controllers\Admin\ResultController::class, 'generateFinalResult'])
    ->name('admin.results.final-result')
    ->middleware(['auth', 'verified']);
    
    // Test routes - ONLY AVAILABLE IN LOCAL ENVIRONMENT
    if (app()->environment('local')) {
        Route::get('/test-final-result/{studentId}/{examId}', function ($studentId, $examId) {
            $results = \App\Models\Result::where('student_id', $studentId)
                ->where('exam_id', $examId)
                ->get();
            
            return response()->json([
                'student_id' => $studentId,
                'exam_id' => $examId,
                'count' => $results->count(),
                'results' => $results->toArray()
            ]);
        });
        
        Route::get('/test-generate-form', [App\Http\Controllers\Admin\ResultController::class, 'showGenerateReportCardForm'])->name('test.generate-form');
    }
    
    // Exam Head Dashboard Route (outside admin group for direct access)
    Route::get('/exam-head/dashboard', [App\Http\Controllers\ExamHead\ExamHeadDashboardController::class, 'index'])->middleware(['auth', 'exam.head'])->name('exam-head.dashboard');

    // API routes for biometric devices
    Route::prefix('api')->group(function () {
        // Biometric API routes
        Route::prefix('biometric')->group(function () {
            Route::post('devices/{deviceId}/test-connection', [App\Http\Controllers\Api\BiometricController::class, 'testConnection']);
            Route::post('devices/{deviceId}/sync', [App\Http\Controllers\Api\BiometricController::class, 'syncDevice']);
            Route::get('devices/{deviceId}/status', [App\Http\Controllers\Api\BiometricController::class, 'getDeviceStatus']);
            Route::get('devices/{deviceId}/logs', [App\Http\Controllers\Api\BiometricController::class, 'getSyncLogs']);
            Route::get('statistics', [App\Http\Controllers\Api\BiometricController::class, 'getSyncStatistics']);
            Route::post('sync-all', [App\Http\Controllers\Api\BiometricController::class, 'syncAllDevices']);
            Route::post('devices/{deviceId}/webhook', [App\Http\Controllers\Api\BiometricController::class, 'webhook']);
        });
        
        // Self-service API routes
        Route::prefix('self-service')->group(function () {
            Route::post('authenticate', [App\Http\Controllers\Api\SelfServiceController::class, 'authenticate']);
            Route::middleware('auth:sanctum')->group(function () {
                Route::get('attendance', [App\Http\Controllers\Api\SelfServiceController::class, 'getAttendance']);
                Route::get('summary/{month?}', [App\Http\Controllers\Api\SelfServiceController::class, 'getMonthlySummary']);
                Route::get('trends', [App\Http\Controllers\Api\SelfServiceController::class, 'getPerformanceTrends']);
                Route::post('download-report', [App\Http\Controllers\Api\SelfServiceController::class, 'downloadReport']);
            });
        });
        
        // Webhook routes
        Route::prefix('webhooks')->group(function () {
            Route::post('biometric/{webhookToken}', [App\Http\Controllers\WebhookController::class, 'handleBiometricWebhook']);
            Route::get('health', [App\Http\Controllers\WebhookController::class, 'healthCheck']);
            Route::get('config-info', [App\Http\Controllers\WebhookController::class, 'getConfigInfo']);
        });
    });
    
    // Attendance Routes -- previously carried only the global 'web'
    // middleware (no auth at all), despite being the main attendance UI
    // linked from the admin/home/parent dashboards, not a device/API
    // integration. Matched to the auth stack its admin.attendance.*
    // sibling already uses.
    Route::middleware(['auth', 'verified', 'redirect.if.not.onboarded'])->group(function () {
        Route::get('/attendance/reports', [AttendanceController::class, 'reports'])->name('attendance.reports');
        Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
        Route::get('/attendance/bulk-mark', [AttendanceController::class, 'bulkMark'])->name('attendance.bulk-mark');
        Route::get('/attendance/student/{studentId}/report', [AttendanceController::class, 'studentReport'])->name('attendance.student.report');
        Route::resource('attendance', AttendanceController::class)->except(['reports', 'export']);
    });
    
    // Library Management Routes
    Route::resource('books', App\Http\Controllers\Admin\BookController::class);
    Route::resource('book-issues', App\Http\Controllers\Admin\BookIssueController::class);
    Route::resource('library-settings', App\Http\Controllers\Admin\LibrarySettingController::class);
    Route::get('/library/dashboard', [App\Http\Controllers\Admin\BookController::class, 'dashboard'])->name('library.dashboard');
    Route::get('/library/return/{id}', [App\Http\Controllers\Admin\BookIssueController::class, 'returnBook'])->name('library.return');
    Route::get('/library/reports', [App\Http\Controllers\Admin\BookIssueController::class, 'reports'])->name('library.reports');
    Route::get('/library/export/{type?}', [App\Http\Controllers\Admin\BookIssueController::class, 'exportReport'])->name('library.export');
    
    
    // Teacher Biometric Routes
    Route::prefix('teacher')->name('teacher.')->group(function () {
        Route::get('/biometric/dashboard', [App\Http\Controllers\Teacher\BiometricController::class, 'dashboard'])->name('biometric.dashboard');
        Route::get('/biometric/records', [App\Http\Controllers\Teacher\BiometricController::class, 'getRecords'])->name('biometric.records');
        Route::get('/biometric/monthly-summary', [App\Http\Controllers\Teacher\BiometricController::class, 'monthlySummary'])->name('biometric.monthly-summary');
        Route::post('/biometric/dashboard/download', [App\Http\Controllers\Teacher\BiometricController::class, 'downloadReport'])->name('biometric.download');
        Route::get('/biometric/notification-preferences', [App\Http\Controllers\Teacher\BiometricController::class, 'notificationPreferences'])->name('biometric.notification-preferences');
        Route::post('/biometric/notification-preferences', [App\Http\Controllers\Teacher\BiometricController::class, 'updateNotificationPreferences'])->name('biometric.update-notification-preferences');
    });
    
    // CBSE Results Routes
    Route::resource('results', ResultController::class);
    Route::post('results/{result}/toggle-lock', [ResultController::class, 'toggleLock'])->name('results.toggle-lock');
    Route::get('results/{result}/pdf', [ResultController::class, 'generatePdf'])->name('results.pdf');
    Route::post('results/bulk-upload', [ResultController::class, 'bulkUpload'])->name('results.bulk-upload');
    Route::get('results/single-subject/{studentId}/{examId}/{subjectId}', [ResultController::class, 'generateSingleSubjectResult'])->name('results.single-subject');
    
    // Multi-Subject Result Entry Routes
    Route::prefix('results')->name('results.')->group(function () {
        Route::get('entry', [ResultEntryController::class, 'index'])->name('entry.index');
        Route::get('entry/create', [ResultEntryController::class, 'create'])->name('entry.create');
        Route::post('entry', [ResultEntryController::class, 'store'])->name('entry.store');
        Route::get('entry/{result}/edit', [ResultEntryController::class, 'edit'])->name('entry.edit');
        Route::put('entry/{result}', [ResultEntryController::class, 'update'])->name('entry.update');
        Route::delete('entry/{result}', [ResultEntryController::class, 'destroy'])->name('entry.destroy');
        Route::post('entry/{result}/verify', [ResultEntryController::class, 'verify'])->name('entry.verify');
        Route::post('entry/{result}/unverify', [ResultEntryController::class, 'unverify'])->name('entry.unverify');
        Route::get('entry/bulk-entry', [ResultEntryController::class, 'bulkEntryForm'])->name('entry.bulk-form');
        Route::post('entry/bulk-entry', [ResultEntryController::class, 'processBulkEntry'])->name('entry.bulk-process');
    });
    
    // Result Verification Routes
    Route::prefix('results')->name('results.')->group(function () {
        Route::get('verification', [ResultVerificationController::class, 'index'])->name('verification.index');
        Route::get('verification/student/{studentId}', [ResultVerificationController::class, 'show'])->name('verification.show');
        Route::post('verification/bulk-verify', [ResultVerificationController::class, 'bulkVerify'])->name('verification.bulk-verify');
        Route::post('verification/bulk-unverify', [ResultVerificationController::class, 'bulkUnverify'])->name('verification.bulk-unverify');
        Route::get('verification/statistics', [ResultVerificationController::class, 'statistics'])->name('verification.statistics');
    });
    
    // Final Result Generation Routes
    Route::prefix('results')->name('results.')->group(function () {
        Route::get('final/{studentId}/{examId}', [FinalResultController::class, 'generate'])->name('final.generate');
        Route::get('final/{studentId}/{examId}/pdf', [FinalResultController::class, 'downloadPdf'])->name('final.pdf');
        Route::get('final/{studentId}/{examId}/print', [FinalResultController::class, 'print'])->name('final.print');
    });
    
    // Parent Lesson Plan Routes
    Route::prefix('parent')->name('parent.')->group(function () {
        Route::middleware('parent.auth')->group(function () {
            Route::get('/lesson-plans', [App\Http\Controllers\Parent\LessonPlanController::class, 'index'])->name('lesson-plans.index');
            Route::get('/lesson-plans/{lessonPlan}', [App\Http\Controllers\Parent\LessonPlanController::class, 'show'])->name('lesson-plans.show');
            Route::get('/lesson-plans/books-to-send', [App\Http\Controllers\Parent\LessonPlanController::class, 'booksToSend'])->name('lesson-plans.books-to-send');
            Route::get('/lesson-plans/weekly-overview', [App\Http\Controllers\Parent\LessonPlanController::class, 'weeklyOverview'])->name('lesson-plans.weekly-overview');
            
            // Professional Lesson Plan Parent Routes
            Route::get('/professional-lesson-plans', [App\Http\Controllers\Parent\ProfessionalLessonPlanController::class, 'index'])->name('professional-lesson-plans.index');
            Route::get('/professional-lesson-plans/{lessonPlan}', [App\Http\Controllers\Parent\ProfessionalLessonPlanController::class, 'show'])->name('professional-lesson-plans.show');
            
            // Parent Homework Routes
            Route::get('/homework', [App\Http\Controllers\Parent\HomeworkController::class, 'index'])->name('homework.index');
            Route::get('/homework/{homeworkNotice}', [App\Http\Controllers\Parent\HomeworkController::class, 'show'])->name('homework.show');

            // T5 item 1: today's periods (published timetable + substitutions)
            Route::get('/timetable/today', [App\Http\Controllers\Parent\TimetableController::class, 'today'])->name('timetable.today');
            // Timetable pilot-completion pass (Phase 3): weekly companion.
            Route::get('/timetable/weekly', [App\Http\Controllers\Parent\TimetableController::class, 'weekly'])->name('timetable.weekly');
        });
    });
    

    

    
// ============================================================================
// DEBUG/PROBE ROUTES - ONLY AVAILABLE IN LOCAL ENVIRONMENT
// These routes are disabled in production for security
// ============================================================================
if (app()->environment('local')) {
    Route::get('/_route-test', function () {
        return 'ROUTES ARE WORKING';
    });

    Route::get('/_parent-test', function () {
        return 'Parent routes working';
    });

    // Test parent login route without middleware
    Route::get('/_parent-login-test', function () {
        return 'Parent login route working';
    });

    // Debug routes for teacher attendance reports
    Route::get('/admin/test-reports', function() {
        return view('admin.teacher-attendance.reports', [
            'teachers' => [],
            'date' => now()->toDateString(),
            'stats' => ['total' => 0, 'present' => 0, 'absent' => 0, 'attendance_rate' => 0]
        ]);
    });

    Route::get('/_auth-test', function () {
        if (Auth::check()) {
            return 'Authenticated as: ' . Auth::user()->email . ' with roles: ' . Auth::user()->roles->pluck('name')->join(', ');
        } else {
            return 'Not authenticated';
        }
    });
    
    // Test route for subject loading
    Route::get('/test-subject-ajax/{examId}', function ($examId) {
        $exam = \App\Models\Exam::find($examId);
            
        if (!$exam) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found'
            ]);
        }
            
        return response()->json([
            'success' => true,
            'subject' => $exam->subject,
            'total_marks' => $exam->total_marks
        ]);
    });
}

// ============================================================
// HR LEAVES & PAYROLL ROUTES (ADMIN & TEACHER)
// ============================================================
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/hr/leaves', [\App\Http\Controllers\Admin\AdminTeacherLeaveController::class, 'index'])->name('admin.leaves.index');
    Route::get('/admin/hr/leaves/{id}', [\App\Http\Controllers\Admin\AdminTeacherLeaveController::class, 'show'])->name('admin.leaves.show');
    Route::put('/admin/hr/leaves/{id}', [\App\Http\Controllers\Admin\AdminTeacherLeaveController::class, 'update'])->name('admin.leaves.update');
    
    Route::get('/admin/hr/payroll', [\App\Http\Controllers\Admin\AdminPayrollController::class, 'index'])->name('admin.hr.payroll.index');
    Route::get('/admin/hr/payroll/preview-deduction', [\App\Http\Controllers\Admin\AdminPayrollController::class, 'previewDeduction'])->name('admin.hr.payroll.preview-deduction');
    Route::post('/admin/hr/payroll/generate', [\App\Http\Controllers\Admin\AdminPayrollController::class, 'generate'])->name('admin.hr.payroll.generate');
    Route::get('/admin/hr/payroll/{salary}/pdf', [\App\Http\Controllers\Admin\AdminPayrollController::class, 'downloadSlip'])->name('admin.hr.payroll.pdf');
});

Route::middleware(['auth:teacher'])->group(function () {
    Route::get('/teacher/leaves', [\App\Http\Controllers\Teacher\TeacherLeaveController::class, 'index'])->name('teacher.leaves.index');
    Route::get('/teacher/leaves/create', [\App\Http\Controllers\Teacher\TeacherLeaveController::class, 'create'])->name('teacher.leaves.create');
    Route::post('/teacher/leaves', [\App\Http\Controllers\Teacher\TeacherLeaveController::class, 'store'])->name('teacher.leaves.store');

    Route::get('/teacher/salaries', [\App\Http\Controllers\Teacher\TeacherSalaryController::class, 'index'])->name('teacher.salaries.index');
    Route::get('/teacher/salaries/{salary}/pdf', [\App\Http\Controllers\Teacher\TeacherSalaryController::class, 'downloadSlip'])->name('teacher.salaries.pdf');
    
    Route::get('/teacher/homework/{homework}/submissions', [\App\Http\Controllers\Teacher\TeacherHomeworkSubmissionController::class, 'index'])->name('teacher.homework.submissions.index');
    Route::get('/teacher/homework/submissions/{submission}/evaluate', [\App\Http\Controllers\Teacher\TeacherHomeworkSubmissionController::class, 'evaluateForm'])->name('teacher.homework.submissions.evaluate');
    Route::post('/teacher/homework/submissions/{submission}/evaluate', [\App\Http\Controllers\Teacher\TeacherHomeworkSubmissionController::class, 'storeEvaluation'])->name('teacher.homework.submissions.store-evaluation');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/student/homework', [\App\Http\Controllers\Student\StudentHomeworkController::class, 'index'])->name('student.homework.index');
    Route::get('/student/homework/{homework}/submit', [\App\Http\Controllers\Student\StudentHomeworkController::class, 'submitForm'])->name('student.homework.submit');
    Route::post('/student/homework/{homework}/submit', [\App\Http\Controllers\Student\StudentHomeworkController::class, 'storeSubmission'])->name('student.homework.store-submission');
});

// ============================================================
// NEW ERPS BLUEPRINT MODULES (MEDICAL, DISCIPLINE, NOTEBOOKS, QUIZZES, PTM)
// ============================================================

Route::middleware(['auth'])->group(function () {
    // Admin Hostels
    Route::get('/admin/hostels', [\App\Http\Controllers\Admin\HostelController::class, 'index'])->name('admin.hostels.index');
    Route::post('/admin/hostels/hostel', [\App\Http\Controllers\Admin\HostelController::class, 'storeHostel'])->name('admin.hostels.store-hostel');
    Route::post('/admin/hostels/room', [\App\Http\Controllers\Admin\HostelController::class, 'storeRoom'])->name('admin.hostels.store-room');
    Route::post('/admin/hostels/assign', [\App\Http\Controllers\Admin\HostelController::class, 'allocateBed'])->name('admin.hostels.store-allocation');

    // Admin Alumni
    Route::get('/admin/alumni', [\App\Http\Controllers\Admin\AdminAlumniController::class, 'index'])->name('admin.alumni.index');
    Route::post('/admin/alumni', [\App\Http\Controllers\Admin\AdminAlumniController::class, 'store'])->name('admin.alumni.store');

    // Admin Gate Entries
    Route::get('/admin/gate-entries', [\App\Http\Controllers\Admin\AdminGateEntryController::class, 'index'])->name('admin.gate-entries.index');
    Route::post('/admin/gate-entries', [\App\Http\Controllers\Admin\AdminGateEntryController::class, 'store'])->name('admin.gate-entries.store');
    Route::post('/admin/gate-entries/{id}/checkout', [\App\Http\Controllers\Admin\AdminGateEntryController::class, 'checkout'])->name('admin.gate-entries.checkout');

    // Admin Admissions (same role gate as the front-office enquiry CRM below)
    Route::middleware(['role:admin,super-admin,receptionist,teacher,clerk'])->group(function () {
        Route::get('/admin/admissions', [\App\Http\Controllers\Admin\AdminAdmissionController::class, 'index'])->name('admin.admissions.index');
        Route::post('/admin/admissions/enquiry', [\App\Http\Controllers\Admin\AdminAdmissionController::class, 'storeEnquiry'])->name('admin.admissions.store-enquiry');
        Route::post('/admin/admissions/{id}/schedule', [\App\Http\Controllers\Admin\AdminAdmissionController::class, 'scheduleInterview'])->name('admin.admissions.schedule');
        Route::post('/admin/admissions/{id}/evaluate', [\App\Http\Controllers\Admin\AdminAdmissionController::class, 'evaluate'])->name('admin.admissions.evaluate');
        Route::get('/admin/admissions/{id}/confirm', [\App\Http\Controllers\Admin\AdminAdmissionController::class, 'showConfirmForm'])->name('admin.admissions.confirm-form');
        Route::post('/admin/admissions/{id}/confirm', [\App\Http\Controllers\Admin\AdminAdmissionController::class, 'confirmAdmission'])->name('admin.admissions.confirm-admission');
    });

    // Front Office (Counsellor/Staff Allowed) Routes
    Route::middleware(['role:admin,super-admin,receptionist,teacher,clerk'])->prefix('admin/front-office')->name('admin.front-office.')->group(function () {
        // Enquiries
        Route::get('/enquiries', [\App\Http\Controllers\Admin\AdmissionEnquiryController::class, 'index'])->name('enquiries.index');
        Route::get('/enquiries/create', [\App\Http\Controllers\Admin\AdmissionEnquiryController::class, 'create'])->name('enquiries.create');
        Route::post('/enquiries', [\App\Http\Controllers\Admin\AdmissionEnquiryController::class, 'store'])->name('enquiries.store');
        Route::get('/enquiries/{id}', [\App\Http\Controllers\Admin\AdmissionEnquiryController::class, 'show'])->name('enquiries.show');
        Route::get('/enquiries/{id}/edit', [\App\Http\Controllers\Admin\AdmissionEnquiryController::class, 'edit'])->name('enquiries.edit');
        Route::put('/enquiries/{id}', [\App\Http\Controllers\Admin\AdmissionEnquiryController::class, 'update'])->name('enquiries.update');
        Route::delete('/enquiries/{id}', [\App\Http\Controllers\Admin\AdmissionEnquiryController::class, 'destroy'])->name('enquiries.destroy');
        Route::post('/enquiries/{id}/follow-up', [\App\Http\Controllers\Admin\AdmissionEnquiryController::class, 'storeFollowUp'])->name('enquiries.follow-up');
        Route::post('/enquiries/{id}/counsellor', [\App\Http\Controllers\Admin\AdmissionEnquiryController::class, 'assignCounsellor'])->name('enquiries.counsellor');
        Route::post('/enquiries/check-duplicate', [\App\Http\Controllers\Admin\AdmissionEnquiryController::class, 'checkDuplicate'])->name('enquiries.check-duplicate');
        Route::post('/enquiries/{id}/documents', [\App\Http\Controllers\Admin\AdmissionEnquiryController::class, 'uploadDocuments'])->name('enquiries.documents.upload');
        Route::post('/enquiries/documents/{documentId}/verify', [\App\Http\Controllers\Admin\AdmissionEnquiryController::class, 'verifyDocument'])->name('enquiries.documents.verify');
        Route::post('/enquiries/{id}/payments', [\App\Http\Controllers\Admin\AdmissionEnquiryController::class, 'recordPayment'])->name('enquiries.payments.record');

        // Appointments
        Route::get('/appointments/search-guardians', [\App\Http\Controllers\Admin\AppointmentController::class, 'searchGuardians'])->name('appointments.search-guardians');
        Route::get('/appointments/search-teachers', [\App\Http\Controllers\Admin\AppointmentController::class, 'searchTeachers'])->name('appointments.search-teachers');
        Route::get('/appointments', [\App\Http\Controllers\Admin\AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/appointments/create', [\App\Http\Controllers\Admin\AppointmentController::class, 'create'])->name('appointments.create');
        Route::post('/appointments', [\App\Http\Controllers\Admin\AppointmentController::class, 'store'])->name('appointments.store');
        Route::get('/appointments/{id}', [\App\Http\Controllers\Admin\AppointmentController::class, 'show'])->name('appointments.show');
        Route::get('/appointments/{id}/edit', [\App\Http\Controllers\Admin\AppointmentController::class, 'edit'])->name('appointments.edit');
        Route::put('/appointments/{id}', [\App\Http\Controllers\Admin\AppointmentController::class, 'update'])->name('appointments.update');
        Route::delete('/appointments/{id}', [\App\Http\Controllers\Admin\AppointmentController::class, 'destroy'])->name('appointments.destroy');
        Route::post('/appointments/{id}/status', [\App\Http\Controllers\Admin\AppointmentController::class, 'updateStatus'])->name('appointments.status');
    });

    // Gatekeeper Security Terminal
    Route::middleware(['role:admin,super-admin,receptionist,teacher,clerk,guard'])->prefix('admin/front-office')->name('admin.front-office.')->group(function () {
        Route::get('/gatekeeper', [\App\Http\Controllers\Admin\GatePassController::class, 'gatekeeper'])->name('gatekeeper');
        Route::post('/gate-passes/{id}/request-gate-change', [\App\Http\Controllers\Admin\GatePassController::class, 'requestGateChange'])->name('gate-passes.request-gate-change');
    });

    // Front Office (Strictly Receptionist/Admin) Routes
    Route::middleware(['role:admin,super-admin,receptionist'])->prefix('admin/front-office')->name('admin.front-office.')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\FrontOfficeDashboardController::class, 'index'])->name('dashboard');
        
        // AJAX searches for Selectors
        Route::get('/students/ajax-search', [\App\Http\Controllers\Admin\GatePassController::class, 'searchStudents'])->name('students.ajax-search');
        Route::get('/staff/ajax-search', [\App\Http\Controllers\Admin\GatePassController::class, 'searchStaff'])->name('staff.ajax-search');
        
        // Visitors
        Route::get('/visitors', [\App\Http\Controllers\Admin\VisitorManagementController::class, 'index'])->name('visitors.index');
        Route::get('/visitors/create', [\App\Http\Controllers\Admin\VisitorManagementController::class, 'create'])->name('visitors.create');
        Route::post('/visitors', [\App\Http\Controllers\Admin\VisitorManagementController::class, 'store'])->name('visitors.store');
        Route::get('/visitors/{id}', [\App\Http\Controllers\Admin\VisitorManagementController::class, 'show'])->name('visitors.show');
        Route::get('/visitors/{id}/edit', [\App\Http\Controllers\Admin\VisitorManagementController::class, 'edit'])->name('visitors.edit');
        Route::put('/visitors/{id}', [\App\Http\Controllers\Admin\VisitorManagementController::class, 'update'])->name('visitors.update');
        Route::delete('/visitors/{id}', [\App\Http\Controllers\Admin\VisitorManagementController::class, 'destroy'])->name('visitors.destroy');
        Route::post('/visitors/{id}/blacklist', [\App\Http\Controllers\Admin\VisitorManagementController::class, 'toggleBlacklist'])->name('visitors.blacklist');
        Route::get('/visitors/{id}/badge', [\App\Http\Controllers\Admin\VisitorManagementController::class, 'printBadge'])->name('visitors.badge');

        // Calls
        Route::resource('/calls', \App\Http\Controllers\Admin\CallRegisterController::class);

        // Gate Passes
        Route::get('/gate-passes', [\App\Http\Controllers\Admin\GatePassController::class, 'index'])->name('gate-passes.index');
        Route::get('/gate-passes/create', [\App\Http\Controllers\Admin\GatePassController::class, 'create'])->name('gate-passes.create');
        Route::post('/gate-passes', [\App\Http\Controllers\Admin\GatePassController::class, 'store'])->name('gate-passes.store');
        Route::get('/gate-passes/{id}', [\App\Http\Controllers\Admin\GatePassController::class, 'show'])->name('gate-passes.show');
        Route::get('/gate-passes/{id}/edit', [\App\Http\Controllers\Admin\GatePassController::class, 'edit'])->name('gate-passes.edit');
        Route::put('/gate-passes/{id}', [\App\Http\Controllers\Admin\GatePassController::class, 'update'])->name('gate-passes.update');
        Route::delete('/gate-passes/{id}', [\App\Http\Controllers\Admin\GatePassController::class, 'destroy'])->name('gate-passes.destroy');
        Route::post('/gate-passes/{id}/verify', [\App\Http\Controllers\Admin\GatePassController::class, 'verifyExit'])->name('gate-passes.verify');

        // Courier
        Route::resource('/couriers', \App\Http\Controllers\Admin\CourierController::class);

        // Lost & Found
        Route::get('/lost-found', [\App\Http\Controllers\Admin\LostFoundController::class, 'index'])->name('lost-found.index');
        Route::get('/lost-found/create', [\App\Http\Controllers\Admin\LostFoundController::class, 'create'])->name('lost-found.create');
        Route::post('/lost-found', [\App\Http\Controllers\Admin\LostFoundController::class, 'store'])->name('lost-found.store');
        Route::get('/lost-found/{id}', [\App\Http\Controllers\Admin\LostFoundController::class, 'show'])->name('lost-found.show');
        Route::get('/lost-found/{id}/edit', [\App\Http\Controllers\Admin\LostFoundController::class, 'edit'])->name('lost-found.edit');
        Route::put('/lost-found/{id}', [\App\Http\Controllers\Admin\LostFoundController::class, 'update'])->name('lost-found.update');
        Route::delete('/lost-found/{id}', [\App\Http\Controllers\Admin\LostFoundController::class, 'destroy'])->name('lost-found.destroy');
        Route::post('/lost-found/{id}/claim', [\App\Http\Controllers\Admin\LostFoundController::class, 'claim'])->name('lost-found.claim');

        // Guard Duty Assignments
        Route::get('/duty-assignments', [\App\Http\Controllers\Admin\GuardDutyController::class, 'index'])->name('duty-assignments.index');
        Route::post('/duty-assignments', [\App\Http\Controllers\Admin\GuardDutyController::class, 'store'])->name('duty-assignments.store');
        Route::delete('/duty-assignments/{id}', [\App\Http\Controllers\Admin\GuardDutyController::class, 'destroy'])->name('duty-assignments.destroy');
    });

    // Admin Inventory Purchase Requisitions
    Route::get('/admin/inventory/purchase-requests', [\App\Http\Controllers\Admin\AdminInventoryPurchaseController::class, 'index'])->name('admin.inventory.purchase-requests.index');
    Route::post('/admin/inventory/purchase-requests', [\App\Http\Controllers\Admin\AdminInventoryPurchaseController::class, 'store'])->name('admin.inventory.purchase-requests.store');
    Route::post('/admin/inventory/purchase-requests/{id}/status', [\App\Http\Controllers\Admin\AdminInventoryPurchaseController::class, 'updateStatus'])->name('admin.inventory.purchase-requests.update-status');

    // Student Medical & Discipline
    Route::get('/student/medical', [\App\Http\Controllers\Student\StudentMedicalController::class, 'index'])->name('student.medical.index');
    Route::get('/student/discipline', [\App\Http\Controllers\Student\StudentDisciplineController::class, 'index'])->name('student.discipline.index');

    // Student Leaves
    Route::get('/student/leaves', [\App\Http\Controllers\Student\StudentLeaveController::class, 'index'])->name('student.leaves.index');
    Route::post('/student/leaves', [\App\Http\Controllers\Student\StudentLeaveController::class, 'store'])->name('student.leaves.store');

    // Student Quizzes
    Route::get('/student/quizzes', [\App\Http\Controllers\Student\StudentQuizController::class, 'index'])->name('student.quizzes.index');
    Route::get('/student/quizzes/{id}/take', [\App\Http\Controllers\Student\StudentQuizController::class, 'showTake'])->name('student.quizzes.take');
    Route::post('/student/quizzes/{id}/submit', [\App\Http\Controllers\Student\StudentQuizController::class, 'storeSubmission'])->name('student.quizzes.submit');
});

// Teacher routes
Route::middleware(['auth:teacher'])->group(function () {
    Route::get('/teacher/notebooks', [\App\Http\Controllers\Teacher\TeacherNotebookCheckingController::class, 'index'])->name('teacher.notebooks.index');
    Route::post('/teacher/notebooks', [\App\Http\Controllers\Teacher\TeacherNotebookCheckingController::class, 'store'])->name('teacher.notebooks.store');
    
    Route::get('/teacher/quizzes', [\App\Http\Controllers\Teacher\TeacherQuizController::class, 'index'])->name('teacher.quizzes.index');
    Route::post('/teacher/quizzes', [\App\Http\Controllers\Teacher\TeacherQuizController::class, 'storeQuiz'])->name('teacher.quizzes.store');
    Route::get('/teacher/quizzes/{id}', [\App\Http\Controllers\Teacher\TeacherQuizController::class, 'showQuiz'])->name('teacher.quizzes.show');
    Route::post('/teacher/quizzes/{id}/question', [\App\Http\Controllers\Teacher\TeacherQuizController::class, 'storeQuestion'])->name('teacher.quizzes.store-question');

    // Teacher Student Leaves
    Route::get('/teacher/student-leaves', [\App\Http\Controllers\Teacher\TeacherStudentLeaveController::class, 'index'])->name('teacher.student-leaves.index');
    Route::post('/teacher/student-leaves/{id}', [\App\Http\Controllers\Teacher\TeacherStudentLeaveController::class, 'update'])->name('teacher.student-leaves.update');

    // Teacher Uniform Checklist
    Route::get('/teacher/uniform', [\App\Http\Controllers\Teacher\ClassTeacherChecklistController::class, 'index'])->name('teacher.uniform.index');
    Route::post('/teacher/uniform', [\App\Http\Controllers\Teacher\ClassTeacherChecklistController::class, 'store'])->name('teacher.uniform.store');

    // Teacher Remedial Log
    Route::get('/teacher/remedial', [\App\Http\Controllers\Teacher\TeacherRemedialController::class, 'index'])->name('teacher.remedial.index');
    Route::post('/teacher/remedial', [\App\Http\Controllers\Teacher\TeacherRemedialController::class, 'store'])->name('teacher.remedial.store');
});

// Parent PTM & Payment routes
Route::middleware(['parent.auth'])->group(function () {
    Route::get('/parent/ptm', [\App\Http\Controllers\Parent\ParentPtmController::class, 'index'])->name('parent.ptm.index');
    Route::post('/parent/ptm', [\App\Http\Controllers\Parent\ParentPtmController::class, 'store'])->name('parent.ptm.store');

    // Parent Hostel Room view
    Route::get('/parent/hostels', [\App\Http\Controllers\Parent\ParentHostelController::class, 'index'])->name('parent.hostels.index');

    // Parent Online payments
    Route::get('/parent/payments/pay-fees', [\App\Http\Controllers\Parent\ParentPaymentController::class, 'showPaymentForm'])->name('parent.payments.pay-fees');
    Route::post('/parent/payments/stripe-checkout', [\App\Http\Controllers\Parent\ParentPaymentController::class, 'processStripePayment'])->name('parent.payments.stripe-checkout');
    Route::get('/parent/payments/stripe-success', [\App\Http\Controllers\Parent\ParentPaymentController::class, 'callbackSuccess'])->name('parent.payments.stripe-success');
    Route::get('/parent/payments/upi-qr', [\App\Http\Controllers\Parent\ParentPaymentController::class, 'generateUpiQr'])->name('parent.payments.upi-qr');
    Route::post('/parent/payments/submit-claim', [\App\Http\Controllers\Parent\ParentPaymentController::class, 'submitClaim'])->name('parent.payments.submit-claim');
    Route::post('/parent/payments/submit-cash-deposit-claim', [\App\Http\Controllers\Parent\ParentPaymentController::class, 'submitCashDepositClaim'])->name('parent.payments.submit-cash-deposit-claim');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/student/dashboard', [\App\Http\Controllers\Admin\RoleDashboardController::class, 'studentDashboard'])->name('student.dashboard');
    Route::get('/accountant/dashboard', [\App\Http\Controllers\Admin\RoleDashboardController::class, 'accountantDashboard'])->name('accountant.dashboard');
});

// Phase 6 Routes
Route::middleware(['auth'])->group(function () {
    // Timetable Scheduler
    // Timetable Editor Phase B: single consolidated workspace, entry point
    // the sidebar now points to -- timetable.index (the standalone grid)
    // stays registered and fully functional underneath it, unchanged.
    Route::get('/admin/timetable/workspace', [\App\Http\Controllers\Admin\TimetableController::class, 'workspace'])->name('timetable.workspace');
    Route::get('/admin/timetable', [\App\Http\Controllers\Admin\TimetableController::class, 'index'])->name('timetable.index');
    Route::post('/admin/timetable', [\App\Http\Controllers\Admin\TimetableController::class, 'store'])->name('timetable.store');
    Route::patch('/admin/timetable/{slot}', [\App\Http\Controllers\Admin\TimetableController::class, 'update'])->name('timetable.update');
    Route::delete('/admin/timetable/{id}', [\App\Http\Controllers\Admin\TimetableController::class, 'destroy'])->name('timetable.destroy');
    // Phase 5 (Locked Lessons): gated the same as editing the slot (TimetableSlotPolicy::update()).
    Route::post('/admin/timetable/{slot}/lock', [\App\Http\Controllers\Admin\TimetableController::class, 'lockSlot'])->name('timetable.lock');
    Route::post('/admin/timetable/{slot}/unlock', [\App\Http\Controllers\Admin\TimetableController::class, 'unlockSlot'])->name('timetable.unlock');
    Route::get('/admin/timetable/check-conflicts', [\App\Http\Controllers\Admin\TimetableController::class, 'checkConflictsApi'])->name('timetable.check-conflicts');
    Route::post('/admin/timetable/auto-fix/relocate-blocker', [\App\Http\Controllers\Admin\TimetableController::class, 'autoFixRelocateBlocker'])->name('timetable.auto-fix.relocate-blocker');
    // Chain repair (Phase 4): preview is read-only (viewAny-gated, like
    // check-conflicts); apply re-validates and is admin-only (autoFix ability).
    Route::get('/admin/timetable/auto-fix/preview-chain', [\App\Http\Controllers\Admin\TimetableController::class, 'autoFixPreviewChain'])->name('timetable.auto-fix.preview-chain');
    Route::post('/admin/timetable/auto-fix/apply-chain', [\App\Http\Controllers\Admin\TimetableController::class, 'autoFixApplyChain'])->name('timetable.auto-fix.apply-chain');
    // Rebalancing Engine: preview is read-only (viewAny-gated, like every
    // other preview above); apply re-validates live data and is
    // admin-only (autoFix ability), same reasoning as chain repair's apply.
    Route::get('/admin/timetable/rebalance/preview', [\App\Http\Controllers\Admin\TimetableController::class, 'rebalancePreview'])->name('timetable.rebalance.preview');
    Route::post('/admin/timetable/rebalance/apply', [\App\Http\Controllers\Admin\TimetableController::class, 'rebalanceApply'])->name('timetable.rebalance.apply');
    // Swap Engine: preview is read-only (viewAny-gated, like check-conflicts);
    // the actual swap re-validates and authorizes both slots individually.
    Route::get('/admin/timetable/swap-preview', [\App\Http\Controllers\Admin\TimetableController::class, 'swapPreviewApi'])->name('timetable.swap-preview');
    Route::post('/admin/timetable/swap', [\App\Http\Controllers\Admin\TimetableController::class, 'swapSlots'])->name('timetable.swap');
    Route::get('/admin/timetable/feasibility', [\App\Http\Controllers\Admin\TimetableController::class, 'feasibility'])->name('timetable.feasibility');
    Route::get('/admin/timetable/pdf/class', [\App\Http\Controllers\Admin\TimetableController::class, 'classPdf'])->name('timetable.pdf.class');
    Route::get('/admin/timetable/pdf/teacher', [\App\Http\Controllers\Admin\TimetableController::class, 'teacherPdf'])->name('timetable.pdf.teacher');
    Route::get('/admin/timetable/pdf/master', [\App\Http\Controllers\Admin\TimetableController::class, 'masterPdf'])->name('timetable.pdf.master');

    // Phase 5: Interactive Teacher/Room views (Class view already exists
    // above via timetable.index / the Review & Edit grid).
    Route::get('/admin/timetable/view/teacher', [\App\Http\Controllers\Admin\TimetableController::class, 'teacherView'])->name('timetable.view.teacher');
    Route::get('/admin/timetable/view/room', [\App\Http\Controllers\Admin\TimetableController::class, 'roomView'])->name('timetable.view.room');

    // Phase 5: Excel exports -- reuse the same grid data as the PDF exports above.
    Route::get('/admin/timetable/export/class', [\App\Http\Controllers\Admin\TimetableController::class, 'classExcelExport'])->name('timetable.export.class');
    Route::get('/admin/timetable/export/teacher', [\App\Http\Controllers\Admin\TimetableController::class, 'teacherExcelExport'])->name('timetable.export.teacher');
    Route::get('/admin/timetable/export/master', [\App\Http\Controllers\Admin\TimetableController::class, 'masterExcelExport'])->name('timetable.export.master');
    Route::get('/admin/timetable/export/room', [\App\Http\Controllers\Admin\TimetableController::class, 'roomExcelExport'])->name('timetable.export.room');

    // Auto-generation, draft/publish workflow (T4b)
    Route::get('/admin/timetable/generate', [\App\Http\Controllers\Admin\TimetableController::class, 'showGenerateForm'])->name('timetable.generate.form');
    Route::post('/admin/timetable/generate', [\App\Http\Controllers\Admin\TimetableController::class, 'generate'])->name('timetable.generate');
    Route::get('/admin/timetable/generations/{generation}/status', [\App\Http\Controllers\Admin\TimetableController::class, 'generationStatus'])->name('timetable.generation.status');
    Route::post('/admin/timetable/generations/{generation}/publish', [\App\Http\Controllers\Admin\TimetableController::class, 'publishGeneration'])->name('timetable.generation.publish');
    Route::post('/admin/timetable/generations/{generation}/discard', [\App\Http\Controllers\Admin\TimetableController::class, 'discardGeneration'])->name('timetable.generation.discard');
    Route::get('/admin/timetable/generations/{generation}', [\App\Http\Controllers\Admin\TimetableController::class, 'generationReview'])->name('timetable.generation.review');

    // Guided setup wizard (T6 item 5, revised for per-section class
    // teachers) -- Steps 1-3 orchestrate existing tables/services; Step 4
    // (Generate & Publish) IS the existing generate/review/publish flow
    // above, no separate route needed.
    Route::get('/admin/timetable/wizard', [\App\Http\Controllers\Admin\TimetableWizardController::class, 'index'])->name('timetable.wizard.index');
    Route::get('/admin/timetable/wizard/subjects/{class}/{section?}', [\App\Http\Controllers\Admin\TimetableWizardController::class, 'step1'])->name('timetable.wizard.step1');
    Route::post('/admin/timetable/wizard/subjects/{class}/{section?}', [\App\Http\Controllers\Admin\TimetableWizardController::class, 'step1Store'])->name('timetable.wizard.step1.store');
    Route::get('/admin/timetable/wizard/style', [\App\Http\Controllers\Admin\TimetableWizardController::class, 'step2'])->name('timetable.wizard.step2');
    Route::get('/admin/timetable/wizard/readiness', [\App\Http\Controllers\Admin\TimetableWizardController::class, 'step3'])->name('timetable.wizard.step3');

    // Combined classes (T2b)
    Route::post('/admin/timetable/combined', [\App\Http\Controllers\Admin\TimetableController::class, 'storeCombined'])->name('timetable.combined.store');
    Route::get('/admin/combined-class-groups', [\App\Http\Controllers\Admin\CombinedClassGroupController::class, 'index'])->name('combined-class-groups.index');
    Route::get('/admin/combined-class-groups/create', [\App\Http\Controllers\Admin\CombinedClassGroupController::class, 'create'])->name('combined-class-groups.create');
    Route::post('/admin/combined-class-groups', [\App\Http\Controllers\Admin\CombinedClassGroupController::class, 'store'])->name('combined-class-groups.store');
    Route::delete('/admin/combined-class-groups/{combinedClassGroup}', [\App\Http\Controllers\Admin\CombinedClassGroupController::class, 'destroy'])->name('combined-class-groups.destroy');

    // Teacher availability grid (T2a)
    Route::get('/admin/teacher-availability', [\App\Http\Controllers\Admin\TeacherAvailabilityController::class, 'index'])->name('teacher-availability.index');
    Route::get('/admin/teacher-availability/{teacher}', [\App\Http\Controllers\Admin\TeacherAvailabilityController::class, 'edit'])->name('teacher-availability.edit');
    Route::post('/admin/teacher-availability/{teacher}', [\App\Http\Controllers\Admin\TeacherAvailabilityController::class, 'update'])->name('teacher-availability.update');

    // Library circulations & OPAC
    Route::get('/admin/library', [\App\Http\Controllers\Admin\LibraryController::class, 'index'])->name('library.index');
    Route::post('/admin/library/issue', [\App\Http\Controllers\Admin\LibraryController::class, 'issueBook'])->name('library.issue');
    Route::post('/admin/library/return/{id}', [\App\Http\Controllers\Admin\LibraryController::class, 'returnBook'])->name('library.return-book');
    Route::post('/admin/library/settings', [\App\Http\Controllers\Admin\LibraryController::class, 'updateSettings'])->name('library.settings');
    Route::get('/admin/library/opac', [\App\Http\Controllers\Admin\LibraryController::class, 'opac'])->name('library.opac');

    // Hostel allocations
    Route::get('/admin/hostels/dashboard', [\App\Http\Controllers\Admin\HostelController::class, 'index'])->name('hostel.dashboard');
    Route::post('/admin/hostels/allocate', [\App\Http\Controllers\Admin\HostelController::class, 'allocateBed'])->name('hostel.allocate');
    Route::post('/admin/hostels/vacate/{id}', [\App\Http\Controllers\Admin\HostelController::class, 'vacateBed'])->name('hostel.vacate');

    // Visitor Gate log
    Route::get('/admin/visitor/log', [\App\Http\Controllers\Admin\VisitorController::class, 'index'])->name('visitor.log');
    Route::post('/admin/visitor/checkin', [\App\Http\Controllers\Admin\VisitorController::class, 'checkIn'])->name('visitor.checkin');
    Route::post('/admin/visitor/checkout/{id}', [\App\Http\Controllers\Admin\VisitorController::class, 'checkOut'])->name('visitor.checkout');

    // Phase 7: Academic Assessment & Exams
    Route::get('/admin/exams/{examId}/blueprints', [\App\Http\Controllers\Admin\ExamBlueprintController::class, 'index'])->name('admin.exams.blueprints.index');
    Route::post('/admin/exams/{examId}/blueprints', [\App\Http\Controllers\Admin\ExamBlueprintController::class, 'store'])->name('admin.exams.blueprints.store');
    Route::delete('/admin/exams/blueprints/{id}', [\App\Http\Controllers\Admin\ExamBlueprintController::class, 'destroy'])->name('admin.exams.blueprints.destroy');
    
    Route::get('/admin/exams/moderation/index', [\App\Http\Controllers\Admin\MarksModerationController::class, 'index'])->name('admin.exams.moderation.index');
    Route::post('/admin/exams/moderation/moderate', [\App\Http\Controllers\Admin\MarksModerationController::class, 'moderate'])->name('admin.exams.moderation.moderate');
    Route::post('/admin/exams/moderation/grace', [\App\Http\Controllers\Admin\MarksModerationController::class, 'applyGrace'])->name('admin.exams.moderation.grace');

    Route::get('/admin/exams/reports/designer', [\App\Http\Controllers\Admin\ReportCardDesignerController::class, 'index'])->name('admin.exams.reports.designer');
    Route::post('/admin/exams/reports/store-template', [\App\Http\Controllers\Admin\ReportCardDesignerController::class, 'storeTemplate'])->name('admin.exams.reports.store-template');
    Route::post('/admin/exams/reports/store-rule', [\App\Http\Controllers\Admin\ReportCardDesignerController::class, 'storePromotionRule'])->name('admin.exams.reports.store-rule');
    Route::post('/admin/exams/reports/check-promotion', [\App\Http\Controllers\Admin\ReportCardDesignerController::class, 'checkPromotion'])->name('admin.exams.reports.check-promotion');
});










