<?php

require 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEACHER_LOGINS ===\n";
$logins = DB::table('teacher_logins')->select('id', 'email')->get();
foreach($logins as $login) {
    echo $login->id . ' | ' . $login->email . "\n";
}

echo "\n=== TEACHERS ===\n";
$teachers = DB::table('teachers')->select('id', 'email')->get();
foreach($teachers as $teacher) {
    echo $teacher->id . ' | ' . $teacher->email . "\n";
}

echo "\n=== MATCHING ANALYSIS ===\n";
$login_emails = [];
foreach($logins as $login) {
    $login_emails[$login->email] = $login->id;
}

$teacher_emails = [];
foreach($teachers as $teacher) {
    $teacher_emails[$teacher->email] = $teacher->id;
}

echo "Teacher logins with matching teacher records:\n";
$matches = 0;
foreach($login_emails as $email => $login_id) {
    if(isset($teacher_emails[$email])) {
        echo "✓ Login ID {$login_id} ({$email}) -> Teacher ID {$teacher_emails[$email]}\n";
        $matches++;
    } else {
        echo "✗ Login ID {$login_id} ({$email}) -> NO MATCH in teachers table\n";
    }
}

echo "\nTotal matches: {$matches} / " . count($login_emails) . "\n";