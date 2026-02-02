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
use App\Http\Controllers\Admin\AdminDashboardController;

Route::get('/', [App\Http\Controllers\HomeController::class, 'welcome']);

// Authentication Routes
Route::get('/login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
Route::post('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

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
    Route::get('exam-papers/available', [App\Http\Controllers\ExamPaperController::class, 'available'])->name('exam-papers.available');
    
    // Student Export/Import Routes
    Route::get('students/export/csv', [App\Http\Controllers\StudentController::class, 'exportCsv'])->name('students.export.csv');
    Route::post('students/import/csv', [App\Http\Controllers\StudentController::class, 'importCsv'])->name('students.import.csv');
    
    // Attendance Routes
    Route::get('attendance/student/{studentId}/report', [App\Http\Controllers\AttendanceController::class, 'studentReport'])->name('attendance.student.report');
    
    // Notifications Routes
    Route::get('notifications', [App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/mark-all-read', [App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    
    // Results Routes
    Route::get('results', [App\Http\Controllers\Admin\ResultController::class, 'index'])->name('results.index');
    
    // Fees Payment Routes
    Route::post('fees/payment', [App\Http\Controllers\Admin\FeeController::class, 'payment'])->name('fees.payment');
    
    // Teacher Biometric Dashboard
    Route::get('teachers/biometric/dashboard', [App\Http\Controllers\Teacher\BiometricController::class, 'dashboard'])->name('teachers.biometric.dashboard');
    
    // Student Admit Cards Routes
    Route::prefix('student')->name('student.')->group(function () {
        Route::get('/admit-cards', [App\Http\Controllers\StudentAdmitCardController::class, 'index'])->name('admit-cards.index');
        Route::get('/admit-cards/{admitCard}', [App\Http\Controllers\StudentAdmitCardController::class, 'show'])->name('admit-cards.show');
        Route::get('/admit-cards/{admitCard}/download-pdf', [App\Http\Controllers\StudentAdmitCardController::class, 'downloadPdf'])->name('admit-cards.download-pdf');
    });
    
    // Student Daily Teaching Work Routes
    Route::prefix('student')->name('student.')->group(function () {
        Route::get('/daily-teaching-work', [App\Http\Controllers\StudentDailyTeachingWorkController::class, 'index'])->name('daily-teaching-work.index');
        Route::get('/daily-teaching-work/{id}', [App\Http\Controllers\StudentDailyTeachingWorkController::class, 'show'])->name('daily-teaching-work.show');
    });
    
    // Student Results Routes
    Route::prefix('student')->name('student.')->group(function () {
        Route::get('/results', [App\Http\Controllers\StudentResultController::class, 'index'])->name('results.index');
        Route::get('/results/{result}', [App\Http\Controllers\StudentResultController::class, 'show'])->name('results.show');
        Route::get('/results/{result}/generate-pdf', [App\Http\Controllers\StudentResultController::class, 'generatePdf'])->name('results.generate-pdf');
    });
    
    // Parent Dashboard Routes
    Route::prefix('parent')->name('parent.')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\ParentController::class, 'index'])->name('dashboard');
        Route::get('/child/{childId}/details', [App\Http\Controllers\ParentController::class, 'viewChild'])->name('child.details');
    });
    
    // Additional Parent Routes
    Route::get('parents/dashboard', [App\Http\Controllers\ParentController::class, 'index'])->name('parents.dashboard');
    Route::get('parents/child/{childId}/details', [App\Http\Controllers\ParentController::class, 'viewChild'])->name('parents.child.details');
    

    

    
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
    Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified'])->group(function () {
        // Admin Dashboard Route
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        
        // Essential Admin Resource Routes
        Route::resource('students', StudentController::class);
        Route::resource('teachers', TeacherController::class);
        Route::get('teachers/bulk-upload', [App\Http\Controllers\Admin\TeacherBulkUploadController::class, 'create'])->name('teachers.bulk-upload');
        Route::post('teachers/bulk-upload', [App\Http\Controllers\Admin\TeacherBulkUploadController::class, 'store'])->name('teachers.bulk-upload.store');
        Route::get('teachers/bulk-upload/sample', [App\Http\Controllers\Admin\TeacherBulkUploadController::class, 'downloadSample'])->name('teachers.bulk-upload.sample');
        Route::resource('attendance', AttendanceController::class);
        Route::get('attendance/report/{id}', [AttendanceController::class, 'report'])->name('attendance.report');
        // Exam Paper Routes (Admin facing)
        Route::resource('exam-papers', App\Http\Controllers\Admin\ExamPaperController::class);
        Route::get('exam-papers/available-for-class', [App\Http\Controllers\Admin\ExamPaperController::class, 'availableForClass'])->name('exam-papers.available-for-class');
        Route::get('exam-papers/search', [App\Http\Controllers\Admin\ExamPaperController::class, 'search'])->name('exam-papers.search');
        Route::get('exam-papers/upcoming', [App\Http\Controllers\Admin\ExamPaperController::class, 'upcoming'])->name('exam-papers.upcoming');
        Route::get('exam-papers/{examPaper}/download', [App\Http\Controllers\Admin\ExamPaperController::class, 'download'])->name('exam-papers.download');
        Route::patch('exam-papers/{examPaper}/toggle-publish', [App\Http\Controllers\Admin\ExamPaperController::class, 'togglePublish'])->name('exam-papers.toggle-publish');
        
        // Additional Exam Paper Routes
        Route::post('exam-papers/{examPaper}/submit', [App\Http\Controllers\Admin\ExamPaperController::class, 'submit'])->name('admin.exam-papers.submit');
        Route::post('exam-papers/{examPaper}/approve', [App\Http\Controllers\Admin\ExamPaperController::class, 'approve'])->name('admin.exam-papers.approve');
        Route::post('exam-papers/{examPaper}/lock', [App\Http\Controllers\Admin\ExamPaperController::class, 'lock'])->name('admin.exam-papers.lock');
        Route::get('exam-papers/{examPaper}/print', [App\Http\Controllers\Admin\ExamPaperController::class, 'print'])->name('admin.exam-papers.print');
        Route::post('exam-papers/{examPaper}/clone', [App\Http\Controllers\Admin\ExamPaperController::class, 'clone'])->name('admin.exam-papers.clone');
        
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
        
        // Class Management Routes
        Route::resource('classes', App\Http\Controllers\Admin\ClassController::class);
        
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
        
        // Teacher Subject Assignment Management Routes
        Route::resource('teacher-subject-assignments', App\Http\Controllers\Admin\TeacherSubjectAssignmentController::class);
        
        // Teacher Class Assignment Management Routes
        Route::resource('teacher-class-assignments', App\Http\Controllers\Admin\TeacherClassAssignmentController::class);
        
        // Admin Configuration Routes
        Route::get('configurations', [App\Http\Controllers\Admin\AdminConfigurationController::class, 'index'])->name('admin.configurations.index');
        Route::post('configurations/update', [App\Http\Controllers\Admin\AdminConfigurationController::class, 'update'])->name('admin.configurations.update');
        Route::post('configurations/reset-defaults', [App\Http\Controllers\Admin\AdminConfigurationController::class, 'resetToDefaults'])->name('admin.configurations.reset-defaults');
        Route::post('configurations/{id}/toggle', [App\Http\Controllers\Admin\AdminConfigurationController::class, 'toggle'])->name('admin.configurations.toggle');
        
        // Student Promotion Management Routes
        Route::resource('student-promotions', App\Http\Controllers\Admin\StudentPromotionController::class);
        Route::get('student-promotions/class/{class}/students', [App\Http\Controllers\Admin\StudentPromotionController::class, 'getStudentsByClass'])->name('student-promotions.get-students');
        Route::get('student-promotions/destination-classes/{class}', [App\Http\Controllers\Admin\StudentPromotionController::class, 'getDestinationClasses'])->name('student-promotions.get-destination-classes');
        Route::get('student-promotions/student/{studentId}/history', [App\Http\Controllers\Admin\StudentPromotionController::class, 'studentHistory'])->name('student-promotions.history');
        Route::post('student-promotions/student/{studentId}/passed-out', [App\Http\Controllers\Admin\StudentPromotionController::class, 'markAsPassedOut'])->name('student-promotions.passed-out');
        
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
        Route::put('assets/{asset}/issue', [App\Http\Controllers\Admin\AssetController::class, 'issue'])->name('admin.assets.issue');
        Route::put('assets/{asset}/return', [App\Http\Controllers\Admin\AssetController::class, 'return'])->name('admin.assets.return');
        
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
        
        // Advanced Reporting Routes
        Route::get('advanced-reports/dashboard', [App\Http\Controllers\Admin\AdvancedReportController::class, 'dashboard'])->name('admin.advanced-reports.dashboard');
        Route::resource('advanced-reports', App\Http\Controllers\Admin\AdvancedReportController::class);
        Route::get('advanced-reports/{advancedReport}/export/{format}', [App\Http\Controllers\Admin\AdvancedReportController::class, 'export'])->name('admin.advanced-reports.export');
        
        // Language Management Routes
        Route::resource('languages', App\Http\Controllers\Admin\LanguageController::class);
        Route::get('languages/{language}/translations', [App\Http\Controllers\Admin\LanguageController::class, 'translations'])->name('admin.languages.translations');
        Route::post('languages/{language}/translations', [App\Http\Controllers\Admin\LanguageController::class, 'storeTranslation'])->name('admin.languages.translations.store');
        Route::put('languages/{language}/translations/{translation}', [App\Http\Controllers\Admin\LanguageController::class, 'updateTranslation'])->name('admin.languages.translations.update');
        Route::delete('languages/{language}/translations/{translation}', [App\Http\Controllers\Admin\LanguageController::class, 'destroyTranslation'])->name('admin.languages.translations.destroy');
        Route::get('languages/switch/{code}', [App\Http\Controllers\Admin\LanguageController::class, 'switchLanguage'])->name('admin.languages.switch');
        Route::get('languages/{language}/export', [App\Http\Controllers\Admin\LanguageController::class, 'exportTranslations'])->name('admin.languages.export');
        Route::post('languages/{language}/import', [App\Http\Controllers\Admin\LanguageController::class, 'importTranslations'])->name('admin.languages.import');
        
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
        Route::get('class-teacher-control/student-records', [App\Http\Controllers\ClassTeacherAssignmentController::class, 'studentRecords'])->name('class-teacher-control.student-records');
        
        // Fee Management Routes
        Route::resource('fees', App\Http\Controllers\Admin\FeeController::class);
        Route::resource('fee-structures', App\Http\Controllers\Admin\FeeStructureController::class);
        
        // School Classes Routes
        Route::resource('school-classes', App\Http\Controllers\Admin\SchoolClassController::class);
        
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
        
        // Academic Sessions Additional Routes
        Route::post('academic-sessions/{academic_session}/restore', [App\Http\Controllers\Admin\AcademicSessionController::class, 'restore'])->name('academic-sessions.restore');
        Route::post('academic-sessions/{academic_session}/set-current', [App\Http\Controllers\Admin\AcademicSessionController::class, 'setCurrent'])->name('academic-sessions.set-current');
        
        // Sections Additional Routes
        Route::post('sections/{section}/restore', [App\Http\Controllers\Admin\SectionController::class, 'restore'])->name('sections.restore');
        
        // Subjects Additional Routes
        Route::post('subjects/{subject}/restore', [App\Http\Controllers\Admin\SubjectController::class, 'restore'])->name('subjects.restore');
        
        // Admit Cards Additional Routes
        Route::post('admit-cards/bulk-publish', [App\Http\Controllers\Admin\AdmitCardController::class, 'bulkPublish'])->name('admit-cards.bulk-publish');
        Route::post('admit-cards/bulk-lock', [App\Http\Controllers\Admin\AdmitCardController::class, 'bulkLock'])->name('admit-cards.bulk-lock');
        Route::post('admit-cards/bulk-revoke', [App\Http\Controllers\Admin\AdmitCardController::class, 'bulkRevoke'])->name('admit-cards.bulk-revoke');
        Route::put('admit-cards/{admit_card}/publish', [App\Http\Controllers\Admin\AdmitCardController::class, 'publish'])->name('admit-cards.publish');
        Route::put('admit-cards/{admit_card}/lock', [App\Http\Controllers\Admin\AdmitCardController::class, 'lock'])->name('admit-cards.lock');
        Route::put('admit-cards/{admit_card}/revoke', [App\Http\Controllers\Admin\AdmitCardController::class, 'revoke'])->name('admit-cards.revoke');
        
        // Teacher Substitutions Additional Routes
        Route::post('teacher-substitutions/{substitution}/assign', [App\Http\Controllers\Admin\TeacherSubstitutionController::class, 'assign'])->name('teacher-substitutions.assign');
        Route::post('teacher-substitutions/{substitution}/approve', [App\Http\Controllers\Admin\TeacherSubstitutionController::class, 'approve'])->name('teacher-substitutions.approve');
        Route::post('teacher-substitutions/{substitution}/cancel', [App\Http\Controllers\Admin\TeacherSubstitutionController::class, 'cancel'])->name('teacher-substitutions.cancel');
        
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
        
        // Class Management Additional Routes
        Route::post('classes/save-class-teacher-assignment', [App\Http\Controllers\Admin\ClassController::class, 'saveClassTeacherAssignment'])->name('admin.classes.save-class-teacher-assignment');
        Route::post('classes/save-section-assignment', [App\Http\Controllers\Admin\ClassController::class, 'saveSectionAssignment'])->name('admin.classes.save-section-assignment');
        Route::post('classes/save-subject-teacher-assignment', [App\Http\Controllers\Admin\ClassController::class, 'saveSubjectTeacherAssignment'])->name('admin.classes.save-subject-teacher-assignment');
        Route::post('classes/save-subject-assignment', [App\Http\Controllers\Admin\ClassController::class, 'saveSubjectAssignment'])->name('admin.classes.save-subject-assignment');
        
        // Class Teacher Control Routes
        Route::get('class-teacher-control/assigned-classes', [App\Http\Controllers\ClassTeacherAssignmentController::class, 'assignedClasses'])->name('admin.class-teacher-control.assigned-classes');
        Route::get('class-teacher-control/unassigned-classes', [App\Http\Controllers\ClassTeacherAssignmentController::class, 'unassignedClasses'])->name('admin.class-teacher-control.unassigned-classes');
        Route::get('class-teacher-control/teacher-assignments', [App\Http\Controllers\ClassTeacherAssignmentController::class, 'teacherAssignments'])->name('admin.class-teacher-control.teacher-assignments');
        Route::post('class-teacher-control/assign-teacher', [App\Http\Controllers\ClassTeacherAssignmentController::class, 'assignTeacher'])->name('admin.class-teacher-control.assign-teacher');
        Route::post('class-teacher-control/remove-assignment', [App\Http\Controllers\ClassTeacherAssignmentController::class, 'removeAssignment'])->name('admin.class-teacher-control.remove-assignment');
        
        // Inventory Categories Routes
        Route::resource('inventory/categories', App\Http\Controllers\Admin\AssetCategoryController::class);
        Route::get('inventory/categories/{category}/edit', [App\Http\Controllers\Admin\AssetCategoryController::class, 'edit'])->name('inventory.categories.edit');
        Route::put('inventory/categories/{category}', [App\Http\Controllers\Admin\AssetCategoryController::class, 'update'])->name('inventory.categories.update');
        
        // Additional Inventory Category Routes
        Route::post('inventory/categories', [App\Http\Controllers\Admin\AssetCategoryController::class, 'store'])->name('admin.inventory.categories.store');
        Route::get('inventory/categories', [App\Http\Controllers\Admin\AssetCategoryController::class, 'index'])->name('admin.inventory.categories.index');
        Route::get('inventory/categories/{category}', [App\Http\Controllers\Admin\AssetCategoryController::class, 'show'])->name('admin.inventory.categories.show');
        Route::get('inventory/categories/{category}/edit', [App\Http\Controllers\Admin\AssetCategoryController::class, 'edit'])->name('admin.inventory.categories.edit');
        Route::put('inventory/categories/{category}', [App\Http\Controllers\Admin\AssetCategoryController::class, 'update'])->name('admin.inventory.categories.update');
        Route::delete('inventory/categories/{category}', [App\Http\Controllers\Admin\AssetCategoryController::class, 'destroy'])->name('admin.inventory.categories.destroy');
        
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
        
        // Additional Inventory Assets Routes
        Route::post('inventory/assets', [App\Http\Controllers\Admin\AssetController::class, 'store'])->name('admin.inventory.assets.store');
        Route::get('inventory/assets', [App\Http\Controllers\Admin\AssetController::class, 'index'])->name('admin.inventory.assets.index');
        Route::get('inventory/assets/{asset}', [App\Http\Controllers\Admin\AssetController::class, 'show'])->name('inventory.assets.show');
        Route::get('inventory/assets/{asset}/edit', [App\Http\Controllers\Admin\AssetController::class, 'edit'])->name('inventory.assets.edit');
        Route::put('inventory/assets/{asset}', [App\Http\Controllers\Admin\AssetController::class, 'update'])->name('admin.inventory.assets.update');
        Route::delete('inventory/assets/{asset}', [App\Http\Controllers\Admin\AssetController::class, 'destroy'])->name('admin.inventory.assets.destroy');

        
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
        
        // Class Management Routes
        Route::resource('classes', App\Http\Controllers\Admin\ClassController::class);
        
        // Class Teacher Control Routes
        Route::get('class-teacher-control/assigned-classes', [App\Http\Controllers\ClassTeacherAssignmentController::class, 'assignedClasses'])->name('admin.class-teacher-control.assigned-classes');
        Route::get('class-teacher-control/unassigned-classes', [App\Http\Controllers\ClassTeacherAssignmentController::class, 'unassignedClasses'])->name('admin.class-teacher-control.unassigned-classes');
        Route::get('class-teacher-control/teacher-assignments', [App\Http\Controllers\ClassTeacherAssignmentController::class, 'teacherAssignments'])->name('admin.class-teacher-control.teacher-assignments');
        Route::post('class-teacher-control/assign-teacher', [App\Http\Controllers\ClassTeacherAssignmentController::class, 'assignTeacher'])->name('admin.class-teacher-control.assign-teacher');
        Route::post('class-teacher-control/remove-assignment', [App\Http\Controllers\ClassTeacherAssignmentController::class, 'removeAssignment'])->name('admin.class-teacher-control.remove-assignment');
        
        // Additional Expenses Routes (already defined earlier)
        
        // Academic Sessions Additional Routes (already handled by resource)
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
    Route::get('/attendance/student/{studentId}/report', [AttendanceController::class, 'studentReport'])->name('attendance.student.report');
    
    // Library Management Routes
    Route::resource('books', App\Http\Controllers\Admin\BookController::class);
    Route::resource('book-issues', App\Http\Controllers\Admin\BookIssueController::class);
    Route::resource('library-settings', App\Http\Controllers\Admin\LibrarySettingController::class);
    Route::get('/library/dashboard', [App\Http\Controllers\Admin\BookController::class, 'dashboard'])->name('library.dashboard');
    Route::get('/library/return/{id}', [App\Http\Controllers\Admin\BookIssueController::class, 'returnBook'])->name('library.return');
    Route::get('/library/reports', [App\Http\Controllers\Admin\BookIssueController::class, 'reports'])->name('library.reports');
    Route::get('/library/export/{type?}', [App\Http\Controllers\Admin\BookIssueController::class, 'exportReport'])->name('library.export');
    
    // Lesson Plan Management Routes
    Route::resource('lesson-plans', App\Http\Controllers\Admin\LessonPlanController::class);
    Route::get('/lesson-plans/compliance', [App\Http\Controllers\Admin\LessonPlanController::class, 'compliance'])->name('admin.lesson-plans.compliance');
    Route::get('/lesson-plans/reports', [App\Http\Controllers\Admin\LessonPlanController::class, 'reports'])->name('admin.lesson-plans.reports');
    Route::get('/lesson-plans/dashboard-stats', [App\Http\Controllers\Admin\LessonPlanController::class, 'dashboardStats'])->name('admin.lesson-plans.dashboard-stats');
    Route::get('/lesson-plans/export-pdf', [App\Http\Controllers\Admin\LessonPlanController::class, 'exportPdf'])->name('admin.lesson-plans.export-pdf');
    Route::get('/lesson-plans/subject-progress', [App\Http\Controllers\Admin\LessonPlanController::class, 'subjectProgress'])->name('admin.lesson-plans.subject-progress');
    
    // Teacher Lesson Plan Routes
    Route::prefix('teacher')->name('teacher.')->group(function () {
        Route::resource('lesson-plans', App\Http\Controllers\Teacher\LessonPlanController::class);
        Route::get('/lesson-plans/history', [App\Http\Controllers\Teacher\LessonPlanController::class, 'history'])->name('lesson-plans.history');
    });
    
    // Teacher Biometric Routes
    Route::prefix('teacher')->name('teacher.')->group(function () {
        Route::get('/biometric/dashboard', [App\Http\Controllers\Teacher\BiometricController::class, 'dashboard'])->name('biometric.dashboard');
        Route::get('/biometric/records', [App\Http\Controllers\Teacher\BiometricController::class, 'getRecords'])->name('biometric.records');
        Route::get('/biometric/monthly-summary', [App\Http\Controllers\Teacher\BiometricController::class, 'monthlySummary'])->name('biometric.monthly-summary');
        Route::post('/biometric/dashboard/download', [App\Http\Controllers\Teacher\BiometricController::class, 'downloadReport'])->name('biometric.download');
        Route::get('/biometric/notification-preferences', [App\Http\Controllers\Teacher\BiometricController::class, 'notificationPreferences'])->name('biometric.notification-preferences');
        Route::post('/biometric/notification-preferences', [App\Http\Controllers\Teacher\BiometricController::class, 'updateNotificationPreferences'])->name('biometric.update-notification-preferences');
    });
    
    // Parent Lesson Plan Routes
    Route::prefix('parent')->name('parent.')->group(function () {
        Route::get('/lesson-plans', [App\Http\Controllers\Parent\LessonPlanController::class, 'index'])->name('lesson-plans.index');
        Route::get('/lesson-plans/{lessonPlan}', [App\Http\Controllers\Parent\LessonPlanController::class, 'show'])->name('lesson-plans.show');
        Route::get('/lesson-plans/books-to-send', [App\Http\Controllers\Parent\LessonPlanController::class, 'booksToSend'])->name('lesson-plans.books-to-send');
        Route::get('/lesson-plans/weekly-overview', [App\Http\Controllers\Parent\LessonPlanController::class, 'weeklyOverview'])->name('lesson-plans.weekly-overview');
    });
});