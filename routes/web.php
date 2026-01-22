<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TeacherExperienceController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BellTimingController;
use App\Http\Controllers\ExamPaperController;
use App\Http\Controllers\HomeController;
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
    
    // =======================
    // STUDENT ROUTES
    // =======================
    Route::resource('students', StudentController::class);
    
    // Student Dashboard
    Route::get('/students-dashboard', function () {
        $stats = Student::getStatistics();
        return view('students.dashboard', compact('stats'));
    })->name('students.dashboard');
    
    // Student CSV
    Route::get('/students/export/csv', [StudentController::class, 'exportCSV'])->name('students.export.csv');
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
    Route::resource('attendance', AttendanceController::class);
    Route::get('/attendance/reports', [AttendanceController::class, 'reports'])->name('attendance.reports');
    Route::get('/attendance/student/{studentId}/report', [AttendanceController::class, 'studentReport'])->name('attendance.student.report');
    Route::get('/attendance/bulk-mark', [AttendanceController::class, 'bulkMark'])->name('attendance.bulk');
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
    
    // =======================
    // BELL TIMING ROUTES
    // =======================
    Route::resource('bell-timing', BellTimingController::class);
    Route::get('/bell-timing/weekly', [BellTimingController::class, 'weeklyTimetable'])->name('bell-timing.weekly');
    Route::get('/bell-timing/daily', [BellTimingController::class, 'todaysSchedule'])->name('bell-timing.daily');
    Route::get('/bell-timing/current-period', [BellTimingController::class, 'currentPeriod'])->name('bell-timing.current-period');
    Route::match(['get', 'post'], '/bell-timing/bulk-create', [BellTimingController::class, 'bulkCreate'])->name('bell-timing.bulk-create');
    
    // =======================
    // EXAM PAPER ROUTES
    // =======================
    Route::resource('exam-papers', ExamPaperController::class);
    Route::get('/exam-papers/download/{examPaper}', [ExamPaperController::class, 'download'])->name('exam-papers.download');
    Route::get('/exam-papers/available', [ExamPaperController::class, 'availableForClass'])->name('exam-papers.available');
    Route::get('/exam-papers/search', [ExamPaperController::class, 'search'])->name('exam-papers.search');
    Route::get('/exam-papers/upcoming', [ExamPaperController::class, 'upcoming'])->name('exam-papers.upcoming');
    Route::patch('/exam-papers/{examPaper}/toggle-publish', [ExamPaperController::class, 'togglePublish'])->name('exam-papers.toggle-publish');
});
