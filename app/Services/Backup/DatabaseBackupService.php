<?php

namespace App\Services\Backup;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class DatabaseBackupService
{
    /**
     * Resolve the on-disk directory a Backup record's file lives in.
     * The single canonical path resolution used everywhere -- create,
     * download, delete, and retention pruning all go through this, closing
     * the pre-existing bug where store() and destroy() disagreed on where
     * the file actually was.
     */
    public function directoryFor(Backup $backup): string
    {
        return storage_path('app/' . trim($backup->path, '/'));
    }

    public function filePathFor(Backup $backup): string
    {
        return $this->directoryFor($backup) . DIRECTORY_SEPARATOR . $backup->filename;
    }

    /**
     * Create a real database backup for the given (already-persisted,
     * status=pending) Backup record. Every stage is individually wrapped so
     * a failure anywhere marks the record 'failed' with a real reason
     * logged -- it is never left/reported as 'completed' unless every
     * stage genuinely succeeded.
     */
    public function create(Backup $backup): void
    {
        $workDir = storage_path('app/tmp/backups/' . $backup->id . '-' . Str::random(8));
        File::ensureDirectoryExists($workDir);

        try {
            $connection = config('database.default');
            $dbConfig = config("database.connections.{$connection}");

            $sqlPath = $workDir . '/dump.sql';
            $this->runStage('mysqldump', fn () => $this->runMysqldump($dbConfig, $sqlPath));

            $tableStats = $this->runStage('table_verification', fn () => $this->collectTableStats($connection, $dbConfig));

            $sqlChecksum = $this->runStage('checksum', fn () => hash_file('sha256', $sqlPath));

            $metadata = [
                'created_at' => now()->toIso8601String(),
                'app' => [
                    'name' => config('app.name'),
                    'laravel_version' => app()->version(),
                ],
                'database' => [
                    'connection' => $connection,
                    'driver' => $dbConfig['driver'] ?? null,
                    'name' => $dbConfig['database'] ?? null,
                ],
                'tables' => $tableStats,
                'sql' => [
                    'filename' => 'dump.sql',
                    'size_bytes' => File::size($sqlPath),
                    'sha256' => $sqlChecksum,
                ],
            ];
            $metaPath = $workDir . '/metadata.json';
            $this->runStage('metadata', function () use ($metaPath, $metadata) {
                File::put($metaPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            });

            $finalDir = $this->directoryFor($backup);
            $zipPath = $finalDir . DIRECTORY_SEPARATOR . $backup->filename;
            $this->runStage('zip', function () use ($finalDir, $zipPath, $sqlPath, $metaPath) {
                File::ensureDirectoryExists($finalDir);
                $this->buildZip($zipPath, $sqlPath, $metaPath);
            });

            $zipChecksum = $this->runStage('checksum', fn () => hash_file('sha256', $zipPath));
            $zipSize = File::size($zipPath);

            $backup->update([
                'status' => 'completed',
                'completed_at' => now(),
                'size' => $zipSize,
                'metadata' => array_merge($metadata, [
                    'zip' => [
                        'filename' => $backup->filename,
                        'size_bytes' => $zipSize,
                        'sha256' => $zipChecksum,
                    ],
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::channel('backup')->error('Backup failed', [
                'backup_id' => $backup->id,
                'message' => $e->getMessage(),
            ]);

            $backup->update([
                'status' => 'failed',
                'notes' => trim(($backup->notes ? $backup->notes . "\n" : '') . 'Failed: ' . $e->getMessage()),
            ]);

            throw $e;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /**
     * Delete a completed backup's file and DB record together, via the one
     * canonical path -- fixes the pre-existing destroy() bug where the
     * file was never actually found/removed.
     */
    public function delete(Backup $backup): void
    {
        $path = $this->filePathFor($backup);
        if (File::exists($path)) {
            File::delete($path);
        }
        $backup->delete();
    }

    /**
     * Prune completed backups beyond the retention count, oldest first.
     * Only ever called after a new backup has already completed
     * successfully -- never before, so a failed new backup never leaves
     * fewer good backups on hand than before it ran.
     */
    public function pruneOldBackups(int $keep): int
    {
        $stale = Backup::where('status', 'completed')
            ->orderByDesc('completed_at')
            ->skip(max($keep, 0))
            ->take(PHP_INT_MAX)
            ->get();

        foreach ($stale as $backup) {
            $this->delete($backup);
        }

        return $stale->count();
    }

    private function runStage(string $stage, callable $fn)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            throw new \RuntimeException("Backup stage [{$stage}] failed: " . $this->sanitize($e->getMessage()), 0, $e);
        }
    }

    private function runMysqldump(array $dbConfig, string $outputPath): void
    {
        $mysqldumpPath = config('backup.mysqldump_path');
        $timeout = (int) config('backup.timeout_seconds', 300);

        // Credentials are passed via a temporary --defaults-extra-file
        // rather than on the command line, so they never appear in the
        // process argument list (visible to other processes/users on the
        // same machine) or in any exception/log message built from the
        // command itself.
        $defaultsFile = tempnam(sys_get_temp_dir(), 'hh_backup_');
        $ini = "[client]\n"
            . 'host=' . ($dbConfig['host'] ?? '127.0.0.1') . "\n"
            . 'port=' . ($dbConfig['port'] ?? 3306) . "\n"
            . 'user=' . ($dbConfig['username'] ?? '') . "\n"
            . 'password=' . ($dbConfig['password'] ?? '') . "\n";
        file_put_contents($defaultsFile, $ini);
        @chmod($defaultsFile, 0600);

        try {
            $result = Process::timeout($timeout)->run([
                $mysqldumpPath,
                '--defaults-extra-file=' . $defaultsFile,
                '--single-transaction',
                '--routines',
                '--triggers',
                '--skip-comments',
                (string) ($dbConfig['database'] ?? ''),
            ]);

            if (! $result->successful()) {
                throw new \RuntimeException(
                    'mysqldump exited with code ' . $result->exitCode() . ': ' . $this->sanitize($result->errorOutput())
                );
            }

            File::put($outputPath, $result->output());

            if (! File::exists($outputPath) || File::size($outputPath) === 0) {
                throw new \RuntimeException('mysqldump produced an empty output file.');
            }
        } finally {
            @unlink($defaultsFile);
        }
    }

    private function collectTableStats(string $connection, array $dbConfig): array
    {
        $databaseName = $dbConfig['database'] ?? null;
        $tables = DB::connection($connection)->select('SHOW TABLES');
        $tablesInKey = 'Tables_in_' . $databaseName;

        $rowCounts = [];
        foreach ($tables as $row) {
            $row = (array) $row;
            $table = $row[$tablesInKey] ?? array_values($row)[0];
            $rowCounts[$table] = DB::connection($connection)->table($table)->count();
        }

        return [
            'count' => count($rowCounts),
            'total_rows' => array_sum($rowCounts),
            'row_counts' => $rowCounts,
        ];
    }

    private function buildZip(string $zipPath, string $sqlPath, string $metaPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Unable to create zip archive at {$zipPath}");
        }
        $zip->addFile($sqlPath, 'dump.sql');
        $zip->addFile($metaPath, 'metadata.json');
        $zip->close();

        if (! File::exists($zipPath) || File::size($zipPath) === 0) {
            throw new \RuntimeException('Backup zip was not created correctly.');
        }
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
