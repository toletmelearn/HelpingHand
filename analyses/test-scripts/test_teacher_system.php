<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Teacher;
use App\Models\TeacherLogin;
use App\Models\TeacherClassSubjectAssignment;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║     TEACHER SYSTEM - REAL TESTING & VERIFICATION        ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// ============================================
// TEST 1: CHECK TEACHER LOGIN SYSTEM
// ============================================
echo "📋 TEST 1: TEACHER LOGIN SYSTEM\n";
echo "──────────────────────────────────────────────────────────\n";

$totalTeachers = Teacher::count();
$totalLogins = TeacherLogin::count();

// Check if all teachers have logins
$teachersWithoutLogin = Teacher::whereNotIn('id', TeacherLogin::pluck('teacher_id'))->count();

echo "✓ Total Teachers: {$totalTeachers}\n";
echo "✓ Total Teacher Logins: {$totalLogins}\n";
echo "✓ Teachers WITHOUT Login: {$teachersWithoutLogin}\n";

if ($teachersWithoutLogin > 0) {
    echo "⚠️ WARNING: Some teachers don't have logins!\n";
} else {
    echo "✅ ALL teachers have login accounts\n";
}

// Check password hashing
$sampleLogin = TeacherLogin::first();
if ($sampleLogin) {
    echo "\n✓ Sample Login Check:\n";
    echo "  - Username: {$sampleLogin->username}\n";
    echo "  - Password Hashed: " . (Hash::needsRehash($sampleLogin->password) ? 'NO' : 'YES') . "\n";
    echo "  - Force Password Change: " . ($sampleLogin->force_password_change ? 'YES' : 'NO') . "\n";
    
    // Verify Hash::check works
    $passwordCheck = Hash::check('123456', $sampleLogin->password);
    echo "  - Default Password (123456) Valid: " . ($passwordCheck ? 'YES' : 'NO') . "\n";
}

// ============================================
// TEST 2: CHECK ASSIGNMENT SYSTEM
// ============================================
echo "\n\n📋 TEST 2: ASSIGNMENT SYSTEM\n";
echo "──────────────────────────────────────────────────────────\n";

$totalAssignments = TeacherClassSubjectAssignment::count();
echo "✓ Total Assignments: {$totalAssignments}\n";

// Check for duplicate assignments
$duplicates = TeacherClassSubjectAssignment::select('teacher_id', 'class_id', 'section_id', 'subject_id', 'academic_year')
    ->groupBy('teacher_id', 'class_id', 'section_id', 'subject_id', 'academic_year')
    ->havingRaw('COUNT(*) > 1')
    ->get();

echo "✓ Duplicate Assignments: " . $duplicates->count() . "\n";

// Show sample assignments
$sampleAssignments = TeacherClassSubjectAssignment::with(['teacher', 'schoolClass', 'subject'])->take(5)->get();
echo "\n✓ Sample Assignments:\n";
foreach ($sampleAssignments as $assignment) {
    echo "  - {$assignment->teacher->name} → {$assignment->schoolClass->name} → {$assignment->subject->name}\n";
    echo "    Class Teacher: " . ($assignment->is_class_teacher ? 'YES' : 'NO') . 
         " | Primary: " . ($assignment->is_primary_subject_teacher ? 'YES' : 'NO') . "\n";
}

// ============================================
// TEST 3: CHECK MULTIPLE ASSIGNMENTS PER TEACHER
// ============================================
echo "\n\n📋 TEST 3: MULTIPLE ASSIGNMENTS PER TEACHER\n";
echo "──────────────────────────────────────────────────────────\n";

$teacherAssignments = TeacherClassSubjectAssignment::select('teacher_id')
    ->selectRaw('COUNT(*) as assignment_count')
    ->groupBy('teacher_id')
    ->having('assignment_count', '>', 1)
    ->orderBy('assignment_count', 'desc')
    ->take(5)
    ->get();

echo "✓ Teachers with Multiple Assignments:\n";
foreach ($teacherAssignments as $ta) {
    $teacher = Teacher::find($ta->teacher_id);
    echo "  - {$teacher->name}: {$ta->assignment_count} assignments\n";
}

// ============================================
// TEST 4: VERIFY DATABASE COLUMNS
// ============================================
echo "\n\n📋 TEST 4: DATABASE STRUCTURE\n";
echo "──────────────────────────────────────────────────────────\n";

$columns = Schema::getColumnListing('teacher_class_subject_assignments');
$requiredColumns = [
    'id', 'teacher_id', 'class_id', 'section_id', 
    'subject_id', 'is_class_teacher', 'is_primary_subject_teacher', 
    'academic_year', 'created_at', 'updated_at'
];

echo "✓ Required Columns Check:\n";
foreach ($requiredColumns as $col) {
    $exists = in_array($col, $columns);
    echo "  - {$col}: " . ($exists ? '✅' : '❌') . "\n";
}

// ============================================
// TEST 5: CREATE TEST TEACHERS (IF NEEDED)
// ============================================
echo "\n\n📋 TEST 5: CREATE 3 TEST TEACHERS\n";
echo "──────────────────────────────────────────────────────────\n";

$testPhones = ['9000000001', '9000000002', '9000000003'];

foreach ($testPhones as $index => $phone) {
    $existing = Teacher::where('phone', $phone)->first();
    
    if (!$existing) {
        $teacher = Teacher::create([
            'name' => 'Test Teacher ' . ($index + 1),
            'email' => 'testteacher' . ($index + 1) . '@school.com',
            'phone' => $phone,
            'designation' => 'Teacher',
            'status' => 'active',
        ]);
        
        TeacherLogin::create([
            'teacher_id' => $teacher->id,
            'username' => $phone,
            'password' => Hash::make('123456'),
            'status' => 'active',
            'force_password_change' => true,
        ]);
        
        echo "✅ Created Test Teacher " . ($index + 1) . " (Phone: {$phone})\n";
    } else {
        // Ensure login exists
        $login = TeacherLogin::where('teacher_id', $existing->id)->first();
        if (!$login) {
            TeacherLogin::create([
                'teacher_id' => $existing->id,
                'username' => $phone,
                'password' => Hash::make('123456'),
                'status' => 'active',
                'force_password_change' => true,
            ]);
            echo "✅ Created login for existing Test Teacher " . ($index + 1) . "\n";
        } else {
            echo "✓ Test Teacher " . ($index + 1) . " already exists (Phone: {$phone})\n";
        }
    }
}

// ============================================
// SUMMARY
// ============================================
echo "\n\n╔══════════════════════════════════════════════════════════╗\n";
echo "║                      TEST SUMMARY                        ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";

echo "\n✅ SYSTEM STATUS:\n";
echo "  - Teachers: {$totalTeachers}\n";
echo "  - Teacher Logins: {$totalLogins}\n";
echo "  - Assignments: {$totalAssignments}\n";
echo "  - Test Teachers: 3 (9000000001, 9000000002, 9000000003)\n";

echo "\n🔧 LOGIN CREDENTIALS FOR TESTING:\n";
echo "  Teacher 1: 9000000001 / 123456\n";
echo "  Teacher 2: 9000000002 / 123456\n";
echo "  Teacher 3: 9000000003 / 123456\n";

echo "\n📍 IMPORTANT URLS:\n";
echo "  Teacher Login: http://127.0.0.1:8000/teacher/login\n";
echo "  Admin Assignments: http://127.0.0.1:8000/admin/teacher-subject-assignments\n";

echo "\n";
