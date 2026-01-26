<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\BellScheduleController;
use App\Http\Controllers\SpecialDayOverrideController;
use App\Http\Controllers\ClassTeacherAssignmentController;
use App\Http\Controllers\FieldPermissionController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AttendanceController;

Route::get('/', function () {
    return view('welcome');
});

// Authentication Routes
Route::get('/login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
Route::post('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    
    // Student Routes
    Route::resource('students', StudentController::class);
    
    // Teacher Routes
    Route::resource('teachers', TeacherController::class);
    
    // Bell Schedule Routes
    Route::resource('bell-schedules', BellScheduleController::class);
    Route::get('/bell-schedules/current', [BellScheduleController::class, 'getCurrentSchedule'])->name('bell-schedules.current');
    Route::get('/bell-schedules/live-monitor', [BellScheduleController::class, 'liveMonitor'])->name('bell-schedules.live-monitor');
    
    // Special Day Override Routes
    Route::resource('special-day-overrides', SpecialDayOverrideController::class);
    
    // Class Teacher Assignment Routes
    Route::resource('class-teacher-assignments', ClassTeacherAssignmentController::class);
    
    // Field Permission Routes
    Route::resource('field-permissions', FieldPermissionController::class);
    
    // Audit Log Routes
    Route::resource('audit-logs', AuditLogController::class);
    Route::get('/audit-logs/model/{modelType}/{modelId}', [AuditLogController::class, 'getModelLogs'])->name('audit-logs.model-logs');
    
    // Attendance Routes
    Route::resource('attendance', AttendanceController::class);
    Route::get('/attendance/reports', [AttendanceController::class, 'reports'])->name('attendance.reports');
    Route::get('/attendance/bulk-mark', [AttendanceController::class, 'bulkMark'])->name('attendance.bulk-mark');
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
    Route::get('/attendance/student/{studentId}/report', [AttendanceController::class, 'studentReport'])->name('attendance.student-report');
});