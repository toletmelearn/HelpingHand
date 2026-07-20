<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEACHER_LOGINS TABLE STRUCTURE ===\n";
$columns = DB::select('SHOW COLUMNS FROM teacher_logins');
foreach($columns as $col) {
    echo $col->Field . ' (' . $col->Type . ")\n";
}

echo "\n=== TEACHERS TABLE STRUCTURE ===\n";
$columns = DB::select('SHOW COLUMNS FROM teachers');
foreach($columns as $col) {
    echo $col->Field . ' (' . $col->Type . ")\n";
}

echo "\n=== SAMPLE DATA FROM TEACHER_LOGINS ===\n";
$logins = DB::table('teacher_logins')->limit(3)->get();
foreach($logins as $login) {
    echo "ID: {$login->id}, Name: {$login->name}, Email: {$login->email}";
    if (isset($login->teacher_id)) {
        echo ", Teacher ID: {$login->teacher_id}";
    }
    echo "\n";
}