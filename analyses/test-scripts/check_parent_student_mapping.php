<?php
require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

// Set the Laravel application context
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CHECKING PARENT-TO-STUDENT MAPPING ===\n\n";

// Check the specific working parent
$workingParentCheck = DB::select('SELECT parents.id as parent_id, parents.mobile, parents.student_id, students.name, students.class, students.admission_no FROM parents LEFT JOIN students ON parents.student_id = students.id WHERE parents.mobile = ?', ['9842950777']);

echo "Working parent (9842950777) mapping:\n";
if (empty($workingParentCheck)) {
    echo "  No mapping found for this parent.\n";
} else {
    foreach ($workingParentCheck as $row) {
        echo "  Parent ID: {$row->parent_id}\n";
        echo "  Mobile: {$row->mobile}\n";
        echo "  Student ID (linked): {$row->student_id}\n";
        echo "  Student Name: {$row->name}\n";
        echo "  Student Class: {$row->class}\n";
        echo "  Student Admission: {$row->admission_no}\n";
    }
}

echo "\nChecking a few other parent mappings:\n";
$otherParents = DB::select('SELECT parents.id as parent_id, parents.mobile, parents.student_id, students.name, students.class, students.admission_no FROM parents LEFT JOIN students ON parents.student_id = students.id WHERE parents.mobile != ? LIMIT 5', ['9842950777']);

foreach ($otherParents as $row) {
    echo "  Parent Mobile: {$row->mobile}\n";
    echo "  Linked Student: {$row->name} (Class: {$row->class}, ID: {$row->student_id})\n\n";
}

// Count total parents without student links
$orphanedParents = DB::table('parents')->whereNull('student_id')->count();
echo "\nTotal parents without student links: {$orphanedParents}\n";

$totalParents = DB::table('parents')->count();
echo "Total parents: {$totalParents}\n";

echo "\n=== MAPPING ANALYSIS COMPLETE ===\n";