<?php

namespace Tests\Feature\Console;

use App\Models\Backup;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Phase 1B-ii: backup:restore's source-verification and confirmation
 * safeguards. These run entirely against the SQLite test database and
 * hand-crafted files -- no real MariaDB connection is touched, and
 * because every scenario here is rejected before the command ever reaches
 * its target-database or destructive-import stages, none of them are
 * capable of modifying any real data. Restore success/failure against a
 * genuine target database is covered separately in
 * BackupRestoreRealMariaDbTest.php (skips cleanly if MariaDB isn't
 * reachable) and in the mandatory manual Real Restore UAT procedure.
 */
class BackupRestoreCommandTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseBackupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DatabaseBackupService::class);
    }

    private function backupRow(array $overrides = []): Backup
    {
        return Backup::create(array_merge([
            'filename' => 'test_' . uniqid() . '.zip',
            'path' => 'backups/2026/08/24',
            'type' => 'database',
            'location' => 'local',
            'size' => 1,
            'status' => 'completed',
            'created_by' => \App\Models\User::factory()->create()->id,
            'completed_at' => now(),
        ], $overrides));
    }

    private function writeAt(Backup $backup, string $content): void
    {
        $path = $this->service->filePathFor($backup);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);
    }

    /** A real, valid zip -- dump.sql content is arbitrary but internally consistent (checksum matches). */
    private function writeValidZip(Backup $backup, string $sql = "-- test dump\nCREATE TABLE t (id int);\n"): void
    {
        $path = $this->service->filePathFor($backup);
        File::ensureDirectoryExists(dirname($path));

        $tmp = storage_path('app/tmp/test_' . uniqid());
        File::ensureDirectoryExists($tmp);
        File::put($tmp . '/dump.sql', $sql);
        File::put($tmp . '/metadata.json', json_encode([
            'database' => ['name' => 'irrelevant'],
            'tables' => ['count' => 1, 'total_rows' => 0, 'row_counts' => []],
            'sql' => ['sha256' => hash('sha256', $sql)],
        ]));

        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFile($tmp . '/dump.sql', 'dump.sql');
        $zip->addFile($tmp . '/metadata.json', 'metadata.json');
        $zip->close();

        File::deleteDirectory($tmp);
    }

    public function test_command_exists_and_is_resolvable(): void
    {
        $this->assertArrayHasKey('backup:restore', Artisan::all());
    }

    public function test_missing_backup_is_rejected(): void
    {
        $exitCode = Artisan::call('backup:restore', [
            'backup_id' => 999999,
            '--target-database' => 'whatever',
            '--confirm' => 'whatever',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('does not exist', Artisan::output());
    }

    public function test_missing_target_database_option_is_rejected(): void
    {
        $backup = $this->backupRow();

        $exitCode = Artisan::call('backup:restore', ['backup_id' => $backup->id]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('--target-database is required', Artisan::output());
    }

    public function test_incorrect_confirmation_is_rejected_before_touching_anything(): void
    {
        $backup = $this->backupRow();
        // Deliberately leave no real file at all -- if confirmation-check
        // genuinely runs first, this test never even reaches file I/O.

        $exitCode = Artisan::call('backup:restore', [
            'backup_id' => $backup->id,
            '--target-database' => 'some_db',
            '--confirm' => 'a_typo_or_wrong_name',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Confirmation did not match', Artisan::output());
    }

    public function test_missing_backup_file_on_disk_is_rejected(): void
    {
        $backup = $this->backupRow();
        // No file written at all.

        $exitCode = Artisan::call('backup:restore', [
            'backup_id' => $backup->id,
            '--target-database' => 'some_db',
            '--confirm' => 'some_db',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('missing', Artisan::output());
    }

    public function test_path_traversal_via_backup_path_is_rejected(): void
    {
        $backup = $this->backupRow(['path' => '../../../../etc', 'filename' => 'passwd']);

        $exitCode = Artisan::call('backup:restore', [
            'backup_id' => $backup->id,
            '--target-database' => 'some_db',
            '--confirm' => 'some_db',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('outside the backups directory', Artisan::output());
    }

    public function test_malformed_zip_is_rejected(): void
    {
        $backup = $this->backupRow();
        $this->writeAt($backup, 'this is not a zip file, just plain garbage bytes');

        $exitCode = Artisan::call('backup:restore', [
            'backup_id' => $backup->id,
            '--target-database' => 'some_db',
            '--confirm' => 'some_db',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('integrity check', Artisan::output());
    }

    public function test_zip_missing_sql_is_rejected(): void
    {
        $backup = $this->backupRow();
        $path = $this->service->filePathFor($backup);
        File::ensureDirectoryExists(dirname($path));
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('metadata.json', json_encode(['database' => [], 'tables' => [], 'sql' => ['sha256' => 'x']]));
        $zip->close();

        $exitCode = Artisan::call('backup:restore', [
            'backup_id' => $backup->id,
            '--target-database' => 'some_db',
            '--confirm' => 'some_db',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('does not contain dump.sql', Artisan::output());
    }

    public function test_zip_missing_metadata_is_rejected(): void
    {
        $backup = $this->backupRow();
        $path = $this->service->filePathFor($backup);
        File::ensureDirectoryExists(dirname($path));
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('dump.sql', 'CREATE TABLE t (id int);');
        $zip->close();

        $exitCode = Artisan::call('backup:restore', [
            'backup_id' => $backup->id,
            '--target-database' => 'some_db',
            '--confirm' => 'some_db',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('does not contain metadata.json', Artisan::output());
    }

    public function test_malformed_metadata_json_is_rejected(): void
    {
        $backup = $this->backupRow();
        $path = $this->service->filePathFor($backup);
        File::ensureDirectoryExists(dirname($path));
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('dump.sql', 'CREATE TABLE t (id int);');
        $zip->addFromString('metadata.json', '{not valid json...');
        $zip->close();

        $exitCode = Artisan::call('backup:restore', [
            'backup_id' => $backup->id,
            '--target-database' => 'some_db',
            '--confirm' => 'some_db',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('malformed', Artisan::output());
    }

    public function test_metadata_missing_required_keys_is_rejected(): void
    {
        $backup = $this->backupRow();
        $path = $this->service->filePathFor($backup);
        File::ensureDirectoryExists(dirname($path));
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('dump.sql', 'CREATE TABLE t (id int);');
        $zip->addFromString('metadata.json', json_encode(['not_the_right' => 'shape']));
        $zip->close();

        $exitCode = Artisan::call('backup:restore', [
            'backup_id' => $backup->id,
            '--target-database' => 'some_db',
            '--confirm' => 'some_db',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('missing the required', Artisan::output());
    }

    public function test_checksum_mismatch_is_rejected(): void
    {
        $backup = $this->backupRow();
        $path = $this->service->filePathFor($backup);
        File::ensureDirectoryExists(dirname($path));
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('dump.sql', 'CREATE TABLE t (id int);');
        // Deliberately wrong checksum -- simulates a corrupted/tampered backup.
        $zip->addFromString('metadata.json', json_encode([
            'database' => ['name' => 'x'],
            'tables' => ['count' => 1, 'total_rows' => 0, 'row_counts' => []],
            'sql' => ['sha256' => str_repeat('0', 64)],
        ]));
        $zip->close();

        $exitCode = Artisan::call('backup:restore', [
            'backup_id' => $backup->id,
            '--target-database' => 'some_db',
            '--confirm' => 'some_db',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Checksum mismatch', Artisan::output());
    }

    /**
     * No database credential ever appears in command output, even on
     * failure paths that build their error message from a real database
     * exception. The SQLite test connection has no password configured,
     * so this asserts the structural guarantee (no literal "password="
     * value leaks into output) rather than a specific secret -- the
     * equivalent check against a real, non-empty MariaDB password is
     * covered by BackupRestoreRealMariaDbTest.
     */
    public function test_no_secrets_appear_in_command_output_on_failure(): void
    {
        $backup = $this->backupRow();
        $this->writeValidZip($backup);
        $target = 'definitely_does_not_exist_' . uniqid();

        Artisan::call('backup:restore', [
            'backup_id' => $backup->id,
            // A target database that (almost certainly) does not exist,
            // forcing the target-connectivity failure path, which builds
            // its error message from a real exception.
            '--target-database' => $target,
            '--confirm' => $target,
        ]);

        $output = Artisan::output();
        $this->assertStringNotContainsString('password=', strtolower($output));
    }
}
