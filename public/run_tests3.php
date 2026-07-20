<?php
header('Content-Type: application/json');

try {
    $phpunitPath = realpath(__DIR__ . '/../vendor/bin/phpunit');
    $testPath = realpath(__DIR__ . '/../tests/Feature/Admin/TeacherRouteAlignmentAndParentLoginTest.php');
    
    $cmd = 'php "' . $phpunitPath . '" "' . $testPath . '" 2>&1';
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
