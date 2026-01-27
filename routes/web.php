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
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\UserController;

Route::get('/', [App\Http\Controllers\HomeController::class, 'welcome']);

// Authentication Routes
Route::get('/login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
Route::post('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    
    // Profile Routes
    Route::get('/user/two-factor-authentication', function () {
        return redirect()->route('user.two-factor.qr-code');
    })->name('profile.two-factor-authentication');
    
    // User Routes
    Route::resource('users', UserController::class);
    
    // Student Routes
    Route::resource('students', StudentController::class);
    
    // Teacher Routes
    Route::resource('teachers', TeacherController::class);
    
    // Bell Timing Routes
    Route::resource('bell-timing', App\Http\Controllers\BellTimingController::class);
    Route::get('/bell-timing/weekly', [App\Http\Controllers\BellTimingController::class, 'weekly'])->name('bell-timing.weekly');
    Route::get('/bell-timing/daily', [App\Http\Controllers\BellTimingController::class, 'daily'])->name('bell-timing.daily');
    Route::get('/bell-timing/bulk-create', [App\Http\Controllers\BellTimingController::class, 'bulkCreate'])->name('bell-timing.bulk-create');
    Route::post('/bell-timing/bulk-create', [App\Http\Controllers\BellTimingController::class, 'processBulkCreate'])->name('bell-timing.bulk-create.process');
    Route::get('/bell-timing/print/{classSection?}/{academicYear?}', [App\Http\Controllers\BellTimingController::class, 'print'])->name('bell-timing.print');
    
    // Exam Paper Routes (User facing)
    Route::resource('exam-papers', App\Http\Controllers\ExamPaperController::class);
    Route::get('/exam-papers/available-for-class', [App\Http\Controllers\ExamPaperController::class, 'availableForClass'])->name('exam-papers.available-for-class');
    Route::get('/exam-papers/search', [App\Http\Controllers\ExamPaperController::class, 'search'])->name('exam-papers.search');
    Route::get('/exam-papers/upcoming', [App\Http\Controllers\ExamPaperController::class, 'upcoming'])->name('exam-papers.upcoming');
    Route::get('/exam-papers/{examPaper}/download', [App\Http\Controllers\ExamPaperController::class, 'download'])->name('exam-papers.download');
    Route::patch('/exam-papers/{examPaper}/toggle-publish', [App\Http\Controllers\ExamPaperController::class, 'togglePublish'])->name('exam-papers.toggle-publish');
    
    // Admin routes
    Route::prefix('admin')->name('admin.')->group(function () {
        // Exam Paper Routes (Admin facing)
        Route::resource('exam-papers', App\Http\Controllers\Admin\ExamPaperController::class);
        Route::get('exam-papers/available-for-class', [App\Http\Controllers\Admin\ExamPaperController::class, 'availableForClass'])->name('exam-papers.available-for-class');
        Route::get('exam-papers/search', [App\Http\Controllers\Admin\ExamPaperController::class, 'search'])->name('exam-papers.search');
        Route::get('exam-papers/upcoming', [App\Http\Controllers\Admin\ExamPaperController::class, 'upcoming'])->name('exam-papers.upcoming');
        Route::get('exam-papers/{examPaper}/download', [App\Http\Controllers\Admin\ExamPaperController::class, 'download'])->name('exam-papers.download');
        Route::patch('exam-papers/{examPaper}/toggle-publish', [App\Http\Controllers\Admin\ExamPaperController::class, 'togglePublish'])->name('exam-papers.toggle-publish');
        
        // Exam Management Routes
        Route::resource('exams', App\Http\Controllers\Admin\ExamController::class);
        
        // Result Management Routes
        Route::resource('results', App\Http\Controllers\Admin\ResultController::class);
        Route::get('results/{result}/download', [App\Http\Controllers\Admin\ResultController::class, 'download'])->name('results.download');
        Route::get('results/student/{studentId}', [App\Http\Controllers\Admin\ResultController::class, 'getStudentResults'])->name('results.student-results');
        
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
        
        // Section Management Routes
        Route::resource('sections', App\Http\Controllers\Admin\SectionController::class);
        
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
        Route::resource('teacher-substitutions', App\Http\Controllers\Admin\TeacherSubstitutionController::class);
        Route::get('teacher-substitutions/today', [App\Http\Controllers\Admin\TeacherSubstitutionController::class, 'today'])->name('teacher-substitutions.today');
        Route::get('teacher-substitutions/absence-overview', [App\Http\Controllers\Admin\TeacherSubstitutionController::class, 'absenceOverview'])->name('teacher-substitutions.absence-overview');
        Route::get('teacher-substitutions/rules', [App\Http\Controllers\Admin\TeacherSubstitutionController::class, 'rules'])->name('teacher-substitutions.rules');
        Route::post('teacher-substitutions/rules', [App\Http\Controllers\Admin\TeacherSubstitutionController::class, 'updateRules'])->name('teacher-substitutions.update-rules');
        
        // Class Teacher Assignment Management Routes
        Route::resource('class-teacher-assignments', App\Http\Controllers\ClassTeacherAssignmentController::class);
        Route::get('class-teacher-assignments/teacher/{teacherId}/classes', [App\Http\Controllers\ClassTeacherAssignmentController::class, 'getTeacherClasses'])->name('class-teacher-assignments.teacher-classes');
        Route::get('class-teacher-assignments/student/{studentId}/class-teacher', [App\Http\Controllers\ClassTeacherAssignmentController::class, 'getStudentClassTeacher'])->name('class-teacher-assignments.student-class-teacher');
        
        // Inventory Management Routes
        Route::get('inventory', [App\Http\Controllers\Admin\InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/dashboard', [App\Http\Controllers\Admin\InventoryController::class, 'index'])->name('inventory.dashboard');
        Route::get('inventory/asset-master', [App\Http\Controllers\Admin\InventoryController::class, 'assetMaster'])->name('inventory.asset-master');
        Route::get('inventory/furniture', [App\Http\Controllers\Admin\InventoryController::class, 'furnitureManagement'])->name('inventory.furniture');
        Route::get('inventory/lab-equipment', [App\Http\Controllers\Admin\InventoryController::class, 'labEquipmentManagement'])->name('inventory.lab-equipment');
        Route::get('inventory/electronics', [App\Http\Controllers\Admin\InventoryController::class, 'electronicsManagement'])->name('inventory.electronics');
        Route::get('inventory/reports', [App\Http\Controllers\Admin\InventoryController::class, 'reports'])->name('inventory.reports');
        Route::get('inventory/audit-logs', [App\Http\Controllers\Admin\InventoryController::class, 'auditLogs'])->name('inventory.audit-logs');
        
        // Asset Management Routes
        Route::resource('inventory/assets', App\Http\Controllers\Admin\AssetController::class);
        Route::put('inventory/assets/{asset}/issue', [App\Http\Controllers\Admin\AssetController::class, 'issue'])->name('inventory.assets.issue');
        Route::put('inventory/assets/{asset}/return', [App\Http\Controllers\Admin\AssetController::class, 'return'])->name('inventory.assets.return');
        
        // Asset Category Management Routes
        Route::resource('inventory/categories', App\Http\Controllers\Admin\AssetCategoryController::class);
        
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
        Route::post('reports/generate', [App\Http\Controllers\Admin\ReportController::class, 'generate'])->name('reports.generate');
        Route::get('reports/templates', [App\Http\Controllers\Admin\ReportController::class, 'templates'])->name('reports.templates');
        
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
        
        Route::resource('certificate-templates', App\Http\Controllers\Admin\CertificateTemplateController::class);
        Route::post('certificate-templates/{certificateTemplate}/set-default', [App\Http\Controllers\Admin\CertificateTemplateController::class, 'setDefault'])->name('certificate-templates.set-default');
        
        // Backup Management Routes
        Route::resource('backups', App\Http\Controllers\Admin\BackupController::class);
        Route::get('backups/{backup}/download', [App\Http\Controllers\Admin\BackupController::class, 'download'])->name('backups.download');
        Route::post('backups/schedule', [App\Http\Controllers\Admin\BackupController::class, 'schedule'])->name('backups.schedule');
        
        // Daily Teaching Work Routes
        Route::resource('daily-teaching-work', App\Http\Controllers\Admin\DailyTeachingWorkController::class);
        
        // Syllabi Routes
        Route::resource('syllabi', App\Http\Controllers\Admin\SyllabusController::class);
        Route::get('syllabi/progress-report', [App\Http\Controllers\Admin\SyllabusController::class, 'progressReport'])->name('syllabi.progress-report');
        
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
        Route::get('class-teacher-control/student-records', [App\Http\Controllers\ClassTeacherAssignmentController::class, 'studentRecords'])->name('class-teacher-control.student-records');
    });

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
    
    // Attendance Routes
    Route::resource('attendance', AttendanceController::class);
    Route::get('/attendance/reports', [AttendanceController::class, 'reports'])->name('attendance.reports');
    Route::get('/attendance/bulk-mark', [AttendanceController::class, 'bulkMark'])->name('attendance.bulk-mark');
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
    Route::get('/attendance/student/{studentId}/report', [AttendanceController::class, 'studentReport'])->name('attendance.student-report');
});