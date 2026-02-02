<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

class RouteHealthCheck extends Command
{
    protected $signature = 'route:health-check';
    protected $description = 'Check for broken routes in blade files';

    public function handle()
    {
        $this->info('Starting Route Health Check...');

        $bladeFiles = $this->getAllBladeFiles();
        $allRoutes = $this->getAllRouteNames();
        
        $brokenRoutes = [];
        $foundRouteReferences = [];

        foreach ($bladeFiles as $file) {
            $content = File::get($file);
            
            // Find all route() calls in blade files
            preg_match_all('/route\s*\(\s*[\'\"]([^\s\'\"]+)[\'\"]/i', $content, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $routeName) {
                    $routeName = trim($routeName);
                    $foundRouteReferences[] = ['file' => $file, 'route' => $routeName];
                    
                    if (!$this->routeExists($routeName, $allRoutes)) {
                        $brokenRoutes[] = ['file' => $file, 'route' => $routeName];
                    }
                }
            }
        }

        if (empty($brokenRoutes)) {
            $this->info('✓ All routes in blade files are valid!');
            $this->info('Found ' . count($foundRouteReferences) . ' route references in blade files.');
        } else {
            $this->error('✗ Found ' . count($brokenRoutes) . ' broken routes:');
            
            foreach ($brokenRoutes as $brokenRoute) {
                $this->error("- Route: {$brokenRoute['route']} in file: {$brokenRoute['file']}");
            }
            
            return 1; // Return error code
        }

        return 0;
    }

    private function getAllBladeFiles()
    {
        $files = [];
        $directories = [
            resource_path('views'),
            base_path('resources/views')
        ];

        foreach ($directories as $directory) {
            if (File::exists($directory)) {
                $bladeFiles = File::allFiles($directory);
                
                foreach ($bladeFiles as $file) {
                    if (str_ends_with($file->getPathname(), '.blade.php')) {
                        $files[] = $file->getPathname();
                    }
                }
            }
        }

        return $files;
    }

    private function getAllRouteNames()
    {
        $routes = [];
        $routeCollection = Route::getRoutes();

        foreach ($routeCollection as $route) {
            if ($route->getName()) {
                $routes[] = $route->getName();
            }
        }

        return $routes;
    }

    private function routeExists($routeName, $allRoutes)
    {
        return in_array($routeName, $allRoutes);
    }
}