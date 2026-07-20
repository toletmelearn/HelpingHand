<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\StudentController;
use App\Http\Controllers\API\TeacherController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\ExamPaperController;
use App\Http\Controllers\API\BellTimingController;
use App\Http\Controllers\API\GuardianController;
use App\Http\Controllers\API\LessonPlanController;
use App\Http\Controllers\API\NotificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Version 1 API routes
Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public routes - Authentication
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    
    // Public routes - Guest access
    Route::get('/exam-papers/available/{classSection}', [ExamPaperController::class, 'availableForClass'])->name('exam-papers.available-for-class')->middleware(['throttle:10,1', \App\Http\Middleware\ApiAccessControl::class]);
    Route::post('/exam-papers/search', [ExamPaperController::class, 'search'])->name('exam-papers.search')->middleware(['throttle:10,1', \App\Http\Middleware\ApiAccessControl::class]);
    Route::get('/bell-timing/today/{classSection}', [BellTimingController::class, 'todaysSchedule'])->name('bell-timing.today')->middleware(['throttle:10,1', \App\Http\Middleware\ApiAccessControl::class]);
    
    // Protected routes
    Route::middleware(['auth:sanctum', 'throttle:60,1', \App\Http\Middleware\ApiAccessControl::class])->group(function () {
        // Authentication routes
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::put('/profile', [AuthController::class, 'updateProfile'])->name('update-profile');
        Route::post('/change-password', [AuthController::class, 'changePassword'])->name('change-password');
        Route::post('/refresh-token', [AuthController::class, 'refreshToken'])->name('refresh-token');
        
        // Dashboard routes
        Route::get('/dashboard/student', [DashboardController::class, 'studentDashboard'])->name('dashboard.student');
        Route::get('/dashboard/parent', [DashboardController::class, 'parentDashboard'])->name('dashboard.parent');
        Route::get('/dashboard/teacher', [DashboardController::class, 'teacherDashboard'])->name('dashboard.teacher');
        
        // Lesson Plan routes
        Route::get('/lesson-plans', [LessonPlanController::class, 'index'])->name('lesson-plans.index');
        Route::get('/lesson-plans/today', [LessonPlanController::class, 'todayLessons'])->name('lesson-plans.today');
        Route::get('/lesson-plans/week', [LessonPlanController::class, 'weekLessons'])->name('lesson-plans.week');
        Route::get('/lesson-plans/my', [LessonPlanController::class, 'myLessonPlans'])->name('lesson-plans.my');
        Route::post('/lesson-plans', [LessonPlanController::class, 'store'])->name('lesson-plans.store');
        Route::get('/lesson-plans/{id}', [LessonPlanController::class, 'show'])->name('lesson-plans.show');
        Route::put('/lesson-plans/{id}', [LessonPlanController::class, 'update'])->name('lesson-plans.update');
        
        // Student routes
        Route::apiResource('students', StudentController::class);
        Route::get('/students/{id}/attendance', [StudentController::class, 'attendance'])->name('students.attendance');
        Route::get('/students/{id}/results', [StudentController::class, 'results'])->name('students.results');
        Route::get('/students/{id}/fees', [StudentController::class, 'fees'])->name('students.fees');
        
        // Teacher routes
        Route::apiResource('teachers', TeacherController::class);
        Route::get('/teachers/{id}/classes', [TeacherController::class, 'classes'])->name('teachers.classes');
        Route::get('/teachers/{id}/papers', [TeacherController::class, 'examPapers'])->name('teachers.papers');
        Route::get('/teachers/{id}/subject-classes', [TeacherController::class, 'subjectClasses'])->name('teachers.subject-classes');
        Route::get('/teachers/{id}/attendance-data', [TeacherController::class, 'attendanceData'])->name('teachers.attendance-data');
        Route::get('/teachers/{id}/grading-data', [TeacherController::class, 'gradingData'])->name('teachers.grading-data');
        
        // Attendance routes
        Route::apiResource('attendance', AttendanceController::class);
        Route::get('/attendance/student/{studentId}/monthly/{month}/{year}', [AttendanceController::class, 'studentMonthlyReport'])->name('attendance.student-monthly');
        Route::get('/attendance/class/{classSection}/daily/{date}', [AttendanceController::class, 'dailyReport'])->name('attendance.daily-report');
        Route::post('/attendance/bulk-mark', [AttendanceController::class, 'bulkMark'])->name('attendance.bulk-mark');
        
        // Exam paper routes
        Route::apiResource('exam-papers', ExamPaperController::class);
        Route::post('/exam-papers/{id}/download', [ExamPaperController::class, 'download'])->name('exam-papers.download');
        Route::post('/exam-papers/{id}/toggle-publish', [ExamPaperController::class, 'togglePublish'])->name('exam-papers.toggle-publish');
        
        // Bell timing routes
        Route::apiResource('bell-timing', BellTimingController::class);
        Route::get('/bell-timing/weekly/{classSection}', [BellTimingController::class, 'weeklyTimetable'])->name('bell-timing.weekly');
        Route::get('/bell-timing/current-period', [BellTimingController::class, 'currentPeriod'])->name('bell-timing.current-period');
        Route::post('/bell-timing/bulk-create', [BellTimingController::class, 'bulkCreate'])->name('bell-timing.bulk-create');
        
        // Notification routes
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
        Route::put('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        
        // Guardian routes (for parent dashboard)
        Route::apiResource('guardians', GuardianController::class);
        Route::get('/guardians/{id}/children', [GuardianController::class, 'children'])->name('guardians.children');
        Route::get('/guardians/{id}/notifications', [GuardianController::class, 'notifications'])->name('guardians.notifications');
    });
});

// Version 2 API routes (if needed in future)
// Route::prefix('v2')->group(function () {
//     // Future API version routes
// });