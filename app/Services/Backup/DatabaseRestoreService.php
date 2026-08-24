<?php

namespace App\Services\Backup;

use App\Models\Backup;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * High-risk, CLI-only database restore. Every public entry point here is
 * only ever meant to be called from BackupRestoreCommand -- there is
 * deliberately no controller/route/web path to this service anywhere in
 * the application.
 */
class DatabaseRestoreService
{
    public function __construct(private DatabaseBackupService $backupService) {}

    /**
     * The connection name backup/restore always talk to. Deliberately
     * hardcoded rather than config('database.default') -- restore is
     * inherently a MySQL/MariaDB operation, and this app's own
     * config/database.php always names that connection 'mariadb'
     * regardless of which connection happens to be the runtime default
     * (e.g. the test suite's default is 'sqlite'). Using the fixed name
     * keeps target-database resolution correct and consistent everywhere
     * this app runs, not just when mariadb happens to also be default.
     */
    public const TRUSTED_CONNECTION = 'mariadb';

    /**
     * Resolve the same trusted connection (host/port/username/password)
     * this app already talks to, with only the database name swapped to
     * the operator-confirmed target. Host/credentials are never accepted
     * from CLI input -- only which database, on the already-trusted
     * server, is being targeted.
     */
    public function targetDbConfig(string $targetDatabase): array
    {
        $baseConfig = config('database.connections.' . self::TRUSTED_CONNECTION);

        return array_merge($baseConfig, ['database' => $targetDatabase]);
    }

    /**
     * Confirm the target database genuinely exists and is reachable on
     * the trusted server before anything destructive is even considered.
     * Registers and immediately purges a throwaway named connection --
     * never touches the app's own default connection.
     */
    public function verifyTargetDatabaseExists(array $targetDbConfig): void
    {
        $connectionName = $this->registerTemporaryConnection($targetDbConfig);

        try {
            DB::connection($connectionName)->select('SELECT 1');
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Cannot connect to target database [{$targetDbConfig['database']}]: it must already exist on the server and be reachable. " . $this->sanitize($e->getMessage())
            );
        } finally {
            DB::purge($connectionName);
        }
    }

    /**
     * The mandatory pre-restore safety backup of whatever currently lives
     * in the target database, taken immediately before anything
     * destructive happens. Returns the created Backup record. Throws (and
     * the caller must abort the restore) if this backup does not
     * genuinely complete.
     */
    public function createPreRestoreBackup(array $targetDbConfig): Backup
    {
        $connectionName = $this->registerTemporaryConnection($targetDbConfig);

        // backups.created_by is NOT NULL with a foreign key to users -- a
        // CLI restore has no authenticated web session, so it's
        // attributed to the first admin account found, same fallback
        // BackupRunCommand already uses for the identical reason.
        $createdBy = Auth::id() ?? User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->value('id');
        if ($createdBy === null) {
            DB::purge($connectionName);
            throw new \RuntimeException('No admin user exists to attribute the pre-restore safety backup to. Restore aborted.');
        }

        $preRestore = Backup::create([
            'filename' => 'pre_restore_' . now()->format('Y-m-d_H-i-s') . '_' . Str::random(8) . '.zip',
            'path' => 'backups/pre-restore/' . now()->format('Y/m/d'),
            'type' => 'database',
            'location' => 'local',
            'size' => 0,
            'status' => 'pending',
            'notes' => "Automatic safety backup of [{$targetDbConfig['database']}] taken immediately before a restore.",
            'created_by' => $createdBy,
        ]);

        try {
            $this->backupService->create($preRestore, $connectionName, $targetDbConfig);
        } finally {
            DB::purge($connectionName);
        }

        $preRestore->refresh();
        if ($preRestore->status !== 'completed') {
            throw new \RuntimeException('Pre-restore safety backup did not complete successfully -- restore aborted before touching the target database.');
        }

        return $preRestore;
    }

    /**
     * Verify the source backup's integrity (delegates to
     * DatabaseBackupService::verifyAndExtract -- exists, contained,
     * valid ZIP, both members present, metadata valid, checksum matches).
     * Extraction happens into $workDir, caller is responsible for cleanup.
     */
    public function verifySourceBackup(Backup $backup, string $workDir): array
    {
        return $this->backupService->verifyAndExtract($backup, $workDir);
    }

    /**
     * Stream the verified dump.sql into the target database via the real
     * `mysql` client (Windows-safe array-form Process invocation,
     * credentials via a temporary --defaults-extra-file, never on the
     * command line or in any log/exception message).
     */
    public function importSql(string $sqlPath, array $targetDbConfig): void
    {
        $mysqlPath = config('backup.mysql_path');
        $timeout = (int) config('backup.timeout_seconds', 300);

        $defaultsFile = tempnam(sys_get_temp_dir(), 'hh_restore_');
        $ini = "[client]\n"
            . 'host=' . ($targetDbConfig['host'] ?? '127.0.0.1') . "\n"
            . 'port=' . ($targetDbConfig['port'] ?? 3306) . "\n"
            . 'user=' . ($targetDbConfig['username'] ?? '') . "\n"
            . 'password=' . ($targetDbConfig['password'] ?? '') . "\n";
        file_put_contents($defaultsFile, $ini);
        @chmod($defaultsFile, 0600);

        $handle = fopen($sqlPath, 'rb');
        if ($handle === false) {
            @unlink($defaultsFile);
            throw new \RuntimeException("Unable to open verified dump for reading: {$sqlPath}");
        }

        try {
            $result = Process::timeout($timeout)->input($handle)->run([
                $mysqlPath,
                '--defaults-extra-file=' . $defaultsFile,
                (string) ($targetDbConfig['database'] ?? ''),
            ]);

            if (! $result->successful()) {
                throw new \RuntimeException(
                    'mysql import exited with code ' . $result->exitCode() . ': ' . $this->sanitize($result->errorOutput())
                );
            }
        } finally {
            fclose($handle);
            @unlink($defaultsFile);
        }
    }

    /**
     * Post-restore verification: fresh connection (never a cached/stale
     * one), table count and per-table row counts compared against what
     * the source backup's own metadata recorded. Returns a summary for
     * the command to print; throws if anything looks wrong so the
     * command never reports RESTORE SUCCESSFUL on a silently-bad result.
     */
    public function verifyRestoredDatabase(array $targetDbConfig, array $sourceMetadata): array
    {
        $connectionName = $this->registerTemporaryConnection($targetDbConfig);

        try {
            DB::connection($connectionName)->select('SELECT 1');

            $tables = DB::connection($connectionName)->select('SHOW TABLES');
            $tablesInKey = 'Tables_in_' . $targetDbConfig['database'];
            $restoredCounts = [];
            foreach ($tables as $row) {
                $row = (array) $row;
                $table = $row[$tablesInKey] ?? array_values($row)[0];
                $restoredCounts[$table] = DB::connection($connectionName)->table($table)->count();
            }

            $expectedCounts = $sourceMetadata['tables']['row_counts'] ?? [];
            $mismatches = [];
            foreach ($expectedCounts as $table => $expectedCount) {
                $actual = $restoredCounts[$table] ?? null;
                if ($actual !== $expectedCount) {
                    $mismatches[$table] = ['expected' => $expectedCount, 'actual' => $actual];
                }
            }

            if (! empty($mismatches)) {
                throw new \RuntimeException(
                    'Post-restore verification found row-count mismatches: ' . json_encode($mismatches)
                );
            }

            if (count($restoredCounts) !== ($sourceMetadata['tables']['count'] ?? -1)) {
                throw new \RuntimeException(
                    'Post-restore table count (' . count($restoredCounts) . ') does not match the source backup\'s recorded table count (' . ($sourceMetadata['tables']['count'] ?? 'unknown') . ').'
                );
            }

            return [
                'connection_ok' => true,
                'table_count' => count($restoredCounts),
                'total_rows' => array_sum($restoredCounts),
                'row_counts_match' => true,
            ];
        } finally {
            DB::purge($connectionName);
        }
    }

    public function logFailure(string $stage, string $message, array $context = []): void
    {
        Log::channel('backup')->error("Restore failed at stage [{$stage}]", array_merge($context, [
            'message' => $this->sanitize($message),
        ]));
    }

    private function registerTemporaryConnection(array $dbConfig): string
    {
        $name = 'restore_' . Str::random(8);
        config(["database.connections.{$name}" => $dbConfig]);

        return $name;
    }

    private function sanitize(string $message): string
    {
        $password = config('database.connections.' . config('database.default') . '.password');
        if (is_string($password) && $password !== '') {
            $message = str_replace($password, '***', $message);
        }

        return $message;
    }
}
