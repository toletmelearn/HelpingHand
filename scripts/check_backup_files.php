<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;

$backups = \App\Models\Backup::orderBy('id','desc')->get();
$out = [];
foreach ($backups as $b) {
    $rel = $b->path . $b->filename;
    $storageExists = Storage::exists($rel) ? 'yes' : 'no';
    $fileExists = file_exists(storage_path('app/' . $rel)) ? 'yes' : 'no';
    $out[] = [
        'id' => $b->id,
        'filename' => $b->filename,
        'path' => $b->path,
        'status' => $b->status,
        'storage_exists' => $storageExists,
        'file_exists' => $fileExists,
        'full_path' => storage_path('app/' . $rel),
    ];
}
echo json_encode($out, JSON_PRETTY_PRINT);
