<?php
// Run php artisan route:list --json directly
$routesJson = shell_exec('php artisan route:list --json');
$after = json_decode($routesJson, true);

if (!$after) {
    echo "Failed to retrieve routes. Output was: " . substr($routesJson, 0, 500) . "\n";
    exit(1);
}

// Compare current routes against our expected list or check specifically for the target routes
$foundExamAvailable = false;
$foundAdminExamAvailableForClass = false;

foreach ($after as $r) {
    if ($r['uri'] === 'exam-papers/available') {
        $foundExamAvailable = true;
        echo "=== Found route: exam-papers/available ===\n";
        echo "Name: " . $r['name'] . "\n";
        echo "Action: " . $r['action'] . "\n";
        echo "Middleware: " . implode(', ', $r['middleware']) . "\n\n";
    }
    if ($r['uri'] === 'admin/exam-papers/available-for-class') {
        $foundAdminExamAvailableForClass = true;
        echo "=== Found route: admin/exam-papers/available-for-class ===\n";
        echo "Name: " . $r['name'] . "\n";
        echo "Action: " . $r['action'] . "\n";
        echo "Middleware: " . implode(', ', $r['middleware']) . "\n\n";
    }
}

if (!$foundExamAvailable) {
    echo "❌ Route exam-papers/available NOT found!\n";
}
if (!$foundAdminExamAvailableForClass) {
    echo "❌ Route admin/exam-papers/available-for-class NOT found!\n";
}

echo "Total route count: " . count($after) . "\n";
