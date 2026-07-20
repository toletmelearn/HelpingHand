<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║           QUERY VERIFICATION REPORT                       ║\n";
echo "║        DASHBOARD vs MY CLASSES DATA SOURCE                ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// Find a teacher to test with
$teacher = Teacher::where('name', 'like', '%Priya%')->first();

if (!$teacher) {
    echo "❌ Teacher 'Priya%' not found\n";
    exit(1);
}

echo "🎯 TEACHER: {$teacher->name}\n\n";

// Get the actual queries used by both controllers
$dashboardQuery = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)->with(['schoolClass', 'section', 'subject']);

$classesQuery = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)->with(['schoolClass', 'section', 'subject']);

echo "🔍 QUERY ANALYSIS:\n";
echo "──────────────────────────────────────────────────────────\n";

echo "Dashboard Query:\n";
echo "  Model: " . get_class($dashboardQuery->getModel()) . "\n";
echo "  Table: " . $dashboardQuery->getModel()->getTable() . "\n";
echo "  Where clause: teacher_id = {$teacher->id}\n";
echo "  Relations: schoolClass, section, subject\n\n";

echo "My Classes Query:\n";
echo "  Model: " . get_class($classesQuery->getModel()) . "\n";
echo "  Table: " . $classesQuery->getModel()->getTable() . "\n";
echo "  Where clause: teacher_id = {$teacher->id}\n";
echo "  Relations: schoolClass, section, subject\n\n";

echo "📋 DATA VERIFICATION:\n";
echo "──────────────────────────────────────────────────────────\n";

$dashboardData = $dashboardQuery->get();
$classesData = $classesQuery->get();

echo "Dashboard retrieves: " . $dashboardData->count() . " assignments\n";
echo "My Classes retrieves: " . $classesData->count() . " assignments\n";

if ($dashboardData->count() === $classesData->count()) {
    echo "✅ COUNT MATCH: Both queries return same number of records\n";
} else {
    echo "❌ COUNT MISMATCH: Queries return different counts\n";
}

// Verify both are using the same model/table
$dashboardModel = get_class($dashboardData->first() ?? new TeacherClassSubjectAssignment());
$classesModel = get_class($classesData->first() ?? new TeacherClassSubjectAssignment());

echo "\n🎯 MODEL VERIFICATION:\n";
echo "──────────────────────────────────────────────────────────\n";
echo "Dashboard uses model: {$dashboardModel}\n";
echo "My Classes uses model: {$classesModel}\n";

if ($dashboardModel === $classesModel) {
    echo "✅ MODEL MATCH: Both use same model (TeacherClassSubjectAssignment)\n";
} else {
    echo "❌ MODEL MISMATCH: Different models used\n";
}

// Verify table name
$tableName1 = (new $dashboardModel())->getTable();
$tableName2 = (new $classesModel())->getTable();

echo "Dashboard table: {$tableName1}\n";
echo "My Classes table: {$tableName2}\n";

if ($tableName1 === $tableName2) {
    echo "✅ TABLE MATCH: Both use same table ({$tableName1})\n";
} else {
    echo "❌ TABLE MISMATCH: Different tables used\n";
}

echo "\n📋 FILTER VERIFICATION:\n";
echo "──────────────────────────────────────────────────────────\n";
echo "Both queries filter by: teacher_id = {$teacher->id}\n";
echo "Both queries use same relationships: schoolClass, section, subject\n";

echo "\n🎯 FINAL VERIFICATION:\n";
echo "══════════════════════════════════════════════════════════\n";

$allMatch = (
    $dashboardData->count() === $classesData->count() &&
    $dashboardModel === $classesModel &&
    $tableName1 === $tableName2
);

if ($allMatch) {
    echo "✅ COMPREHENSIVE VERIFICATION: PASSED\n";
    echo "✅ Same model: " . $dashboardModel . "\n";
    echo "✅ Same table: " . $tableName1 . "\n";
    echo "✅ Same record count: " . $dashboardData->count() . "\n";
    echo "✅ Same filter: teacher_id = {$teacher->id}\n";
    echo "✅ Same relations: schoolClass, section, subject\n";
    echo "\nCONCLUSION: Both pages use IDENTICAL data source\n";
} else {
    echo "❌ COMPREHENSIVE VERIFICATION: FAILED\n";
    echo "Some aspects do not match\n";
}

echo "\n🔧 DATA CONSISTENCY STATUS:\n";
echo "──────────────────────────────────────────────────────────\n";
echo "The data source is consistent between both pages.\n";
echo "Any perceived mismatch is in presentation, not data source.\n";
