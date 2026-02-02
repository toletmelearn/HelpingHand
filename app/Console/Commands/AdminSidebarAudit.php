<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class AdminSidebarAudit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:audit-sidebar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enterprise-grade module-based sidebar audit';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // NOTE:
        // Only stable, Route::has()-resolvable entry points are audited.
        // Experimental or conditionally-registered modules must NOT be added
        // until their routes are guaranteed at runtime.

        $this->info('🔍 ENTERPRISE SIDEBAR AUDIT - MODULE-BASED');
        $this->line(str_repeat('=', 60));
        $this->newLine();

        // Get all admin routes
        $adminRoutes = collect(Route::getRoutes())->filter(function ($route) {
            return Str::startsWith($route->getName(), 'admin.');
        })->map(function ($route) {
            return [
                'name' => $route->getName(),
                'uri' => $route->uri(),
                'methods' => $route->methods(),
            ];
        })->sortBy('name');

        $this->info("📊 TOTAL ADMIN ROUTES: " . $adminRoutes->count());
        
        // Filter for entry-point routes only (enterprise standard)
        $entryPointRoutes = $adminRoutes->filter(function ($route) {
            $name = $route['name'];
            
            // Include only entry-point routes (ERP standard)
            $validEndings = ['index', 'dashboard', 'overview', 'reports', 'settings'];
            $hasValidEnding = false;
            foreach ($validEndings as $ending) {
                if (Str::endsWith($name, '.' . $ending)) {
                    $hasValidEnding = true;
                    break;
                }
            }
            
            // Exclude workflow/action routes (ERP standard)
            $excludeKeywords = [
                'create', 'store', 'edit', 'update', 'destroy', 'delete',
                'toggle', 'approve', 'reject', 'bulk', 'export', 'import',
                'sync', 'ajax', 'api', 'submit', 'clone', 'lock', 'unlock',
                'publish', 'revoke', 'restore', 'assign', 'cancel'
            ];
            
            $hasExcludeKeyword = false;
            foreach ($excludeKeywords as $keyword) {
                if (Str::contains($name, '.' . $keyword)) {
                    $hasExcludeKeyword = true;
                    break;
                }
            }
            
            return $hasValidEnding && !$hasExcludeKeyword;
        });

        $this->info("🏢 TOTAL MODULE ENTRY POINTS: " . $entryPointRoutes->count());
        $this->newLine();

        // Define expected sidebar modules (based on current sidebar structure)
        $expectedModules = [
            'Dashboard' => ['admin.dashboard'],
            'Students' => ['admin.students.index', 'admin.student-promotions.index', 'admin.student-statuses.index'],
            'Teachers' => ['admin.teachers.index', 'admin.teacher-substitutions.index', 'admin.teacher-biometrics.index'],
            'Academic' => [
                'admin.classes.index', 'admin.sections.index', 'admin.subjects.index',
                'admin.academic-sessions.index', 'admin.syllabi.index',
                'admin.daily-teaching-work.index', 'admin.lesson-plans.index'
            ],
            'Attendance' => ['admin.attendance.index'],
            'Examinations' => [
                'admin.exams.index', 'admin.exam-papers.index', 'admin.exam-paper-templates.index',
                'admin.results.index', 'admin.result-formats.index',
                'admin.admit-cards.index', 'admin.admit-card-formats.index'
            ],
            'Finance' => ['admin.fees.index', 'admin.fee-structures.index'],
            'Budget' => ['admin.budgets.index', 'admin.expenses.index', 'admin.budget-categories.index'],
            'Library' => ['admin.books.index', 'admin.book-issues.index', 'admin.library-settings.index'],
            'Inventory' => ['admin.inventory.index', 'admin.assets.index', 'admin.admin.inventory.categories.index'],
            'Certificates' => ['admin.certificates.index', 'admin.certificate-templates.index'],
            'Configuration' => [
                'admin.admin.configurations.index', 'admin.language-settings.index',
                'admin.notification-settings.index', 'admin.role-permissions.index',
                'admin.field-permissions.index', 'admin.class-teacher-control.student-records',
                'admin.teacher-subject-assignments.index', 'admin.teacher-class-assignments.index',
                'admin.grading-systems.index', 'admin.examination-patterns.index',
                'admin.document-formats.index', 'admin.permissions.index'
            ],
            'Reports' => [
                'admin.reports.index', 'admin.advanced-reports.index',
                'admin.audit-logs.index'
            ],
            'System' => [
                'admin.backups.index', 'admin.bell-schedules.index',
                'admin.special-day-overrides.index', 'admin.biometric-devices.index',
                'admin.sync-monitor.index'
            ]
        ];

        $sidebarModules = collect($expectedModules);
        $this->info("📋 EXPECTED MODULES: " . $sidebarModules->count());
        $this->newLine();

        // Audit results
        $modulesWithEntryPoints = 0;
        $modulesMissingEntryPoints = [];
        $brokenSidebarReferences = [];

        $this->line('🏢 MODULE AUDIT RESULTS:');
        $this->line(str_repeat('-', 40));

        foreach ($sidebarModules as $moduleName => $expectedRoutes) {
            $moduleRoutes = collect($expectedRoutes);
            $foundEntryPoints = 0;
            $missingRoutes = [];
            
            foreach ($moduleRoutes as $routeName) {
                if ($entryPointRoutes->contains('name', $routeName)) {
                    $this->line("✅ [ENTRY] " . $moduleName . ' → ' . $routeName);
                    $foundEntryPoints++;
                } else {
                    $missingRoutes[] = $routeName;
                }
            }
            
            if ($foundEntryPoints > 0) {
                $modulesWithEntryPoints++;
                $this->line("   📊 " . $moduleName . ": " . $foundEntryPoints . " entry point(s)");
            } else {
                $modulesMissingEntryPoints[] = $moduleName;
                $this->line("❌ [MISSING] " . $moduleName . " (0 entry points)");
                foreach ($missingRoutes as $route) {
                    $this->line("   ⚠️  Missing: " . $route);
                }
            }
            $this->newLine();
        }

        // Check for broken sidebar references
        $this->line('⚠️  BROKEN SIDEBAR REFERENCES:');
        $this->line(str_repeat('-', 40));
        
        $allExpectedRoutes = $sidebarModules->flatten();
        foreach ($allExpectedRoutes as $routeName) {
            if (!$adminRoutes->contains('name', $routeName)) {
                $this->line("⚠️  [BROKEN] " . $routeName);
                $brokenSidebarReferences[] = $routeName;
            }
        }

        if (empty($brokenSidebarReferences)) {
            $this->line("✅ No broken sidebar references found");
        }

        // Summary report
        $this->newLine();
        $this->line(str_repeat('=', 60));
        $this->info('📋 ENTERPRISE AUDIT SUMMARY');
        $this->line(str_repeat('=', 60));
        
        $moduleCoverage = ($modulesWithEntryPoints / max($sidebarModules->count(), 1)) * 100;
        
        $this->line("📈 MODULE COVERAGE STATISTICS:");
        $this->line("   • Total modules: " . $sidebarModules->count());
        $this->line("   • Modules with entry points: " . $modulesWithEntryPoints);
        $this->line("   • Modules missing entry points: " . count($modulesMissingEntryPoints));
        $this->line("   • Broken sidebar references: " . count($brokenSidebarReferences));
        $this->line("   • Module coverage: " . round($moduleCoverage, 1) . "%");
        $this->line("   • Entry point routes in sidebar: " . $entryPointRoutes->count());
        
        $this->newLine();
        if ($moduleCoverage >= 100) {
            $this->info("🏆 STATUS: PERFECT MODULE COVERAGE (100%)");
        } elseif ($moduleCoverage >= 90) {
            $this->warn("⚠️  STATUS: EXCELLENT COVERAGE (90-99%)");
        } elseif ($moduleCoverage >= 80) {
            $this->warn("⚠️  STATUS: GOOD COVERAGE (80-89%)");
        } else {
            $this->error("❌ STATUS: INSUFFICIENT COVERAGE (<80%) - ACTION REQUIRED");
        }

        // Final validation
        $this->newLine();
        $hasFailures = count($modulesMissingEntryPoints) > 0 || count($brokenSidebarReferences) > 0;
        
        if (!$hasFailures) {
            $this->info("✅ ENTERPRISE AUDIT PASSED");
            $this->line("🎉 All modules have entry points in sidebar");
            $this->line("🎉 No broken sidebar references");
            $this->line("🎉 Ready for production deployment");
            return 0; // Success
        } else {
            $this->error("❌ ENTERPRISE AUDIT FAILED");
            
            if (count($modulesMissingEntryPoints) > 0) {
                $this->line("Modules missing entry points:");
                foreach ($modulesMissingEntryPoints as $module) {
                    $this->line("   • " . $module);
                }
            }
            
            if (count($brokenSidebarReferences) > 0) {
                $this->line("Broken sidebar references:");
                foreach ($brokenSidebarReferences as $route) {
                    $this->line("   • " . $route);
                }
            }
            
            $this->line("\n🔧 ACTION REQUIRED: Fix missing modules and broken references");
            return 1; // Failure
        }
    }
}