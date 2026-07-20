<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use App\Console\Commands\RouteHealthCheck;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register ErpRegistry singleton
        $this->app->singleton(\App\Services\Registry\ErpRegistry::class, function ($app) {
            $registry = new \App\Services\Registry\ErpRegistry();
            
            // Register Default ERP Modules
            $registry->registerModule('Students', ['version' => '1.0.0', 'description' => 'Admissions and Student records management.']);
            $registry->registerModule('Teachers', ['version' => '1.0.0', 'description' => 'HR, experience tracking and teacher substitutions.']);
            $registry->registerModule('Finance', ['version' => '1.0.0', 'description' => 'Fee structures, invoices, cash closing and Stripe payment gateway.']);
            $registry->registerModule('Operations', ['version' => '1.1.0', 'description' => 'Disaster recovery, diagnostics, queues, logs and SaaS licenses.']);
            $registry->registerModule('Timetable', ['version' => '1.0.0', 'description' => 'Weekly schedules, periods and teacher conflict audits.']);
            $registry->registerModule('Library', ['version' => '1.0.0', 'description' => 'Circulations, library rules, active issues and OPAC public search.']);
            $registry->registerModule('Hostel', ['version' => '1.0.0', 'description' => 'Hostel dorms details, room capacity allocation.']);
            $registry->registerModule('Visitor', ['version' => '1.0.0', 'description' => 'Campus gate visitor check-in registry.']);
            $registry->registerModule('FrontOffice', ['version' => '1.0.0', 'description' => 'Enquiries, visitors log, call registers, appointments and gate passes.']);

            // Register default imports
            // Note: "fee-structures" was removed (2026-07) — it only ever created an empty
            // FeeStructure header row with no fee items/amounts attached (those live in a
            // separate FeeStructureItem table this import never touched), so the resulting
            // "imported" structures had zero actual fees and had to be filled in manually
            // anyway. Not registered until a version that imports items too is built.
            $registry->registerImport('students', \App\Services\Imports\StudentImportDefinition::class);
            $registry->registerImport('teachers', \App\Services\Imports\TeacherImportDefinition::class);
            $registry->registerImport('parents', \App\Services\Imports\ParentImportDefinition::class);
            $registry->registerImport('classes', \App\Services\Imports\ClassImportDefinition::class);
            $registry->registerImport('sections', \App\Services\Imports\SectionImportDefinition::class);
            $registry->registerImport('subjects', \App\Services\Imports\SubjectImportDefinition::class);
            $registry->registerImport('bank_statement', \App\Services\Imports\BankStatementImportDefinition::class);
            $registry->registerImport('fee_opening_balance', \App\Services\Imports\FeeOpeningBalanceImportDefinition::class);
            $registry->registerImport('fee_opening_balance_summary', \App\Services\Imports\FeeOpeningBalanceSummaryImportDefinition::class);

            // Register default Notification Channels
            $registry->registerNotificationChannel('email', ['description' => 'SMTP mail communications.']);
            $registry->registerNotificationChannel('sms', ['description' => 'Twilio SMS mobile text delivery.']);
            $registry->registerNotificationChannel('whatsapp', ['description' => 'WhatsApp direct user chat API.']);
            $registry->registerNotificationChannel('push', ['description' => 'Push notifications for mobile app.']);
            $registry->registerNotificationChannel('in_app', ['description' => 'Internal bell notification system.']);

            // Register default Feature Flags
            $registry->registerFeatureFlag('enable_stripe', true);
            $registry->registerFeatureFlag('enable_whatsapp', false);
            $registry->registerFeatureFlag('maintenance_mode', false);
            $registry->registerFeatureFlag('auto_backups', true);

            // Register dynamic Sidebar sections & entries
            $registry->registerSidebarEntry('operations_center', [
                'title' => 'Operations Center',
                'icon' => 'bi-gear-wide-connected',
                'roles' => ['admin', 'super-admin'],
                'links' => [
                    ['title' => 'Operations Center', 'url' => '/operations/dashboard', 'icon' => 'bi-speedometer2'],
                    ['title' => 'System Health', 'url' => '/operations/health', 'icon' => 'bi-heart-pulse'],
                    ['title' => 'Disaster Recovery', 'url' => '/operations/backup', 'icon' => 'bi-cloud-arrow-up'],
                    ['title' => 'Queue Status', 'url' => '/operations/queue', 'icon' => 'bi-cpu'],
                    ['title' => 'Scheduled Tasks', 'url' => '/operations/scheduler', 'icon' => 'bi-calendar-check'],
                    ['title' => 'Notification Log', 'url' => '/operations/notifications', 'icon' => 'bi-chat-left-dots'],
                    ['title' => 'Environment Check', 'url' => '/operations/verification', 'icon' => 'bi-check-all'],
                    ['title' => 'System Logs', 'url' => '/operations/logs', 'icon' => 'bi-terminal'],
                    ['title' => 'Activity Timeline', 'url' => '/operations/timeline', 'icon' => 'bi-clock-history'],
                    ['title' => 'SaaS Subscription', 'url' => '/operations/license', 'icon' => 'bi-card-list'],
                    ['title' => 'Maintenance Mode', 'url' => '/operations/maintenance', 'icon' => 'bi-shield-slash'],
                    ['title' => 'Performance Metric', 'url' => '/operations/performance', 'icon' => 'bi-graph-up-arrow'],
                ]
            ]);

            // Register Phase 6 Sidebar entries
            $registry->registerSidebarEntry('scholastic_scheduler', [
                'title' => 'Timetable & Scheduling',
                'icon' => 'bi-calendar-range',
                'roles' => ['admin', 'super-admin', 'teacher'],
                'links' => [
                    ['title' => 'Weekly Timetable', 'url' => '/admin/timetable', 'icon' => 'bi-grid-3x3-gap'],
                ]
            ]);

            $registry->registerSidebarEntry('library_circulations', [
                'title' => 'Library Management',
                'icon' => 'bi-book-half',
                'roles' => ['admin', 'super-admin', 'student', 'parent'],
                'links' => [
                    ['title' => 'Circulations Index', 'url' => '/admin/library', 'icon' => 'bi-journal-bookmark-fill'],
                    ['title' => 'Public OPAC Search', 'url' => '/admin/library/opac', 'icon' => 'bi-search'],
                ]
            ]);

            $registry->registerSidebarEntry('hostel_manager', [
                'title' => 'Hostel Dorms',
                'icon' => 'bi-house-heart',
                'roles' => ['admin', 'super-admin'],
                'links' => [
                    ['title' => 'Dorm Dashboard', 'url' => '/admin/hostels/dashboard', 'icon' => 'bi-layout-text-window-reverse'],
                ]
            ]);

            $registry->registerSidebarEntry('visitor_gate_control', [
                'title' => 'Visitor & Gate Pass',
                'icon' => 'bi-door-open',
                'roles' => ['admin', 'super-admin'],
                'links' => [
                    ['title' => 'Gate Entries Log', 'url' => '/admin/visitor/log', 'icon' => 'bi-person-badge'],
                ]
            ]);

            $registry->registerSidebarEntry('front_office', [
                'title' => 'Front Office',
                'icon' => 'bi-telephone-inbound',
                'roles' => ['admin', 'super-admin', 'receptionist', 'reception'],
                'links' => [
                    ['title' => 'Dashboard', 'url' => '/admin/front-office/dashboard', 'icon' => 'bi-speedometer2'],
                    ['title' => 'Admission Enquiries', 'url' => '/admin/front-office/enquiries', 'icon' => 'bi-chat-left-text'],
                    ['title' => 'Visitor Log', 'url' => '/admin/front-office/visitors', 'icon' => 'bi-people'],
                    ['title' => 'Appointments', 'url' => '/admin/front-office/appointments', 'icon' => 'bi-calendar-event'],
                    ['title' => 'Call Register', 'url' => '/admin/front-office/calls', 'icon' => 'bi-telephone'],
                    ['title' => 'Gate Passes', 'url' => '/admin/front-office/gate-passes', 'icon' => 'bi-card-heading'],
                    ['title' => 'Courier Register', 'url' => '/admin/front-office/couriers', 'icon' => 'bi-box-seam'],
                    ['title' => 'Lost & Found', 'url' => '/admin/front-office/lost-found', 'icon' => 'bi-search-heart'],
                ]
            ]);

            // Register Phase 7 Sidebar entries
            $registry->registerSidebarEntry('academic_assessment', [
                'title' => 'Academic Assessment',
                'icon' => 'bi-clipboard-check',
                'roles' => ['admin', 'super-admin'],
                'links' => [
                    ['title' => 'Marks Moderation', 'url' => '/admin/exams/moderation/index', 'icon' => 'bi-sliders'],
                    ['title' => 'Report & Promotion', 'url' => '/admin/exams/reports/designer', 'icon' => 'bi-file-earmark-pdf'],
                ]
            ]);

            return $registry;
        });

        // Register LedgerService as a singleton
        $this->app->singleton(\App\Services\LedgerService::class, function ($app) {
            return new \App\Services\LedgerService();
        });

        // Register ImportEngine as a singleton loaded from ErpRegistry
        $this->app->singleton(\App\Services\Imports\ImportEngine::class, function ($app) {
            $engine = new \App\Services\Imports\ImportEngine(
                $app->make(\App\Services\Imports\ImportNormalizer::class),
                $app->make(\App\Services\Imports\ImportLookupCache::class)
            );
            
            $registry = $app->make(\App\Services\Registry\ErpRegistry::class);
            foreach ($registry->getImports() as $name => $class) {
                $engine->registerDefinition($name, $class);
            }
            
            return $engine;
        });

        // Register custom commands
        $this->commands([
            RouteHealthCheck::class,
            \App\Console\Commands\MigrateHistoricalLedger::class,
            \App\Console\Commands\AdminSidebarAudit::class,
            \App\Console\Commands\AssignMissingTeachers::class,
            \App\Console\Commands\AttendanceNullPeriodDiagnosticsCommand::class,
            \App\Console\Commands\ReconcileTerminalStatusesCommand::class,
            \App\Console\Commands\SystemRouteAudit::class,
            \App\Console\Commands\TestDataModel::class,
            \App\Console\Commands\UpdateBiometricSettings::class,
            \App\Console\Commands\DiagnoseCommand::class,
            \App\Console\Commands\CalculateDailyLibraryFines::class,
            \App\Console\Commands\ArchitectureAuditCommand::class,
        ]);
    }


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // App layouts only load Bootstrap 5 (no Tailwind), but Laravel's default
        // pagination view is Tailwind-based. Without Tailwind's CSS, the raw
        // `<svg class="w-5 h-5">` prev/next arrows render at their unstyled
        // native size, producing oversized icons in every paginated list.
        \Illuminate\Pagination\Paginator::useBootstrapFive();

        // Add custom blade directive for academic year
        Blade::directive('academicYear', function () {
            return "<?php echo app(App\\Providers\\AppServiceProvider::class)->getCurrentAcademicYear(); ?>";
        });

        // Auto-run migrations for Exam Cell Members, Seating, Invigilators, and Relieving
        if (!app()->runningUnitTests()) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('teachers') && !\Illuminate\Support\Facades\Schema::hasColumn('teachers', 'is_exam_cell_member')) {
                    \Illuminate\Support\Facades\Schema::table('teachers', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->boolean('is_exam_cell_member')->default(false)->after('is_exam_head');
                    });
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('parents')) {
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('parents', 'mobile')) {
                        \Illuminate\Support\Facades\Schema::table('parents', function (\Illuminate\Database\Schema\Blueprint $table) {
                            $table->string('mobile')->nullable()->after('phone');
                        });
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('parents', 'admission_number')) {
                        \Illuminate\Support\Facades\Schema::table('parents', function (\Illuminate\Database\Schema\Blueprint $table) {
                            $table->string('admission_number')->nullable();
                        });
                    }
                }

                if (!\Illuminate\Support\Facades\Schema::hasTable('exam_seating_arrangements')) {
                    \Illuminate\Support\Facades\Schema::create('exam_seating_arrangements', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->id();
                        $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
                        $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                        $table->string('room_number');
                        $table->string('seat_number');
                        $table->timestamps();
                        
                        $table->unique(['exam_id', 'student_id']);
                    });
                }

                if (!\Illuminate\Support\Facades\Schema::hasTable('exam_invigilator_duties')) {
                    \Illuminate\Support\Facades\Schema::create('exam_invigilator_duties', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->id();
                        $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
                        $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
                        $table->string('room_number');
                        $table->string('role')->default('Main Invigilator');
                        $table->timestamps();
                        
                        $table->unique(['exam_id', 'teacher_id']);
                    });
                }

                if (!\Illuminate\Support\Facades\Schema::hasTable('exam_relieving_duties')) {
                    \Illuminate\Support\Facades\Schema::create('exam_relieving_duties', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->id();
                        $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
                        $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
                        $table->string('time_slot');
                        $table->string('room_number');
                        $table->timestamps();
                    });
                }
            } catch (\Exception $e) {
                // Silence database connection errors before DB is setup
            }
        }

        // Force route cache clearing to apply new route changes instantly
        try {
            $routeCache = base_path('bootstrap/cache/routes-v7.php');
            if (file_exists($routeCache)) {
                @unlink($routeCache);
            }
            \Illuminate\Support\Facades\Artisan::call('route:clear');
        } catch (\Exception $e) {
            // Silence any issues during early boot phase
        }

        // Register Student model observer
        \App\Models\Student::observe(\App\Observers\StudentObserver::class);

        // Listen for slow DB queries and log them to Cache
        try {
            \Illuminate\Support\Facades\DB::listen(function ($query) {
                try {
                    if ($query->time <= 50) {
                        return;
                    }

                    // Never log writes to the `cache` table itself. This
                    // listener's own Cache::put() below is a query, and once
                    // the stored payload gets large enough that write starts
                    // taking >50ms too -- logging it would embed the entire
                    // previous payload as this entry's "bindings", roughly
                    // doubling the stored size every time it fires. That
                    // runaway growth blew past the `cache.value` column's
                    // capacity in production (a multi-hundred-row import
                    // generates thousands of queries), and the resulting
                    // exception propagated up through DB::listen into
                    // whatever unrelated query triggered it.
                    if (stripos($query->sql, '`cache`') !== false) {
                        return;
                    }

                    // Bound the size of what a single slow query can ever
                    // contribute, regardless of source, so this diagnostic
                    // feature can never itself produce an oversized payload.
                    $bindings = array_map(function ($binding) {
                        $value = is_string($binding) ? $binding : json_encode($binding);
                        return $value !== null && strlen($value) > 500
                            ? substr($value, 0, 500) . '...(truncated)'
                            : $binding;
                    }, $query->bindings);

                    $slowQueries = \Illuminate\Support\Facades\Cache::get('perf_slow_queries', []);
                    $slowQueries[] = [
                        'sql' => substr($query->sql, 0, 1000),
                        'bindings' => $bindings,
                        'time' => $query->time,
                        'connection' => $query->connectionName,
                        'logged_at' => now()->toDateTimeString(),
                    ];
                    if (count($slowQueries) > 20) {
                        array_shift($slowQueries);
                    }
                    \Illuminate\Support\Facades\Cache::put('perf_slow_queries', $slowQueries, now()->addHours(24));
                } catch (\Throwable $e) {
                    // This is a best-effort diagnostics feature -- it must
                    // never be able to break the query/request that
                    // triggered it.
                }
            });
        } catch (\Throwable $e) {
            // ignore during early app bootstrap
        }
    }

    /**
     * Get current academic year in format YYYY-YY
     */
    public function getCurrentAcademicYear()
    {
        $currentMonth = date('n');
        $currentYear = date('Y');
        
        // Academic year typically starts in April (month 4)
        if ($currentMonth >= 4) {
            $startYear = $currentYear;
            $endYear = $currentYear + 1;
        } else {
            $startYear = $currentYear - 1;
            $endYear = $currentYear;
        }
        
        return $startYear . '-' . substr($endYear, -2);
    }
}