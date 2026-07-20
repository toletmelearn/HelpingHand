<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = \App\Models\SchoolClass::select('id', 'name', 'class_order', 'is_active')->orderBy('class_order')->get();
echo "id | name | class_order | is_active\n";
echo "---|---|---|---\n";
foreach($rows as $r) {
    echo "{$r->id} | {$r->name} | {$r->class_order} | {$r->is_active}\n";
}
