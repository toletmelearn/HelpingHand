<?php

namespace Tests\Feature\Admin;

use App\Models\Backup;
use App\Models\Role;
use App\Models\User;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Phase 1B-i: the backup creation workflow -- success/failure status
 * handling, randomized filenames, retention pruning, and the fixed
 * store()/destroy() path consistency. Uses a bound fake
 * DatabaseBackupService for the HTTP-level tests so nothing here ever
 * shells out to a real mysqldump process against SQLite. pruneOldBackups()
 * itself is pure DB + filesystem logic and is exercised for real.
 */
class BackupCreationTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function bindFakeService(bool $shouldFail = false): void
    {
        $this->app->bind(DatabaseBackupService::class, function () use ($shouldFail) {
            return new class($shouldFail) extends DatabaseBackupService {
                public function __construct(private bool $shouldFail) {}

                public function create(Backup $backup): void
                {
                    if ($this->shouldFail) {
                        // Mirrors the real service's own contract: create()
                        // marks the record 'failed' with a reason before
                        // rethrowing, it never leaves the caller to infer
                        // failure state.
                        $backup->update(['status' => 'failed', 'notes' => 'Failed: Simulated mysqldump failure: connection refused']);
                        throw new \RuntimeException('Simulated mysqldump failure: connection refused');
                    }

                    $backup->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'size' => 54321,
                        'metadata' => [
                            'database' => ['name' => 'helpinghand_uat'],
                            'sql' => ['sha256' => hash('sha256', 'fake-sql-content')],
                        ],
                    ]);
                }
            };
        });
    }

    // --- success path --------------------------------------------------

    public function test_successful_backup_is_recorded_as_completed_with_metadata(): void
    {
        $this->bindFakeService(shouldFail: false);
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('admin.backups.store'), [
            'type' => 'database',
            'location' => 'local',
        ]);

        $response->assertRedirect(route('admin.backups.index'));
        $response->assertSessionHas('success');

        $backup = Backup::latest()->first();
        $this->assertSame('completed', $backup->status);
        $this->assertNotNull($backup->completed_at);
        $this->assertArrayHasKey('sql', $backup->metadata);
        $this->assertArrayHasKey('sha256', $backup->metadata['sql']);
    }

    // --- failure path: NEVER reported as successful ---------------------

    public function test_failed_backup_is_recorded_as_failed_and_not_reported_as_success(): void
    {
        $this->bindFakeService(shouldFail: true);
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('admin.backups.store'), [
            'type' => 'database',
            'location' => 'local',
        ]);

        $response->assertRedirect(route('admin.backups.index'));
        $response->assertSessionHas('error');
        $response->assertSessionMissing('success');

        $backup = Backup::latest()->first();
        $this->assertSame('failed', $backup->status);
        $this->assertStringContainsString('Failed:', (string) $backup->notes);
    }

    // --- randomized filenames -------------------------------------------

    public function test_backup_filenames_include_a_random_component_and_never_collide(): void
    {
        $this->bindFakeService(shouldFail: false);
        $admin = $this->adminUser();

        \Carbon\Carbon::setTestNow('2026-08-24 10:00:00');
        $this->actingAs($admin)->post(route('admin.backups.store'), ['type' => 'database', 'location' => 'local']);
        $this->actingAs($admin)->post(route('admin.backups.store'), ['type' => 'database', 'location' => 'local']);

        $filenames = Backup::orderBy('id')->pluck('filename');
        $this->assertCount(2, $filenames->unique(), 'Two backups created in the same second must still get distinct filenames.');
    }

    // --- retention --------------------------------------------------------

    public function test_retention_keeps_only_the_newest_backups_and_deletes_the_rest(): void
    {
        $admin = $this->adminUser();
        $service = app(DatabaseBackupService::class);

        $keptIds = [];
        for ($i = 0; $i < 16; $i++) {
            $backup = Backup::create([
                'filename' => "retention-{$i}.zip",
                'path' => 'backups/2026/08/' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'type' => 'database',
                'location' => 'local',
                'size' => 10,
                'status' => 'completed',
                'created_by' => $admin->id,
                'completed_at' => now()->subDays(16 - $i),
            ]);
            $path = $service->filePathFor($backup);
            File::ensureDirectoryExists(dirname($path));
            File::put($path, 'x');
            if ($i >= 2) {
                $keptIds[] = $backup->id;
            }
        }

        $pruned = $service->pruneOldBackups(14);

        $this->assertSame(2, $pruned);
        $this->assertSame(14, Backup::count());
        $remainingIds = Backup::pluck('id')->sort()->values()->all();
        sort($keptIds);
        $this->assertSame($keptIds, $remainingIds);
    }

    public function test_retention_only_prunes_after_a_new_backup_succeeds_never_before(): void
    {
        $this->bindFakeService(shouldFail: true);
        $admin = $this->adminUser();
        $service = app(DatabaseBackupService::class);

        for ($i = 0; $i < 5; $i++) {
            $backup = Backup::create([
                'filename' => "existing-{$i}.zip",
                'path' => 'backups/2026/07/' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'type' => 'database', 'location' => 'local', 'size' => 10,
                'status' => 'completed', 'created_by' => $admin->id, 'completed_at' => now()->subDays($i),
            ]);
            $path = $service->filePathFor($backup);
            File::ensureDirectoryExists(dirname($path));
            File::put($path, 'x');
        }

        // A failing new backup attempt must not trigger pruning of the 5 good ones.
        $this->actingAs($admin)->post(route('admin.backups.store'), ['type' => 'database', 'location' => 'local']);

        $this->assertSame(5, Backup::where('status', 'completed')->count());
    }
}
