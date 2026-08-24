<?php

namespace App\Services\Operations;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class BackupService
{
    /**
     * Trigger a new backup process.
     */
    public function createBackup(string $type = 'database', string $notes = ''): Backup
    {
        $backup = Backup::create([
            'filename' => 'pending',
            'path' => 'pending',
            'type' => $type,
            'location' => 'local',
            'size' => 0,
            'status' => 'pending',
            'notes' => $notes,
            'created_by' => Auth::id(),
            'scheduled_at' => now(),
        ]);

        try {
            $filename = 'backup_' . $type . '_' . date('Ymd_His') . ($type === 'files' ? '.zip' : ($type === 'database' ? '.sql' : '.zip'));
            $backupDir = storage_path('app/backups');
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0775, true);
            }
            $filePath = $backupDir . '/' . $filename;

            $backup->update([
                'filename' => $filename,
                'path' => 'backups/' . $filename,
                'status' => 'running',
            ]);

            $start = microtime(true);

            if ($type === 'database') {
                $this->dumpDatabase($filePath);
            } elseif ($type === 'files') {
                $this->zipFiles($filePath, storage_path('app/public'));
            } else { // full backup (database + files in a single zip)
                $tempSql = $backupDir . '/temp_db.sql';
                $this->dumpDatabase($tempSql);
                
                $zip = new ZipArchive();
                if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                    // Add SQL file
                    $zip->addFile($tempSql, 'database.sql');
                    // Add public upload files
                    $filesDir = storage_path('app/public');
                    if (is_dir($filesDir)) {
                        $files = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($filesDir),
                            RecursiveIteratorIterator::LEAVES_ONLY
                        );
                        foreach ($files as $file) {
                            if (!$file->isDir()) {
                                $filePathName = $file->getRealPath();
                                $relativePath = 'uploads/' . substr($filePathName, strlen($filesDir) + 1);
                                $zip->addFile($filePathName, $relativePath);
                            }
                        }
                    }
                    $zip->close();
                }
                @unlink($tempSql);
            }

            $duration = round(microtime(true) - $start, 2);
            $size = file_exists($filePath) ? filesize($filePath) : 0;

            $backup->update([
                'size' => $size,
                'status' => 'completed',
                'completed_at' => now(),
                'metadata' => [
                    'duration_seconds' => $duration,
                    'hash_md5' => file_exists($filePath) ? md5_file($filePath) : '',
                    'tables_count' => count(Schema::getTables()),
                ],
            ]);

            return $backup;

        } catch (\Throwable $e) {
            $backup->update([
                'status' => 'failed',
                'notes' => ($backup->notes ? $backup->notes . "\n" : '') . "Error: " . $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify backup exists and generates matching MD5 hash.
     */
    public function verifyBackup(int $id): bool
    {
        $backup = Backup::findOrFail($id);
        $filePath = storage_path('app/' . $backup->path);

        if (!file_exists($filePath)) {
            return false;
        }

        if (filesize($filePath) !== (int)$backup->size) {
            return false;
        }

        $meta = $backup->metadata;
        if (!empty($meta['hash_md5'])) {
            return md5_file($filePath) === $meta['hash_md5'];
        }

        return true;
    }

    /**
     * Dump the active Database to a SQL file.
     */
    protected function dumpDatabase(string $path): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $dbFile = config('database.connections.sqlite.database');
            if ($dbFile === ':memory:') {
                file_put_contents($path, "-- SQLite In-Memory Database Backup Placeholder\n");
            } elseif (file_exists($dbFile)) {
                copy($dbFile, $path);
            } else {
                throw new \Exception("SQLite database file not found at " . $dbFile);
            }
        } else {
            // Portable PHP dumper for MariaDB/MySQL
            $tables = Schema::getTables();
            $sql = "-- HelpingHand ERP Database Dump\n";
            $sql .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tables as $table) {
                $tableName = $table['name'] ?? (is_object($table) ? $table->name : $table);
                
                // Fetch structural DDL
                $create = DB::selectOne("SHOW CREATE TABLE `{$tableName}`");
                if ($create) {
                    $ddlField = 'Create Table';
                    $sqlDDL = $create->$ddlField ?? array_values((array)$create)[1];
                    $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                    $sql .= $sqlDDL . ";\n\n";
                }

                // Fetch table records
                $rows = DB::table($tableName)->get();
                if ($rows->count() > 0) {
                    $sql .= "LOCK TABLES `{$tableName}` WRITE;\n";
                    foreach ($rows as $row) {
                        $fields = array_keys((array)$row);
                        $values = array_map(function($v) {
                            if (is_null($v)) return 'NULL';
                            return DB::getPdo()->quote($v);
                        }, array_values((array)$row));
                        
                        $sql .= "INSERT INTO `{$tableName}` (`" . implode("`, `", $fields) . "`) VALUES (" . implode(", ", $values) . ");\n";
                    }
                    $sql .= "UNLOCK TABLES;\n\n";
                }
            }

            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            file_put_contents($path, $sql);
        }
    }

    /**
     * Zip contents of a directory.
     */
    protected function zipFiles(string $zipPath, string $sourceDir): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Could not create ZIP file: " . $zipPath);
        }

        if (is_dir($sourceDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceDir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($sourceDir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }

        $zip->close();
    }

    /**
     * Unzip archive file to a destination directory.
     */
    protected function unzipFiles(string $zipPath, string $destinationDir): void
    {
        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0775, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($destinationDir);
            $zip->close();
        } else {
            throw new \Exception("Could not extract ZIP file.");
        }
    }

    /**
     * Copy directory recursively.
     */
    protected function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0775, true);
        }
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file !== '.' && $file !== '..') {
                if (is_dir($src . '/' . $file)) {
                    $this->copyDir($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    /**
     * Delete directory recursively.
     */
    protected function deleteDir(string $dirPath): void
    {
        if (!is_dir($dirPath)) {
            return;
        }
        $files = array_diff(scandir($dirPath), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dirPath/$file")) ? $this->deleteDir("$dirPath/$file") : unlink("$dirPath/$file");
        }
        rmdir($dirPath);
    }
}
