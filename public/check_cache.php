<?php
header('Content-Type: application/json');

$configCachePath = __DIR__ . '/../bootstrap/cache/config.php';
$exists = file_exists($configCachePath);

if ($exists) {
    // Attempt to delete it to clear config cache
    @unlink($configCachePath);
    $deleted = !file_exists($configCachePath);
} else {
    $deleted = false;
}

echo json_encode([
    'success' => true,
    'config_cache_exists' => $exists,
    'config_cache_deleted' => $deleted
], JSON_PRETTY_PRINT);
