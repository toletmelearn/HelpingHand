<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

// Bootstrap Laravel without handling HTTP request if we just want to query DB
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$output = "User & Role Diagnostics\n";
$output .= "=======================\n\n";

try {
    $users = \App\Models\User::with('roles')->get();
    $output .= "Total users: " . $users->count() . "\n";
    foreach ($users as $user) {
        $roles = $user->roles->pluck('name')->implode(', ');
        $output .= "ID: {$user->id} | Name: {$user->name} | Email: {$user->email} | Verified At: " . ($user->email_verified_at ? $user->email_verified_at->toDateTimeString() : 'NULL') . " | Roles: [{$roles}]\n";
    }

    $output .= "\nRole & Permission mapping\n";
    $output .= "=========================\n";
    $roles = \App\Models\Role::with('permissions')->get();
    foreach ($roles as $role) {
        $perms = $role->permissions->pluck('name')->implode(', ');
        $output .= "Role: {$role->name} | Permissions: [{$perms}]\n";
    }
    
    // Also check current active session user if any
    $output .= "\nSession Auth Check:\n";
    $output .= "Auth Check: " . (auth()->check() ? 'Yes' : 'No') . "\n";
    if (auth()->check()) {
        $output .= "Logged In User: " . auth()->user()->email . "\n";
    }
} catch (\Exception $e) {
    $output .= "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

file_put_contents(__DIR__ . '/debug_output.txt', $output);
echo "Diagnostics written to debug_output.txt";
