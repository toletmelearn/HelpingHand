<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TeacherExperienceController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BellTimingController;
use App\Http\Controllers\ExamPaperController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ClassManagementController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\AcademicSessionController;
use App\Models\Student;
use App\Models\Teacher;

// Public routes
Route::get('/', function () {
    $stats = [
        'students' => App\Models\Student::count(),
        'teachers' => App\Models\Teacher::count(),
        'attendance' => App\Models\Attendance::count(),
        'bell_timing' => App\Models\BellTiming::count(),
        'exam_papers' => App\Models\ExamPaper::count()
    ];
    return view('welcome', compact('stats'));
});

// Authentication routes
Route::get('/login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
Route::post('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

// Test route to check if all features are accessible
Route::get('/test-all-features', function() {
    return response()->json([
        'students' => App\Models\Student::count(),
        'teachers' => App\Models\Teacher::count(),
        'attendance' => App\Models\Attendance::count(),
        'bell_timing' => App\Models\BellTiming::count(),
        'exam_papers' => App\Models\ExamPaper::count(),
        'status' => 'All models loaded successfully'
    ]);
});

// Protected routes - require authentication
Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
    
    // Role-specific dashboards
    Route::get('/admin/dashboard', function () {
        return view('admin-dashboard');
    })->name('admin.dashboard');
    
    Route::get('/parent/dashboard', function () {
        return view('parent-dashboard');
    })->name('parent.dashboard');
    
    // Parent Portal routes
    Route::prefix('parents')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\ParentController::class, 'index'])->name('parents.dashboard');
        Route::get('/child/{id}', [App\Http\Controllers\ParentController::class, 'viewChild'])->name('parents.child.details');
        Route::get('/child/{id}/attendance', [App\Http\Controllers\ParentController::class, 'getChildAttendance'])->name('parents.child.attendance');
        Route::get('/child/{id}/results', [App\Http\Controllers\ParentController::class, 'getChildResults'])->name('parents.child.results');
        Route::get('/child/{id}/fees', [App\Http\Controllers\ParentController::class, 'getChildFees'])->name('parents.child.fees');
    });
    
    // Profile and Settings routes
    Route::get('/profile/two-factor-authentication', function () {
        return view('profile.two-factor-authentication');
    })->name('profile.two-factor-authentication');
    
    // Notification routes
    Route::get('/notifications', [App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::put('/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
    Route::put('/notifications/mark-all-read', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    
    // =======================
    // STUDENT ROUTES
    // =======================
    Route::resource('students', StudentController::class);
    
    // Student Dashboard
    Route::get('/students-dashboard', function () {
        $stats = Student::getStatistics();
        return view('students.dashboard', compact('stats'));
    })->name('students.dashboard');
    
    // Student CSV/Excel
    Route::get('/students/export/csv', [StudentController::class, 'exportCSV'])->name('students.export.csv');
    Route::get('/students/export/excel', [StudentController::class, 'exportExcel'])->name('students.export.excel');
    Route::post('/students/import/csv', [StudentController::class, 'importCSV'])->name('students.import.csv');
    
    // =======================
    // TEACHER ROUTES
    // =======================
    
    // IMPORTANT: custom routes FIRST
    Route::get('/teachers/experience', [TeacherExperienceController::class, 'index'])->name('teachers.experience');
    Route::get('/teachers/{teacher}/experience-certificate', [TeacherExperienceController::class, 'generateExperienceCertificate'])
        ->name('teachers.experience.certificate');
    
    // Resource routes LAST
    Route::resource('teachers', TeacherController::class);
    
    // Teacher Dashboard
    Route::get('/teachers-dashboard', function () {
        $stats = Teacher::getStatistics();
        return view('teachers.dashboard', compact('stats'));
    })->name('teachers.dashboard');
    
    // =======================
    // ATTENDANCE ROUTES
    // =======================
    // IMPORTANT: Custom routes FIRST to avoid conflicts with resource routes
    Route::get('/attendance/reports', [AttendanceController::class, 'reports'])->name('attendance.reports');
    Route::get('/attendance/student/{studentId}/report', [AttendanceController::class, 'studentReport'])->name('attendance.student.report');
    Route::get('/attendance/bulk-mark', [AttendanceController::class, 'bulkMark'])->name('attendance.bulk');
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
    
    // Resource routes LAST
    Route::resource('attendance', AttendanceController::class);
    
    // =======================
    // BELL TIMING ROUTES
    // =======================
    // IMPORTANT: Custom routes FIRST to avoid conflicts
    Route::get('/bell-timing/weekly', [BellTimingController::class, 'weeklyTimetable'])->name('bell-timing.weekly');
    Route::get('/bell-timing/daily', [BellTimingController::class, 'todaysSchedule'])->name('bell-timing.daily');
    Route::get('/bell-timing/current-period', [BellTimingController::class, 'currentPeriod'])->name('bell-timing.current-period');
    Route::get('/bell-timing/print', [BellTimingController::class, 'printTimetable'])->name('bell-timing.print');
    Route::match(['get', 'post'], '/bell-timing/bulk-create', [BellTimingController::class, 'bulkCreate'])->name('bell-timing.bulk-create');
    
    // Resource routes LAST
    Route::resource('bell-timing', BellTimingController::class);
    
    // =======================
    // EXAM PAPER ROUTES
    // =======================
    // IMPORTANT: Custom routes FIRST to avoid conflicts
    Route::get('/exam-papers/download/{examPaper}', [ExamPaperController::class, 'download'])->name('exam-papers.download');
    Route::get('/exam-papers/available', [ExamPaperController::class, 'availableForClass'])->name('exam-papers.available');
    Route::get('/exam-papers/search', [ExamPaperController::class, 'search'])->name('exam-papers.search');
    Route::get('/exam-papers/upcoming', [ExamPaperController::class, 'upcoming'])->name('exam-papers.upcoming');
    Route::patch('/exam-papers/{examPaper}/toggle-publish', [ExamPaperController::class, 'togglePublish'])->name('exam-papers.toggle-publish');
    
    // Resource routes LAST
    Route::resource('exam-papers', ExamPaperController::class);
    
    // =======================
    // USER MANAGEMENT ROUTES
    // =======================
    Route::resource('users', UserController::class);
    
    // ======================
    // STUDENT VERIFICATION ROUTES
    // ======================
    Route::prefix('admin/students')->name('admin.students.')->group(function () {
        Route::get('/verify', [StudentVerificationController::class, 'index'])->name('verify.index');
        Route::get('/verify/{student}', [StudentVerificationController::class, 'show'])->name('verify.show');
        Route::post('/verify/{student}/upload', [StudentVerificationController::class, 'uploadDocuments'])->name('verify.upload');
        Route::post('/verify/{student}/mark-verified', [StudentVerificationController::class, 'markAsVerified'])->name('verify.mark-verified');
        Route::post('/verify/document/{document}', [StudentVerificationController::class, 'verifyDocument'])->name('verify.document');
    });
    
    // =======================
    // ACADEMIC MANAGEMENT ROUTES
    // =======================
    // IMPORTANT: Custom routes FIRST to avoid conflicts
    Route::get('/academic-sessions/{academicSession}/set-current', [AcademicSessionController::class, 'setCurrent'])
        ->name('academic-sessions.set-current');
    
    // Resource routes for academic sessions
    Route::resource('academic-sessions', AcademicSessionController::class);
    
    // Resource routes for class management
    Route::resource('classes', ClassManagementController::class);
    
    // Resource routes for sections
    Route::resource('sections', SectionController::class);
    
    // Resource routes for subjects
    Route::resource('subjects', SubjectController::class);
    
    // =======================
    // ADMIN CONFIGURATION ROUTES
    // =======================
    Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
        Route::resource('sections', App\Http\Controllers\Admin\SectionController::class);
        Route::post('/sections/{id}/restore', [App\Http\Controllers\Admin\SectionController::class, 'restore'])->name('sections.restore');
    });
    
    Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
        Route::resource('subjects', App\Http\Controllers\Admin\SubjectController::class);
        Route::post('/subjects/{id}/restore', [App\Http\Controllers\Admin\SubjectController::class, 'restore'])->name('subjects.restore');
    });
    
    Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
        Route::resource('academic-sessions', App\Http\Controllers\Admin\AcademicSessionController::class);
        Route::post('/academic-sessions/{id}/restore', [App\Http\Controllers\Admin\AcademicSessionController::class, 'restore'])->name('academic-sessions.restore');
    });
    
    Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
        Route::resource('school-classes', App\Http\Controllers\Admin\SchoolClassController::class);
        Route::post('/school-classes/{id}/restore', [App\Http\Controllers\Admin\SchoolClassController::class, 'restore'])->name('school-classes.restore');
    });
    
    Route::prefix('admin/classes')->name('admin.classes.')->middleware(['auth'])->group(function () {
        Route::get('/{class}/assign-sections', [App\Http\Controllers\Admin\ClassAssignmentController::class, 'assignSections'])->name('assign-sections');
        Route::put('/{class}/assign-sections', [App\Http\Controllers\Admin\ClassAssignmentController::class, 'saveSectionAssignment'])->name('save-section-assignment');
        Route::get('/{class}/assign-subjects', [App\Http\Controllers\Admin\ClassAssignmentController::class, 'assignSubjects'])->name('assign-subjects');
        Route::put('/{class}/assign-subjects', [App\Http\Controllers\Admin\ClassAssignmentController::class, 'saveSubjectAssignment'])->name('save-subject-assignment');
        Route::get('/{class}/assign-class-teacher', [App\Http\Controllers\Admin\ClassAssignmentController::class, 'assignClassTeacher'])->name('assign-class-teacher');
        Route::put('/{class}/assign-class-teacher', [App\Http\Controllers\Admin\ClassAssignmentController::class, 'saveClassTeacherAssignment'])->name('save-class-teacher-assignment');
        Route::get('/assign-subject-teachers', [App\Http\Controllers\Admin\ClassAssignmentController::class, 'assignSubjectTeachers'])->name('assign-subject-teachers');
        Route::put('/assign-subject-teachers', [App\Http\Controllers\Admin\ClassAssignmentController::class, 'saveSubjectTeacherAssignment'])->name('save-subject-teacher-assignment');
    });
    
    // Admin Exam and Result Management Routes
    Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
        Route::resource('exams', App\Http\Controllers\Admin\ExamController::class);
        Route::resource('results', App\Http\Controllers\Admin\ResultController::class);
    });
    
    // Teacher Result Management Routes
    Route::prefix('teacher/results')->name('teacher.results.')->middleware(['auth'])->group(function () {
        Route::get('/', [App\Http\Controllers\TeacherResultController::class, 'index'])->name('index');
        Route::get('/enter-marks/{exam}', [App\Http\Controllers\TeacherResultController::class, 'enterMarks'])->name('enter-marks');
        Route::post('/save-marks/{exam}', [App\Http\Controllers\TeacherResultController::class, 'saveMarks'])->name('save-marks');
        Route::get('/my-results', [App\Http\Controllers\TeacherResultController::class, 'showResults'])->name('show-results');
    });
    
    // Student Result Management Routes
    Route::prefix('student/results')->name('student.results.')->middleware(['auth'])->group(function () {
        Route::get('/', [App\Http\Controllers\StudentResultController::class, 'index'])->name('index');
        Route::get('/{result}', [App\Http\Controllers\StudentResultController::class, 'show'])->name('show');
        Route::get('/{result}/pdf', [App\Http\Controllers\StudentResultController::class, 'generatePDF'])->name('generate-pdf');
    });
    
    // Admin Fee Management Routes
    Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
        Route::resource('fees', App\Http\Controllers\Admin\FeeController::class);
        Route::resource('fee-structures', App\Http\Controllers\Admin\FeeStructureController::class);
    });
    
    // Role & Permission Management Routes
    Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
        Route::get('role-permissions', [App\Http\Controllers\RolePermissionController::class, 'index'])->name('role-permissions.index');
        Route::get('role-permissions/{role}/edit', [App\Http\Controllers\RolePermissionController::class, 'edit'])->name('role-permissions.edit');
        Route::put('role-permissions/{role}', [App\Http\Controllers\RolePermissionController::class, 'update'])->name('role-permissions.update');
        Route::get('users/{user}/roles/edit', [App\Http\Controllers\RolePermissionController::class, 'editUserRoles'])->name('user-roles.edit');
        Route::put('users/{user}/roles', [App\Http\Controllers\RolePermissionController::class, 'updateUserRoles'])->name('user-roles.update');
    });
    
    // Admit Card Management Routes
    Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
        Route::resource('admit-card-formats', App\Http\Controllers\Admin\AdmitCardFormatController::class);
        Route::resource('admit-cards', App\Http\Controllers\Admin\AdmitCardController::class);
        Route::get('admit-cards/{admitCard}/publish', [App\Http\Controllers\Admin\AdmitCardController::class, 'publish'])->name('admit-cards.publish');
        Route::get('admit-cards/{admitCard}/lock', [App\Http\Controllers\Admin\AdmitCardController::class, 'lock'])->name('admit-cards.lock');
        Route::get('admit-cards/{admitCard}/preview', [App\Http\Controllers\Admin\AdmitCardController::class, 'preview'])->name('admit-cards.preview');
        Route::post('admit-cards/bulk-publish', [App\Http\Controllers\Admin\AdmitCardController::class, 'bulkPublish'])->name('admit-cards.bulk-publish');
        Route::post('admit-cards/bulk-lock', [App\Http\Controllers\Admin\AdmitCardController::class, 'bulkLock'])->name('admit-cards.bulk-lock');
        Route::get('admit-cards/{admitCard}/revoke', [App\Http\Controllers\Admin\AdmitCardController::class, 'revoke'])->name('admit-cards.revoke');
        Route::post('admit-cards/bulk-revoke', [App\Http\Controllers\Admin\AdmitCardController::class, 'bulkRevoke'])->name('admit-cards.bulk-revoke');
        
        // Exam Paper Management Routes
        Route::resource('exam-papers', App\Http\Controllers\Admin\ExamPaperController::class);
        Route::get('exam-papers/{examPaper}/submit', [App\Http\Controllers\Admin\ExamPaperController::class, 'submit'])->name('exam-papers.submit');
        Route::get('exam-papers/{examPaper}/approve', [App\Http\Controllers\Admin\ExamPaperController::class, 'approve'])->name('exam-papers.approve');
        Route::get('exam-papers/{examPaper}/lock', [App\Http\Controllers\Admin\ExamPaperController::class, 'lock'])->name('exam-papers.lock');
        Route::get('exam-papers/{examPaper}/download', [App\Http\Controllers\Admin\ExamPaperController::class, 'download'])->name('exam-papers.download');
        Route::get('exam-papers/{examPaper}/print', [App\Http\Controllers\Admin\ExamPaperController::class, 'print'])->name('exam-papers.print');
        Route::get('exam-papers/{examPaper}/clone', [App\Http\Controllers\Admin\ExamPaperController::class, 'clone'])->name('exam-papers.clone');
        Route::get('exam-papers/available', [App\Http\Controllers\Admin\ExamPaperController::class, 'available'])->name('exam-papers.available');
        Route::get('exam-papers/upcoming', [App\Http\Controllers\Admin\ExamPaperController::class, 'upcoming'])->name('exam-papers.upcoming');
        
        // Exam Paper Template Routes
        Route::resource('exam-paper-templates', App\Http\Controllers\Admin\ExamPaperTemplateController::class);
        Route::get('exam-paper-templates/{examPaperTemplate}/toggle-status', [App\Http\Controllers\Admin\ExamPaperTemplateController::class, 'toggleStatus'])->name('exam-paper-templates.toggle-status');
        Route::get('exam-paper-templates/{examPaperTemplate}/preview', [App\Http\Controllers\Admin\ExamPaperTemplateController::class, 'preview'])->name('exam-paper-templates.preview');
    });
    
    // Student Admit Card Routes
    Route::prefix('student/admit-cards')->name('student.admit-cards.')->middleware(['auth'])->group(function () {
        Route::get('/', [App\Http\Controllers\StudentAdmitCardController::class, 'index'])->name('index');
        Route::get('/{admitCard}', [App\Http\Controllers\StudentAdmitCardController::class, 'show'])->name('show');
        Route::get('/{admitCard}/download-pdf', [App\Http\Controllers\StudentAdmitCardController::class, 'downloadPdf'])->name('download-pdf');
    });
});