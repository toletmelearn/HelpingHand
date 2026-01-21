<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TeacherExperienceController;
use App\Http\Controllers\AttendanceController;
use App\Models\Student;
use App\Models\Teacher;

// Home Page
Route::get('/', function () {
    return view('welcome');
});

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
