<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TEACHER TABLES ===\n";
$tables = DB::select("SHOW TABLES LIKE '%teacher%'");
foreach($tables as $table) {
    $tableName = array_values((array)$table)[0];
    echo $tableName . "\n";
}

echo "\n=== TEACHER_CLASS_SUBJECT_ASSIGNMENTS STRUCTURE ===\n";
$columns = DB::select('DESCRIBE teacher_class_subject_assignments');
foreach($columns as $col) {
    echo $col->Field . " | " . $col->Type . " | " . $col->Null . " | " . $col->Key . "\n";
}

echo "\n=== SAMPLE DATA (teacher_id=1) ===\n";
$assignments = DB::table('teacher_class_subject_assignments')->where('teacher_id', 1)->get();
foreach($assignments as $a) {
    echo "ID: $a->id | Teacher: $a->teacher_id | Class: $a->class_id | Subject: $a->subject_id\n";
}
