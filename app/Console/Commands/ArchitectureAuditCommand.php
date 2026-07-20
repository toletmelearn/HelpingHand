<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Registry\ErpRegistry;
use App\Services\Operations\DiagnosticEngine;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

class ArchitectureAuditCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'architecture:audit';

    /**
     * The console command description.
     */
    protected $description = 'Performs a comprehensive architecture audit to prevent code drift and duplication.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("=============================================");
        $this->info("   HelpingHand ERP Architecture Audit Engine  ");
        $this->info("=============================================");

        $failed = false;

        // 1. Controller Duplication Audits
        $this->comment("\n1. Auditing Controllers...");
        $duplicates = [
            'App\Http\Controllers\Admin\AdminExamPaperController' => 'App\Http\Controllers\Admin\ExamPaperController',
            'App\Http\Controllers\Admin\AdminHostelController' => 'App\Http\Controllers\Admin\HostelController',
            'App\Http\Controllers\ExamPaperController' => 'App\Http\Controllers\Admin\ExamPaperController',
        ];

        foreach ($duplicates as $dep => $can) {
            if (class_exists($dep)) {
                $ref = new \ReflectionClass($dep);
                if (!$ref->isSubclassOf($can)) {
                    $this->error("[FAIL] Deprecated controller '{$dep}' does not extend canonical class '{$can}'.");
                    $failed = true;
                } else {
                    $this->line("[WARN] Deprecated controller placeholder '{$dep}' is correctly subclassed.");
                }
            } else {
                $this->info("[PASS] Legacy controller '{$dep}' has been fully removed.");
            }
        }

        // 2. View Folder Duplication Audits
        $this->comment("\n2. Auditing Blade Views...");
        $legacyDirs = [
            resource_path('views/admin/hostel'),
            resource_path('views/admin/exam_papers'),
            resource_path('views/exam-papers'),
        ];
        foreach ($legacyDirs as $dir) {
            if (is_dir($dir)) {
                $this->error("[FAIL] Obsolete Blade directory '{$dir}' still exists. Consolidate templates.");
                $failed = true;
            } else {
                $this->info("[PASS] Obsolete view directory '" . basename($dir) . "' is absent.");
            }
        }

        // 3. Route Integrity Audits
        $this->comment("\n3. Auditing Route Configurations...");
        $routes = Route::getRoutes();
        $names = [];
        foreach ($routes as $route) {
            $name = $route->getName();
            if ($name) {
                if (in_array($name, $names)) {
                    $this->error("[FAIL] Duplicate route name detected: {$name}");
                    $failed = true;
                }
                $names[] = $name;
            }

            $action = $route->getActionName();
            if (strpos($action, 'AdminExamPaperController') !== false && !class_exists('App\Http\Controllers\Admin\AdminExamPaperController')) {
                $this->error("[FAIL] Route {$name} ({$route->uri()}) points to non-existent deprecated controller.");
                $failed = true;
            }
            if (strpos($action, 'AdminHostelController') !== false && !class_exists('App\Http\Controllers\Admin\AdminHostelController')) {
                $this->error("[FAIL] Route {$name} ({$route->uri()}) points to non-existent deprecated controller.");
                $failed = true;
            }
        }
        $this->info("[PASS] Route name collisions check passed.");

        // 4. Sidebar Link Integrity Audits
        $this->comment("\n4. Auditing Sidebar Navigation...");
        $registry = app(ErpRegistry::class);
        $sidebar = $registry->getSidebarEntries();
        foreach ($sidebar as $sectionKey => $section) {
            foreach ($section['links'] as $link) {
                $url = $link['url'];
                if ($url === '#') continue;

                try {
                    $req = Request::create($url, 'GET');
                    $route = Route::getRoutes()->match($req);
                    if (!$route) {
                        $this->error("[FAIL] Sidebar entry '{$link['title']}' URL '{$url}' is unmapped.");
                        $failed = true;
                    }
                } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
                    $this->error("[FAIL] Sidebar entry '{$link['title']}' URL '{$url}' is unmapped.");
                    $failed = true;
                }
            }
        }
        if (!$failed) {
            $this->info("[PASS] Sidebar dynamic links resolve successfully.");
        }

        $this->line("");
        if ($failed) {
            $this->error("=============================================");
            $this->error("   [FAIL] Architecture Audit Failed! Fix errors. ");
            $this->error("=============================================");
            return Command::FAILURE;
        }

        $this->info("=============================================");
        $this->info("   [PASS] Architecture Integrity Confirmed!  ");
        $this->info("=============================================");
        return Command::SUCCESS;
    }
}
