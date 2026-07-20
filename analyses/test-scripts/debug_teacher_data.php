<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TEACHER ASSIGNMENTS (teacher_id=1) ===\n";
$assignments = DB::table('teacher_class_subject_assignments')->where('teacher_id', 1)->get();
foreach($assignments as $a) {
    echo "ID: $a->id | Teacher: $a->teacher_id | Class: $a->class_id | Subject: $a->subject_id\n";
}

echo "\n=== SCHOOL CLASSES ===\n";
$classes = DB::table('school_classes')->get();
foreach($classes as $c) {
    echo "ID: $c->id | Name: $c->name\n";
}

echo "\n=== SUBJECTS ===\n";
$subjects = DB::table('subjects')->get();
foreach($subjects as $s) {
    echo "ID: $s->id | Name: $s->name\n";
}
