<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Models\User;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BackupRunCommand extends Command
{
    protected $signature = 'backup:run {--notes= : Optional note stored on the backup record}';

    protected $description = 'Create a real database backup and prune old backups per retention policy.';

    public function handle(DatabaseBackupService $service): int
    {
        // backups.created_by is NOT NULL with a foreign key to users -- a
        // scheduled/console run has no authenticated session, so it's
        // attributed to the first admin account found rather than left
        // unattributed. Reported explicitly, not silently assumed.
        $createdBy = Auth::id() ?? User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->value('id');

        if ($createdBy === null) {
            $this->error('No admin user exists to attribute this scheduled backup to. Aborting.');

            return self::FAILURE;
        }

        $backup = Backup::create([
            'filename' => 'backup_' . now()->format('Y-m-d_H-i-s') . '_' . Str::random(8) . '.zip',
            'path' => 'backups/' . now()->format('Y/m/d'),
            'type' => 'database',
            'location' => 'local',
            'size' => 0,
            'status' => 'pending',
            'notes' => $this->option('notes'),
            'created_by' => $createdBy,
        ]);

        try {
            $service->create($backup);
        } catch (\Throwable $e) {
            $this->error("Backup #{$backup->id} failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Backup #{$backup->id} completed: {$backup->filename}");

        $pruned = $service->pruneOldBackups((int) config('backup.retention_count', 14));
        if ($pruned > 0) {
            $this->info("Pruned {$pruned} old backup(s) beyond retention.");
        }

        return self::SUCCESS;
    }
}
