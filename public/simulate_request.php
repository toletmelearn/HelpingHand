<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

// Bootstrap Laravel
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$output = "Targeted Sidebar URL Simulation (Optimized)\n";
$output .= "===========================================\n\n";

try {
    // Find a Clerk user
    $clerk = \App\Models\User::role('clerk')->first();
    if (!$clerk) {
        $output .= "No clerk user found in database!\n";
        file_put_contents(__DIR__ . '/simulate_output.txt', $output);
        exit;
    }
    
    $output .= "Testing with Clerk user: ID={$clerk->id}, Email={$clerk->email}\n\n";
    
    $urls = [
        '/admin/dashboard',
        '/admin/students',
        '/admin/student-promotions',
        '/admin/student-statuses',
        '/admin/teachers',
        '/admin/teacher-substitutions',
        '/admin/teacher-biometrics',
        '/admin/classes',
        '/admin/sections',
        '/admin/subjects',
        '/admin/academic-sessions',
        '/admin/syllabi',
        '/admin/daily-teaching-work',
        '/admin/lesson-plans',
        '/admin/attendance',
        '/admin/exams',
        '/admin/exam-papers',
        '/admin/exam-paper-templates',
        '/admin/results',
        '/admin/result-formats',
        '/admin/admit-cards',
        '/admin/admit-card-formats',
        '/admin/configurations',
        '/admin/fees',
        '/admin/fee-structures',
        '/admin/budgets',
        '/admin/expenses',
        '/admin/budget-categories',
        '/admin/books',
        '/admin/book-issues',
        '/admin/library-settings',
        '/admin/inventory',
        '/admin/assets',
        '/admin/admin/inventory/categories',
        '/admin/certificates',
        '/admin/certificate-templates',
    ];
    
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    
    foreach ($urls as $url) {
        // Log in user
        auth()->login($clerk);
        
        // Create Request
        $request = Illuminate\Http\Request::create($url, 'GET');
        $session = $app->make('session')->driver();
        $request->setLaravelSession($session);
        
        // Handle request
        try {
            $response = $kernel->handle($request);
            $status = $response->getStatusCode();
            
            $output .= "URL: {$url} -> Status: {$status}";
            if ($response->isRedirection()) {
                $output .= " (Redirect to: " . $response->headers->get('Location') . ")";
            }
            $output .= "\n";
            
            $kernel->terminate($request, $response);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $output .= "URL: {$url} -> Status: " . $e->getStatusCode() . " (HttpException: " . $e->getMessage() . ")\n";
        } catch (\Throwable $t) {
            $output .= "URL: {$url} -> Error: " . $t->getMessage() . "\n";
        }
        
        auth()->logout();
    }
} catch (\Throwable $e) {
    $output .= "Fatal Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

file_put_contents(__DIR__ . '/simulate_output.txt', $output);
echo "Optimized simulation completed. Output written to simulate_output.txt";
