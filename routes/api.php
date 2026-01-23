<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\StudentController;
use App\Http\Controllers\API\TeacherController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\ExamPaperController;
use App\Http\Controllers\API\BellTimingController;

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
Route::prefix('v1')->group(function () {
    // Public routes
    Route::get('/exam-papers/available/{classSection}', [ExamPaperController::class, 'availableForClass'])->name('api.exam-papers.available-for-class')->middleware('throttle:10,1');
    Route::post('/exam-papers/search', [ExamPaperController::class, 'search'])->name('api.exam-papers.search')->middleware('throttle:10,1');
    Route::get('/bell-timing/today/{classSection}', [BellTimingController::class, 'todaysSchedule'])->name('api.bell-timing.today')->middleware('throttle:10,1');
    
    // Protected routes
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        // Student routes
        Route::apiResource('students', StudentController::class);
        Route::get('/students/{id}/attendance', [StudentController::class, 'attendance'])->name('api.students.attendance');
        Route::get('/students/{id}/results', [StudentController::class, 'results'])->name('api.students.results');
        Route::get('/students/{id}/fees', [StudentController::class, 'fees'])->name('api.students.fees');
        
        // Teacher routes
        Route::apiResource('teachers', TeacherController::class);
        Route::get('/teachers/{id}/classes', [TeacherController::class, 'classes'])->name('api.teachers.classes');
        Route::get('/teachers/{id}/papers', [TeacherController::class, 'examPapers'])->name('api.teachers.papers');
        
        // Attendance routes
        Route::apiResource('attendance', AttendanceController::class);
        Route::get('/attendance/student/{studentId}/monthly/{month}/{year}', [AttendanceController::class, 'studentMonthlyReport'])->name('api.attendance.student-monthly');
        Route::get('/attendance/class/{classSection}/daily/{date}', [AttendanceController::class, 'dailyReport'])->name('api.attendance.daily-report');
        Route::post('/attendance/bulk-mark', [AttendanceController::class, 'bulkMark'])->name('api.attendance.bulk-mark');
        
        // Exam paper routes
        Route::apiResource('exam-papers', ExamPaperController::class);
        Route::post('/exam-papers/{id}/download', [ExamPaperController::class, 'download'])->name('api.exam-papers.download');
        Route::post('/exam-papers/{id}/toggle-publish', [ExamPaperController::class, 'togglePublish'])->name('api.exam-papers.toggle-publish');
        
        // Bell timing routes
        Route::apiResource('bell-timing', BellTimingController::class);
        Route::get('/bell-timing/weekly/{classSection}', [BellTimingController::class, 'weeklyTimetable'])->name('api.bell-timing.weekly');
        Route::get('/bell-timing/current-period', [BellTimingController::class, 'currentPeriod'])->name('api.bell-timing.current-period');
        Route::post('/bell-timing/bulk-create', [BellTimingController::class, 'bulkCreate'])->name('api.bell-timing.bulk-create');
        
        // Notification routes
        Route::get('/notifications', [App\Http\Controllers\Api\NotificationController::class, 'index'])->name('api.notifications.index');
        Route::put('/notifications/{id}/read', [App\Http\Controllers\Api\NotificationController::class, 'markAsRead'])->name('api.notifications.mark-as-read');
        Route::put('/notifications/mark-all-read', [App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead'])->name('api.notifications.mark-all-read');
        Route::get('/notifications/unread-count', [App\Http\Controllers\Api\NotificationController::class, 'unreadCount'])->name('api.notifications.unread-count');
    });
});

// Version 2 API routes (if needed in future)
// Route::prefix('v2')->group(function () {
//     // Future API version routes
// });