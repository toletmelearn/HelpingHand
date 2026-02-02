<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class SystemRouteAudit extends Command
{
    protected $signature = 'system:route-audit {--fix : Automatically fix common route issues}';
    protected $description = 'Audit all route() calls in views, controllers, and components against registered routes';

    private $registeredRoutes = [];
    private $routeUsages = [];
    private $missingRoutes = [];
    private $mismatchedRoutes = [];

    public function handle()
    {
        $this->info('🔍 Starting System Route Audit...');
        $this->line('');

        // Load all registered routes
        $this->loadRegisteredRoutes();
        
        // Scan for route usage
        $this->scanRouteUsage();
        
        // Analyze findings
        $this->analyzeRouteIssues();
        
        // Display results
        $this->displayResults();
        
        // Apply fixes if requested
        if ($this->option('fix')) {
            $this->applyAutoFixes();
        }

        $this->line('');
        $this->info('✅ Route audit completed!');
        
        return 0;
    }

    private function loadRegisteredRoutes()
    {
        $this->info('Loading registered routes...');
        
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if ($name) {
                $this->registeredRoutes[$name] = [
                    'uri' => $route->uri(),
                    'methods' => $route->methods(),
                    'action' => $route->getActionName()
                ];
            }
        }
        
        $this->info('✓ Loaded ' . count($this->registeredRoutes) . ' registered routes');
    }

    private function scanRouteUsage()
    {
        $this->info('Scanning for route() usage...');
        
        // Scan Blade views
        $this->scanBladeViews();
        
        // Scan Controllers
        $this->scanControllers();
        
        // Scan Components (if any)
        $this->scanComponents();
        
        $this->info('✓ Scan complete');
    }

    private function scanBladeViews()
    {
        $viewPath = resource_path('views');
        if (!File::exists($viewPath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($viewPath)->name('*.blade.php');

        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealPath());
            $relativePath = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $file->getRealPath());
            
            // Find route() calls
            preg_match_all('/route\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches, PREG_OFFSET_CAPTURE);
            
            foreach ($matches[1] as $match) {
                $routeName = $match[0];
                $lineNumber = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                
                $this->routeUsages[] = [
                    'route_name' => $routeName,
                    'file' => $relativePath,
                    'line' => $lineNumber,
                    'type' => 'blade'
                ];
            }
        }
    }

    private function scanControllers()
    {
        $controllerPath = app_path('Http/Controllers');
        if (!File::exists($controllerPath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($controllerPath)->name('*.php');

        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealPath());
            $relativePath = str_replace(app_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
            
            // Find route() calls
            preg_match_all('/route\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches, PREG_OFFSET_CAPTURE);
            
            foreach ($matches[1] as $match) {
                $routeName = $match[0];
                $lineNumber = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                
                $this->routeUsages[] = [
                    'route_name' => $routeName,
                    'file' => $relativePath,
                    'line' => $lineNumber,
                    'type' => 'controller'
                ];
            }
        }
    }

    private function scanComponents()
    {
        // Scan component directories if they exist
        $componentPaths = [
            app_path('View/Components'),
            resource_path('views/components')
        ];

        foreach ($componentPaths as $path) {
            if (!File::exists($path)) {
                continue;
            }

            $finder = new Finder();
            $finder->files()->in($path)->name('*.php');

            foreach ($finder as $file) {
                $content = file_get_contents($file->getRealPath());
                $relativePath = str_replace(dirname($path, 2) . DIRECTORY_SEPARATOR, '', $file->getRealPath());
                
                // Find route() calls
                preg_match_all('/route\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches, PREG_OFFSET_CAPTURE);
                
                foreach ($matches[1] as $match) {
                    $routeName = $match[0];
                    $lineNumber = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    
                    $this->routeUsages[] = [
                        'route_name' => $routeName,
                        'file' => $relativePath,
                        'line' => $lineNumber,
                        'type' => 'component'
                    ];
                }
            }
        }
    }

    private function analyzeRouteIssues()
    {
        $this->info('Analyzing route issues...');
        
        foreach ($this->routeUsages as $usage) {
            $routeName = $usage['route_name'];
            
            if (!isset($this->registeredRoutes[$routeName])) {
                $this->missingRoutes[] = $usage;
            }
        }
        
        // Check for common route naming patterns that might be mismatched
        $adminRoutes = array_filter(array_keys($this->registeredRoutes), function($name) {
            return str_starts_with($name, 'admin.');
        });
        
        foreach ($this->routeUsages as $usage) {
            $routeName = $usage['route_name'];
            if (str_contains($routeName, 'admin') && !str_starts_with($routeName, 'admin.')) {
                $suggestions = $this->findSimilarAdminRoutes($routeName, $adminRoutes);
                if (!empty($suggestions)) {
                    $this->mismatchedRoutes[] = array_merge($usage, ['suggestions' => $suggestions]);
                }
            }
        }
    }

    private function findSimilarAdminRoutes($requestedRoute, $availableRoutes)
    {
        $suggestions = [];
        $requestedParts = explode('.', $requestedRoute);
        
        foreach ($availableRoutes as $availableRoute) {
            $availableParts = explode('.', $availableRoute);
            
            // Check if they have similar structure
            if (count($requestedParts) === count($availableParts)) {
                $similarity = 0;
                for ($i = 0; $i < min(count($requestedParts), count($availableParts)); $i++) {
                    if ($requestedParts[$i] === $availableParts[$i]) {
                        $similarity++;
                    }
                }
                
                if ($similarity >= (count($requestedParts) - 1)) {
                    $suggestions[] = $availableRoute;
                }
            }
        }
        
        return array_slice($suggestions, 0, 3); // Return top 3 suggestions
    }

    private function displayResults()
    {
        // Summary
        $this->line('');
        $this->info('📊 ROUTE AUDIT SUMMARY');
        $this->line('=====================');
        $this->line("Total route usages found: " . count($this->routeUsages));
        $this->line("Missing routes: " . count($this->missingRoutes));
        $this->line("Mismatched routes: " . count($this->mismatchedRoutes));
        $this->line("Registered routes: " . count($this->registeredRoutes));
        $this->line('');

        // Missing Routes
        if (!empty($this->missingRoutes)) {
            $this->error('❌ MISSING ROUTES DETECTED');
            $this->line('==========================');
            
            $headers = ['Route Name', 'File', 'Line', 'Type'];
            $rows = array_map(function($item) {
                return [
                    $item['route_name'],
                    $item['file'],
                    $item['line'],
                    strtoupper($item['type'])
                ];
            }, $this->missingRoutes);
            
            $this->table($headers, $rows);
        }

        // Mismatched Routes
        if (!empty($this->mismatchedRoutes)) {
            $this->warn('⚠️  POSSIBLE ROUTE MISMATCHES');
            $this->line('=============================');
            
            foreach ($this->mismatchedRoutes as $item) {
                $this->line("• {$item['route_name']} in {$item['file']}:{$item['line']}");
                if (!empty($item['suggestions'])) {
                    $this->line("  Suggestions: " . implode(', ', $item['suggestions']));
                }
                $this->line('');
            }
        }

        // Key Admin Routes Status
        $this->line('');
        $this->info('📋 KEY ADMIN ROUTES STATUS');
        $this->line('=========================');
        
        $keyRoutes = [
            'admin.dashboard',
            'admin.student-promotions.index',
            'admin.students.index',
            'admin.teachers.index',
            'admin.attendance.index',
            'admin.exams.index'
        ];
        
        foreach ($keyRoutes as $route) {
            $status = isset($this->registeredRoutes[$route]) ? '✅ REGISTERED' : '❌ MISSING';
            $this->line("$status - $route");
        }
    }

    private function applyAutoFixes()
    {
        $this->info('');
        $this->info('🔧 APPLYING AUTO-FIXES...');
        $this->line('');

        $fixedCount = 0;

        // Only fix simple mismatches with high confidence
        foreach ($this->mismatchedRoutes as $issue) {
            if (!empty($issue['suggestions']) && count($issue['suggestions']) === 1) {
                $correctRoute = $issue['suggestions'][0];
                $oldRoute = $issue['route_name'];
                $file = resource_path('views/' . $issue['file']);
                
                if (File::exists($file)) {
                    $content = File::get($file);
                    $newContent = str_replace(
                        "route('{$oldRoute}')", 
                        "route('{$correctRoute}')", 
                        $content
                    );
                    
                    if ($content !== $newContent) {
                        File::put($file, $newContent);
                        $this->line("✓ Fixed: {$oldRoute} → {$correctRoute} in {$issue['file']}");
                        $fixedCount++;
                    }
                }
            }
        }

        $this->info("Applied {$fixedCount} automatic fixes");
    }
}