<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\Schema;

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║      TEACHER ASSIGNMENT SYSTEM - PROFESSIONAL AUDIT      ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// 1. Check all teachers with class teacher assignments
echo "📋 ALL TEACHERS WITH CLASS TEACHER ASSIGNMENTS\n";
echo "──────────────────────────────────────────────────────────\n";

$teachersWithClassAssignments = TeacherClassSubjectAssignment::select('teacher_id')
    ->where('is_class_teacher', true)
    ->groupBy('teacher_id')
    ->get();

foreach ($teachersWithClassAssignments as $ta) {
    $teacher = Teacher::find($ta->teacher_id);
    $count = TeacherClassSubjectAssignment::where('teacher_id', $ta->teacher_id)
        ->where('is_class_teacher', true)
        ->count();
    
    $status = $count <= 2 ? "✅ OK" : "❌ VIOLATION ({$count})";
    echo "{$teacher->name}: {$count} class teacher assignments {$status}\n";
    
    // Show details
    $assignments = TeacherClassSubjectAssignment::where('teacher_id', $ta->teacher_id)
        ->where('is_class_teacher', true)
        ->with(['schoolClass', 'section'])
        ->get();
    
    foreach ($assignments as $assignment) {
        $section = $assignment->section ? $assignment->section->name : 'All Sections';
        echo "  └─ {$assignment->schoolClass->name} (Section: {$section})\n";
    }
    echo "\n";
}

// 2. Check for duplicate assignments
echo "📋 DUPLICATE ASSIGNMENT ANALYSIS\n";
echo "──────────────────────────────────────────────────────────\n";

$duplicates = TeacherClassSubjectAssignment::select('teacher_id', 'class_id', 'section_id', 'subject_id', 'academic_year')
    ->groupBy('teacher_id', 'class_id', 'section_id', 'subject_id', 'academic_year')
    ->havingRaw('COUNT(*) > 1')
    ->get();

echo "Duplicate assignments found: " . $duplicates->count() . "\n";

if ($duplicates->count() > 0) {
    foreach ($duplicates as $dup) {
        $teacher = Teacher::find($dup->teacher_id);
        $class = SchoolClass::find($dup->class_id);
        echo "❌ Duplicate: {$teacher->name} → {$class->name}\n";
    }
}

echo "\n";

// 3. Check current assignment logic issues
echo "📋 CURRENT ASSIGNMENT LOGIC ANALYSIS\n";
echo "──────────────────────────────────────────────────────────\n";

// Check if the controller logic properly prevents auto class teacher assignment
$controllerContent = file_get_contents(__DIR__ . '/app/Http/Controllers/Admin/TeacherSubjectAssignmentController.php');

$hasProperLogic = [
    'Class teacher limit check' => strpos($controllerContent, 'Maximum 2 class teacher assignments allowed') !== false,
    'Existing class teacher counting' => strpos($controllerContent, 'existingClassTeacherCount') !== false,
    'Conditional is_class_teacher assignment' => strpos($controllerContent, 'Only set is_class_teacher if checkbox checked') !== false,
    'updateOrCreate implementation' => strpos($controllerContent, 'updateOrCreate') !== false,
];

foreach ($hasProperLogic as $check => $result) {
    echo ($result ? "✅" : "❌") . " {$check}\n";
}

echo "\n";

// 4. Check database structure
echo "📋 DATABASE STRUCTURE VERIFICATION\n";
echo "──────────────────────────────────────────────────────────\n";

$columns = Schema::getColumnListing('teacher_class_subject_assignments');
$requiredColumns = [
    'id', 'teacher_id', 'class_id', 'section_id', 'subject_id',
    'is_class_teacher', 'is_primary_subject_teacher', 'academic_year'
];

echo "Required columns:\n";
foreach ($requiredColumns as $col) {
    $exists = in_array($col, $columns);
    echo "  " . ($exists ? "✅" : "❌") . " {$col}\n";
}

echo "\n";

// 5. Check for data inconsistencies
echo "📋 DATA CONSISTENCY CHECK\n";
echo "──────────────────────────────────────────────────────────\n";

// Check if same class has multiple class teachers
$classTeacherConflicts = TeacherClassSubjectAssignment::select('class_id', 'section_id', 'academic_year')
    ->where('is_class_teacher', true)
    ->groupBy('class_id', 'section_id', 'academic_year')
    ->havingRaw('COUNT(*) > 1')
    ->get();

echo "Classes with multiple class teachers: " . $classTeacherConflicts->count() . "\n";

if ($classTeacherConflicts->count() > 0) {
    foreach ($classTeacherConflicts as $conflict) {
        $class = SchoolClass::find($conflict->class_id);
        $section = $conflict->section_id ? \App\Models\Section::find($conflict->section_id)->name : 'All';
        echo "❌ Conflict: {$class->name} (Section: {$section}) has multiple class teachers\n";
    }
}

echo "\n";

// Summary
echo "📋 AUDIT SUMMARY\n";
echo "──────────────────────────────────────────────────────────\n";
echo "Total assignments: " . TeacherClassSubjectAssignment::count() . "\n";
echo "Total class teachers: " . TeacherClassSubjectAssignment::where('is_class_teacher', true)->count() . "\n";
echo "Teachers violating class teacher limit: " . $teachersWithClassAssignments->filter(function($ta) {
    return TeacherClassSubjectAssignment::where('teacher_id', $ta->teacher_id)
        ->where('is_class_teacher', true)
        ->count() > 2;
})->count() . "\n";

$allChecks = array_sum($hasProperLogic);
$totalChecks = count($hasProperLogic);

echo "\nLogic implementation score: {$allChecks}/{$totalChecks}\n";

if ($allChecks == $totalChecks) {
    echo "✅ Controller logic appears correct\n";
} else {
    echo "⚠️  Controller logic needs review\n";
}

echo "\n🔧 RECOMMENDATIONS:\n";
echo "1. Clean existing data violations\n";
echo "2. Implement proper duplicate prevention\n";
echo "3. Fix dashboard display logic\n";
echo "4. Ensure updateOrCreate works correctly\n";
