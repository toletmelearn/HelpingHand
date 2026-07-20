<?php
header('Content-Type: application/json');

try {
    $cmd = 'php vendor/phpunit/phpunit/phpunit tests/Feature/Admin/TeacherRouteAlignmentAndParentLoginTest.php 2>&1';
    $output = shell_exec($cmd);

    echo json_encode([
        'success' => true,
        'command' => $cmd,
        'output' => $output
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
