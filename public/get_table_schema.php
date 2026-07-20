<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $results = DB::select("SHOW CREATE TABLE fee_collections");
    echo "Table Schema:\n";
    print_r($results[0]);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
