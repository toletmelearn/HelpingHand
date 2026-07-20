<?php
header('Content-Type: application/json');

$paths = [
    'vendor/bin/phpunit',
    'vendor/bin/phpunit.bat',
    'vendor/phpunit/phpunit/phpunit',
    'vendor/phpunit/phpunit/src/TextUI/Command.php'
];

$found = [];
foreach ($paths as $path) {
    $full = __DIR__ . '/../' . $path;
    $found[$path] = [
        'exists' => file_exists($full),
        'path' => realpath($full)
    ];
}

echo json_encode($found, JSON_PRETTY_PRINT);
