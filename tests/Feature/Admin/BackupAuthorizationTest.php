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
 * Phase 1B-i P0 fix: BackupController previously had zero authorization
 * (only `auth` middleware) -- any authenticated non-admin web-guard
 * account could create/list/download/delete backups. This proves the new
 * BackupPolicy closes that gap for every action, plus the destroy() path
 * bug (it deleted from the wrong directory than store() wrote to) and a
 * defense-in-depth path-traversal check on download().
 *
 * A fake DatabaseBackupService is bound throughout so nothing in this file
 * ever shells out to a real mysqldump process -- these tests run on the
 * SQLite in-memory test database and must never touch the real MariaDB
 * connection. Real dump-pipeline correctness is verified separately via
 * the isolated MariaDB UAT procedure, not here.
 */
class BackupAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(DatabaseBackupService::class, function () {
            return new class extends DatabaseBackupService {
                public function create(\App\Models\Backup $backup): void
                {
                    $backup->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'size' => 12345,
                        'metadata' => ['fake' => true],
                    ]);
                }
            };
        });
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function clerkUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'clerk'], ['display_name' => 'Clerk']);
        $user->roles()->attach($role->id);

        return $user;
    }

    /** Creates a completed Backup record with a real dummy file at the exact path filePathFor() resolves to. */
    private function completedBackupWithFile(string $label = 'Target'): Backup
    {
        $admin = $this->adminUser();
        $backup = Backup::create([
            'filename' => "{$label}.zip",
            'path' => 'backups/2026/08/24',
            'type' => 'database',
            'location' => 'local',
            'size' => 42,
            'status' => 'completed',
            'created_by' => $admin->id,
            'completed_at' => now(),
        ]);

        $service = app(DatabaseBackupService::class);
        $path = $service->filePathFor($backup);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'fake-zip-content');

        return $backup;
    }

    // --- authorized admin -----------------------------------------------

    public function test_admin_can_view_backup_index(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get(route('admin.backups.index'));

        $response->assertOk();
    }

    public function test_admin_can_create_a_backup(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('admin.backups.store'), [
            'type' => 'database',
            'location' => 'local',
        ]);

        $response->assertRedirect(route('admin.backups.index'));
        $this->assertDatabaseHas('backups', ['status' => 'completed', 'created_by' => $admin->id]);
    }

    public function test_admin_can_download_a_backup(): void
    {
        $admin = $this->adminUser();
        $backup = $this->completedBackupWithFile();

        $response = $this->actingAs($admin)->get(route('admin.backups.download', $backup->id));

        $response->assertOk();
    }

    public function test_admin_can_delete_a_backup(): void
    {
        $admin = $this->adminUser();
        $backup = $this->completedBackupWithFile();
        $filePath = app(DatabaseBackupService::class)->filePathFor($backup);
        $this->assertFileExists($filePath);

        $response = $this->actingAs($admin)->delete(route('admin.backups.destroy', $backup->id));

        $response->assertRedirect(route('admin.backups.index'));
        $this->assertDatabaseMissing('backups', ['id' => $backup->id]);
        $this->assertFileDoesNotExist($filePath, 'destroy() must delete the same path store() wrote to -- the pre-existing path-mismatch bug this fix closes.');
    }

    // --- unauthorized role: every action blocked -------------------------

    public function test_clerk_cannot_view_backup_index(): void
    {
        $clerk = $this->clerkUser();

        $response = $this->actingAs($clerk)->get(route('admin.backups.index'));

        $response->assertForbidden();
    }

    public function test_clerk_cannot_create_a_backup(): void
    {
        $clerk = $this->clerkUser();

        $response = $this->actingAs($clerk)->post(route('admin.backups.store'), [
            'type' => 'database',
            'location' => 'local',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('backups', ['created_by' => $clerk->id]);
    }

    public function test_clerk_cannot_download_a_backup_direct_url(): void
    {
        $clerk = $this->clerkUser();
        $backup = $this->completedBackupWithFile();

        $response = $this->actingAs($clerk)->get(route('admin.backups.download', $backup->id));

        $response->assertForbidden();
    }

    public function test_clerk_cannot_delete_a_backup(): void
    {
        $clerk = $this->clerkUser();
        $backup = $this->completedBackupWithFile();

        $response = $this->actingAs($clerk)->delete(route('admin.backups.destroy', $backup->id));

        $response->assertForbidden();
        $this->assertDatabaseHas('backups', ['id' => $backup->id]);
    }

    public function test_clerk_cannot_schedule_a_backup(): void
    {
        $clerk = $this->clerkUser();

        $response = $this->actingAs($clerk)->post(route('admin.backups.schedule'), [
            'type' => 'database',
            'location' => 'local',
            'schedule_date' => now()->addDay()->toDateTimeString(),
        ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.backups.index'));

        $response->assertRedirect(route('login'));
    }

    // --- path traversal ----------------------------------------------------

    /**
     * download() must only ever serve a file that resolves inside the
     * backups directory, even if a Backup record's own path/filename were
     * ever malicious -- proves the containment check, not just its
     * presence.
     */
    public function test_download_rejects_a_backup_record_whose_path_escapes_the_backups_directory(): void
    {
        $admin = $this->adminUser();
        $backup = Backup::create([
            'filename' => 'passwd',
            'path' => '../../../../etc',
            'type' => 'database',
            'location' => 'local',
            'size' => 1,
            'status' => 'completed',
            'created_by' => $admin->id,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.backups.download', $backup->id));

        $response->assertNotFound();
    }

    // --- structural: not publicly reachable -------------------------------

    public function test_backups_directory_is_not_inside_the_public_storage_symlink_target(): void
    {
        $publicStorageTarget = realpath(public_path('storage'));
        $backupsDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupsDir);
        $backupsReal = realpath($backupsDir);

        $this->assertNotFalse($publicStorageTarget);
        $this->assertNotFalse($backupsReal);
        $this->assertStringStartsNotWith($publicStorageTarget, $backupsReal);
    }
}
