<?php

namespace Tests\Feature\Console;

use App\Models\Backup;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Backup\DatabaseRestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

/**
 * Phase 1B-ii: real, isolated MariaDB restore round-trips. These tests
 * create genuinely disposable databases on the same local MariaDB server
 * this app already talks to (never the real `helpinghand` database),
 * exercise the actual backup:restore command end to end against them, and
 * drop the disposable databases afterward. If no MariaDB server is
 * reachable, every test here skips cleanly rather than failing --
 * matching this project's established pattern (Phase 1B-i's backup
 * pipeline) of treating "does the real MySQL-specific pipeline work" as
 * something that needs a real server, not a SQLite substitute.
 */
class BackupRestoreRealMariaDbTest extends TestCase
{
    use RefreshDatabase;

    private ?PDO $pdo = null;
    private array $baseConfig;
    private array $disposableDatabases = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseConfig = config('database.connections.' . DatabaseRestoreService::TRUSTED_CONNECTION);

        try {
            $this->pdo = new PDO(
                "mysql:host={$this->baseConfig['host']};port={$this->baseConfig['port']}",
                $this->baseConfig['username'],
                $this->baseConfig['password']
            );
        } catch (\Throwable $e) {
            $this->markTestSkipped('Real MariaDB server not reachable, skipping real-restore tests: ' . $e->getMessage());
        }

        // createPreRestoreBackup() falls back to the first admin user when
        // there's no authenticated CLI session (matching BackupRunCommand's
        // identical fallback) -- ensure one exists in every test's DB state.
        $admin = \App\Models\User::factory()->create();
        $role = \App\Models\Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($role->id);
    }

    protected function tearDown(): void
    {
        foreach ($this->disposableDatabases as $name) {
            $this->pdo?->exec("DROP DATABASE IF EXISTS `{$name}`");
        }
        parent::tearDown();
    }

    private function createDisposableDatabase(string $suffix): string
    {
        $name = 'hh_restore_test_' . $suffix . '_' . substr(uniqid(), -6);
        $this->pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
        $this->pdo->exec("CREATE DATABASE `{$name}`");
        $this->disposableDatabases[] = $name;

        return $name;
    }

    private function seedMarkerTable(string $database, string $marker, int $rows = 1): void
    {
        config(["database.connections.tmp_seed" => array_merge($this->baseConfig, ['database' => $database])]);
        DB::connection('tmp_seed')->statement('CREATE TABLE marker (id INT PRIMARY KEY, note VARCHAR(255))');
        for ($i = 1; $i <= $rows; $i++) {
            DB::connection('tmp_seed')->table('marker')->insert(['id' => $i, 'note' => $marker . '-' . $i]);
        }
        DB::purge('tmp_seed');
    }

    private function readMarkerRows(string $database): array
    {
        config(["database.connections.tmp_read" => array_merge($this->baseConfig, ['database' => $database])]);
        $rows = DB::connection('tmp_read')->table('marker')->orderBy('id')->pluck('note')->all();
        DB::purge('tmp_read');

        return $rows;
    }

    private function realBackupOf(string $database): Backup
    {
        config(["database.connections.tmp_backup_source" => array_merge($this->baseConfig, ['database' => $database])]);
        $admin = \App\Models\User::factory()->create();
        $backup = Backup::create([
            'filename' => 'restore_uat_source_' . uniqid() . '.zip',
            'path' => 'backups/restore-uat/' . now()->format('Y/m/d'),
            'type' => 'database', 'location' => 'local', 'size' => 0,
            'status' => 'pending', 'created_by' => $admin->id,
        ]);
        app(DatabaseBackupService::class)->create($backup, 'tmp_backup_source', config('database.connections.tmp_backup_source'));
        DB::purge('tmp_backup_source');
        $backup->refresh();

        return $backup;
    }

    // --- successful round trip ---------------------------------------

    public function test_successful_isolated_restore_replaces_target_with_source_content(): void
    {
        $source = $this->createDisposableDatabase('source');
        $this->seedMarkerTable($source, 'SOURCE-MARKER', 3);
        $sourceBackup = $this->realBackupOf($source);
        $this->assertSame('completed', $sourceBackup->status);

        $target = $this->createDisposableDatabase('target');
        $this->seedMarkerTable($target, 'ORIGINAL-TARGET-DATA-SHOULD-BE-REPLACED', 1);

        $exitCode = Artisan::call('backup:restore', [
            'backup_id' => $sourceBackup->id,
            '--target-database' => $target,
            '--confirm' => $target,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, "Restore did not succeed. Output:\n{$output}");
        $this->assertStringContainsString('RESTORE SUCCESSFUL', $output);

        $restoredRows = $this->readMarkerRows($target);
        $this->assertSame(['SOURCE-MARKER-1', 'SOURCE-MARKER-2', 'SOURCE-MARKER-3'], $restoredRows);

        // A pre-restore safety backup of the target's original content must exist.
        $this->assertDatabaseHas('backups', ['notes' => "Automatic safety backup of [{$target}] taken immediately before a restore."]);
    }

    // --- pre-restore backup failure blocks restore ----------------------

    public function test_pre_restore_backup_failure_blocks_restore_and_leaves_target_untouched(): void
    {
        $source = $this->createDisposableDatabase('source2');
        $this->seedMarkerTable($source, 'SOURCE-MARKER', 1);
        $sourceBackup = $this->realBackupOf($source);

        $target = $this->createDisposableDatabase('target2');
        $this->seedMarkerTable($target, 'ORIGINAL-UNTOUCHED', 1);

        // Force the pre-restore backup step to fail without ever touching
        // mysqldump -- bind a DatabaseBackupService whose create() always
        // throws, exactly mirroring a real dump failure's contract
        // (mark failed, then throw).
        $this->app->bind(DatabaseBackupService::class, function () {
            return new class extends DatabaseBackupService {
                public function create(Backup $backup, ?string $connection = null, ?array $dbConfigOverride = null): void
                {
                    $backup->update(['status' => 'failed', 'notes' => 'Simulated pre-restore backup failure']);
                    throw new \RuntimeException('Simulated pre-restore backup failure');
                }
            };
        });

        $exitCode = Artisan::call('backup:restore', [
            'backup_id' => $sourceBackup->id,
            '--target-database' => $target,
            '--confirm' => $target,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Pre-restore safety backup failed', $output);
        $this->assertStringContainsString('NOT touched', $output);

        // Target must be completely unchanged -- the destructive import must never have run.
        $this->assertSame(['ORIGINAL-UNTOUCHED-1'], $this->readMarkerRows($target));
    }

    // --- failed restore is reported correctly, recovery info given ------

    public function test_failed_import_is_reported_with_recovery_information_and_never_claims_success(): void
    {
        $source = $this->createDisposableDatabase('source3');
        $this->seedMarkerTable($source, 'SOURCE-MARKER', 1);
        $sourceBackup = $this->realBackupOf($source);

        $target = $this->createDisposableDatabase('target3');
        $this->seedMarkerTable($target, 'ORIGINAL-PRESERVED-BY-PRE-RESTORE-BACKUP', 1);

        // Force the mysql import step itself to fail by pointing at a
        // nonexistent binary -- the pre-restore backup (real mysqldump)
        // still runs and succeeds normally.
        config(['backup.mysql_path' => storage_path('app/definitely_not_a_real_mysql_binary_' . uniqid())]);

        $exitCode = Artisan::call('backup:restore', [
            'backup_id' => $sourceBackup->id,
            '--target-database' => $target,
            '--confirm' => $target,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('RESTORE FAILED', $output);
        $this->assertStringNotContainsString('RESTORE SUCCESSFUL', $output);
        $this->assertStringContainsString('pre-restore safety backup', strtolower($output));

        // A real pre-restore backup of the target's original content must have been created and recorded.
        $this->assertDatabaseHas('backups', [
            'notes' => "Automatic safety backup of [{$target}] taken immediately before a restore.",
            'status' => 'completed',
        ]);
    }

    // --- secrets never leak, even from a real connection error ----------

    public function test_no_real_database_password_appears_in_output_on_target_connectivity_failure(): void
    {
        $source = $this->createDisposableDatabase('source4');
        $this->seedMarkerTable($source, 'SOURCE-MARKER', 1);
        $sourceBackup = $this->realBackupOf($source);

        $realPassword = $this->baseConfig['password'] ?? null;
        if (! is_string($realPassword) || $realPassword === '') {
            $this->markTestSkipped('No real MariaDB password configured on this environment to verify against.');
        }

        $nonexistentTarget = 'hh_restore_target_that_was_never_created_' . uniqid();

        Artisan::call('backup:restore', [
            'backup_id' => $sourceBackup->id,
            '--target-database' => $nonexistentTarget,
            '--confirm' => $nonexistentTarget,
        ]);

        $this->assertStringNotContainsString($realPassword, Artisan::output());
    }
}
