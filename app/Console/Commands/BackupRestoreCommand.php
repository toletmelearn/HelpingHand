<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Backup\DatabaseRestoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * CLI-ONLY database restore. Deliberately no web/controller/route path to
 * this exists anywhere in the application -- CLI access already implies
 * server/credential-level trust, a materially higher bar than a browser
 * session, and this operation is destructive to whatever currently lives
 * in the target database.
 */
class BackupRestoreCommand extends Command
{
    protected $signature = 'backup:restore
        {backup_id : The Backup record id to restore from}
        {--target-database= : Exact name of the database to restore into (required, must already exist)}
        {--confirm= : Must exactly match --target-database to proceed (typed confirmation, not a plain yes/no)}';

    protected $description = 'Restore a verified backup into a target database, with a mandatory pre-restore safety backup.';

    public function handle(DatabaseBackupService $backupService, DatabaseRestoreService $restoreService): int
    {
        $backup = Backup::find((int) $this->argument('backup_id'));
        if (! $backup) {
            $this->error("Backup #{$this->argument('backup_id')} does not exist.");

            return self::FAILURE;
        }

        $targetDatabase = $this->option('target-database');
        if (! $targetDatabase) {
            $this->error('--target-database is required. This command never guesses or defaults a restore target.');

            return self::FAILURE;
        }

        $targetDbConfig = $restoreService->targetDbConfig($targetDatabase);
        $liveAppConfig = config('database.connections.' . \App\Services\Backup\DatabaseRestoreService::TRUSTED_CONNECTION);
        $isLiveAppDatabase = ((string) ($targetDbConfig['host'] ?? '') === (string) ($liveAppConfig['host'] ?? ''))
            && ((string) ($targetDbConfig['port'] ?? '') === (string) ($liveAppConfig['port'] ?? ''))
            && ($targetDatabase === ($liveAppConfig['database'] ?? null));

        $this->line('');
        $this->line('=== RESTORE REQUEST ===');
        $this->line("SOURCE BACKUP:    #{$backup->id} ({$backup->filename})");
        $this->line("TARGET DATABASE:  {$targetDatabase}");
        $this->line('TARGET HOST:      ' . ($targetDbConfig['host'] ?? 'unknown'));
        $this->line('TARGET PORT:      ' . ($targetDbConfig['port'] ?? 'unknown'));
        $this->line('TARGET ENVIRONMENT: ' . ($isLiveAppDatabase ? 'THIS APPLICATION\'S OWN LIVE DATABASE' : 'a separate/other database'));
        $this->warn('WARNING: all existing data currently in the target database will be replaced.');
        if ($isLiveAppDatabase) {
            $this->error('!!! TARGET IS THIS APPLICATION\'S OWN LIVE DATABASE !!!');
        }
        $this->line('');

        // --- source backup integrity (steps 1-11) ---------------------
        // --- typed confirmation (steps 13-14, checked first: fail fast on
        // the single most common human error -- a mistyped target -- before
        // spending any time on network/file work) ------------------------
        if ($this->option('confirm') !== $targetDatabase) {
            $this->error('Confirmation did not match. Pass --confirm=' . $targetDatabase . ' exactly to proceed. Nothing was changed.');

            return self::FAILURE;
        }
        $this->info('Confirmation matched.');

        $workDir = storage_path('app/tmp/restore/' . $backup->id . '-' . Str::random(8));
        try {
            $verified = $restoreService->verifySourceBackup($backup, $workDir);
        } catch (\Throwable $e) {
            File::deleteDirectory($workDir);
            $this->error('Source backup verification failed: ' . $e->getMessage());
            $restoreService->logFailure('source_verification', $e->getMessage(), ['backup_id' => $backup->id]);

            return self::FAILURE;
        }
        $this->info('Source backup verified: checksum matches, ZIP and metadata are valid.');

        // --- target database connection (step 12) ----------------------
        try {
            $restoreService->verifyTargetDatabaseExists($targetDbConfig);
        } catch (\Throwable $e) {
            File::deleteDirectory($workDir);
            $this->error($e->getMessage());
            $restoreService->logFailure('target_connectivity', $e->getMessage(), ['target_database' => $targetDatabase]);

            return self::FAILURE;
        }
        $this->info('Target database is reachable.');

        // --- mandatory pre-restore safety backup (steps 15-16) ----------
        $this->line('Creating mandatory pre-restore safety backup of the current target database...');
        try {
            $preRestoreBackup = $restoreService->createPreRestoreBackup($targetDbConfig);
        } catch (\Throwable $e) {
            File::deleteDirectory($workDir);
            $this->error('Pre-restore safety backup failed -- restore aborted, target database was NOT touched: ' . $e->getMessage());
            $restoreService->logFailure('pre_restore_backup', $e->getMessage(), ['target_database' => $targetDatabase]);

            return self::FAILURE;
        }
        $this->info("Pre-restore safety backup #{$preRestoreBackup->id} completed ({$preRestoreBackup->filename}).");

        // --- maintenance mode, only for the app's own live database -----
        $maintenanceModeEntered = false;
        if ($isLiveAppDatabase && ! $this->laravel->isDownForMaintenance()) {
            Artisan::call('down');
            $maintenanceModeEntered = true;
            $this->info('Application placed into maintenance mode.');
        }

        // --- restore (step 17) -------------------------------------------
        try {
            $this->line('Importing verified SQL into the target database...');
            $restoreService->importSql($verified['sql_path'], $targetDbConfig);
            $this->info('Import completed. Running post-restore verification...');

            $verification = $restoreService->verifyRestoredDatabase($targetDbConfig, $verified['metadata']);
        } catch (\Throwable $e) {
            $restoreService->logFailure('restore_or_verification', $e->getMessage(), ['target_database' => $targetDatabase]);

            $this->error('RESTORE FAILED: ' . $e->getMessage());
            $this->error("Recovery: the pre-restore safety backup #{$preRestoreBackup->id} ({$preRestoreBackup->filename}) captures exactly what was in [{$targetDatabase}] immediately before this attempt. Restore that backup (with a fresh backup:restore invocation) to recover.");

            if ($maintenanceModeEntered) {
                $this->error('The application remains in MAINTENANCE MODE. Do not run `php artisan up` until the target database has been recovered and verified.');
            }

            File::deleteDirectory($workDir);

            return self::FAILURE;
        }

        File::deleteDirectory($workDir);

        if ($maintenanceModeEntered) {
            Artisan::call('up');
            $this->info('Application taken out of maintenance mode.');
        }

        $this->line('');
        $this->info('RESTORE SUCCESSFUL');
        $this->line("Target database:   {$targetDatabase}");
        $this->line("Tables verified:    {$verification['table_count']}");
        $this->line("Total rows verified: {$verification['total_rows']}");
        $this->line("Pre-restore safety backup: #{$preRestoreBackup->id} ({$preRestoreBackup->filename})");

        return self::SUCCESS;
    }
}
