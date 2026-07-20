<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking teacher_id mappings...\n";

// Check if all teacher_logins have valid teacher_id references
$invalid = DB::table('teacher_logins as tl')
    ->leftJoin('teachers as t', 'tl.teacher_id', '=', 't.id')
    ->whereNull('t.id')
    ->count();

echo "Invalid mappings: " . $invalid . "\n";

if ($invalid > 0) {
    echo "Found $invalid invalid mappings. These need to be fixed.\n";
    
    // Show some examples
    $examples = DB::table('teacher_logins as tl')
        ->leftJoin('teachers as t', 'tl.teacher_id', '=', 't.id')
        ->whereNull('t.id')
        ->select('tl.id', 'tl.teacher_id', 'tl.username')
        ->limit(5)
        ->get();
        
    echo "Sample invalid records:\n";
    foreach($examples as $example) {
        echo "  Login ID: {$example->id}, Teacher ID: {$example->teacher_id}, Username: {$example->username}\n";
    }
} else {
    echo "All teacher_id mappings are valid!\n";
}