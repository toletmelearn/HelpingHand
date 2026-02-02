<?php
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;
use App\Models\SchoolClass;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TESTING PROMOTION LOGIC ===\n\n";

$sourceClass = SchoolClass::where('name', 'Class 5')->first();
echo "Source: {$sourceClass->name} (Order: {$sourceClass->class_order})\n";

$destClasses = SchoolClass::where('class_order', '>', $sourceClass->class_order)
    ->active()
    ->orderByOrder()
    ->get();

echo "Destinations:\n";
foreach($destClasses as $class) {
    echo "  - {$class->name} (Order: {$class->class_order})\n";
}

echo "\n=== PROMOTION LOGIC TEST COMPLETE ===\n";