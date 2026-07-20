<?php
require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use App\Models\ParentModel;
use Illuminate\Support\Facades\Hash;

// Set the Laravel application context
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Updating all parent passwords to '123456'...\n";

$updatedCount = 0;
$parents = ParentModel::all();

foreach ($parents as $parent) {
    $parent->password = Hash::make('123456');
    $parent->save();
    $updatedCount++;
    echo "Updated: {$parent->name} ({$parent->mobile})\n";
}

echo "\n=== COMPLETE ===\n";
echo "Updated {$updatedCount} parent passwords successfully!\n";
echo "All parents can now login with password: 123456\n";