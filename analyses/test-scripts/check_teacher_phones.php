<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Teacher;
use App\Models\TeacherLogin;

echo "📊 Teacher Login Status Report\n\n";

$totalTeachers = Teacher::count();
$teachersWithPhone = Teacher::whereNotNull('phone')->where('phone', '!=', '')->count();
$teachersWithoutPhone = $totalTeachers - $teachersWithPhone;
$existingLogins = TeacherLogin::count();
$teachersWithLogins = Teacher::whereIn('id', TeacherLogin::pluck('teacher_id'))->count();
$teachersWithoutLogins = $totalTeachers - $teachersWithLogins;

echo "Total Teachers: {$totalTeachers}\n";
echo "Teachers with Phone: {$teachersWithPhone}\n";
echo "Teachers without Phone: {$teachersWithoutPhone}\n";
echo "Existing Logins: {$existingLogins}\n";
echo "Teachers WITH Login: {$teachersWithLogins}\n";
echo "Teachers WITHOUT Login: {$teachersWithoutLogins}\n\n";

// Sample first 5 teachers with phone
echo "Sample Teachers with Phone Numbers:\n";
$sampleTeachers = Teacher::whereNotNull('phone')->where('phone', '!=', '')->take(5)->get();
foreach ($sampleTeachers as $t) {
    echo "  - #{$t->id}: {$t->name} (Phone: {$t->phone})\n";
}

echo "\nSample Teachers without Phone Numbers:\n";
$sampleNoPhone = Teacher::where(function($q) { $q->whereNull('phone')->orWhere('phone', ''); })->take(5)->get();
foreach ($sampleNoPhone as $t) {
    echo "  - #{$t->id}: {$t->name} (Phone: [EMPTY])\n";
}
