<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

// Bootstrap Laravel
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$output = "Programmatic Policy & Gate Verification\n";
$output .= "=======================================\n\n";

try {
    $clerk = \App\Models\User::role('clerk')->first();
    $accountant = \App\Models\User::role('accountant')->first();
    
    $users = [];
    if ($clerk) $users['Clerk (' . $clerk->email . ')'] = $clerk;
    if ($accountant) $users['Accountant (' . $accountant->email . ')'] = $accountant;
    
    if (empty($users)) {
        $output .= "No clerk or accountant users found in database!\n";
        file_put_contents(__DIR__ . '/policy_check_output.txt', $output);
        exit;
    }
    
    // We can define the models and abilities we want to check
    $checks = [
        \App\Models\Student::class => ['viewAny', 'create', 'view', 'update', 'delete'],
        \App\Models\Teacher::class => ['viewAny', 'create', 'view', 'update', 'delete'],
        \App\Models\Attendance::class => ['viewAny', 'create', 'view', 'update', 'delete'],
        \App\Models\Exam::class => ['viewAny', 'create', 'view', 'update', 'delete'],
        \App\Models\ExamPaper::class => ['viewAny', 'create', 'view', 'update', 'delete'],
        \App\Models\Result::class => ['viewAny', 'create', 'view', 'update', 'delete'],
        \App\Models\Book::class => ['viewAny', 'create', 'view', 'update', 'delete'],
        \App\Models\BookIssue::class => ['viewAny', 'create', 'view', 'update', 'delete'],
        \App\Models\Budget::class => ['viewAny', 'create', 'view', 'update', 'delete'],
        \App\Models\Expense::class => ['viewAny', 'create', 'view', 'update', 'delete'],
    ];
    
    foreach ($users as $name => $user) {
        $output .= "### Testing policies for {$name} ###\n";
        $output .= "Roles: " . implode(', ', $user->roles->pluck('name')->toArray()) . "\n";
        
        foreach ($checks as $modelClass => $abilities) {
            $modelName = class_basename($modelClass);
            $output .= "Model: {$modelName}\n";
            
            // Try to find an instance of the model for view/update/delete checks
            $instance = null;
            try {
                $instance = $modelClass::first();
            } catch (\Throwable $t) {
                // Ignore if table doesn't exist or other error
            }
            
            foreach ($abilities as $ability) {
                try {
                    $allowed = false;
                    if (in_array($ability, ['viewAny', 'create'])) {
                        $allowed = \Illuminate\Support\Facades\Gate::forUser($user)->allows($ability, $modelClass);
                    } else {
                        if ($instance) {
                            $allowed = \Illuminate\Support\Facades\Gate::forUser($user)->allows($ability, $instance);
                        } else {
                            $allowed = "No model instance available for testing";
                        }
                    }
                    
                    $status = is_bool($allowed) ? ($allowed ? 'ALLOWED' : 'DENIED') : $allowed;
                    $output .= "  - {$ability}: {$status}\n";
                } catch (\Throwable $t) {
                    $output .= "  - {$ability}: ERROR (" . $t->getMessage() . ")\n";
                }
            }
            $output .= "\n";
        }
        $output .= "---------------------------------------------\n\n";
    }
    
} catch (\Throwable $e) {
    $output .= "Fatal Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

file_put_contents(__DIR__ . '/policy_check_output.txt', $output);
echo "Policy checks completed. Output written to policy_check_output.txt";
