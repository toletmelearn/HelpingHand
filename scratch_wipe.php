<?php
// Bootstrap Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
    $tables = DB::select('SHOW TABLES');
    $dbName = config('database.connections.mariadb.database');
    $keyName = "Tables_in_" . $dbName;
    foreach ($tables as $table) {
        $name = $table->$keyName;
        DB::statement("DROP TABLE IF EXISTS `{$name}`");
    }
    DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    echo "Wiped database successfully!\n";
} catch (\Exception $e) {
    echo "Error wiping database: " . $e->getMessage() . "\n";
}
