<?php
require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

// Set the Laravel application context
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== UPDATING OLD EXAMS WITH DEFAULT VALUES ===\n\n";

// Update old exams to have default academic year and term
$updatedCount = DB::table('exams')
    ->whereNull('academic_year')
    ->orWhere('academic_year', '')
    ->update([
        'academic_year' => date('Y').'-'.(date('Y')+1),
        'term' => 'Term 2'
    ]);

echo "Updated {$updatedCount} exam records with default academic year and term.\n";

// Verify the updates
$examCount = DB::table('exams')->count();
$nullAcademicYearCount = DB::table('exams')
    ->whereNull('academic_year')
    ->orWhere('academic_year', '')
    ->count();

echo "Total exams: {$examCount}\n";
echo "Exams with null/empty academic_year: {$nullAcademicYearCount}\n";

if ($nullAcademicYearCount == 0) {
    echo "✅ All exams now have academic year values!\n";
} else {
    echo "⚠️  Some exams still have null academic year values.\n";
}

echo "\n=== UPDATE COMPLETE ===\n";