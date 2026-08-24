<?php

namespace Tests\Feature\Admin;

use App\Models\Backup;
use App\Models\Role;
use App\Models\User;
use App\Services\Operations\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * P1 security remediation: the pre-existing web-based database restore
 * (OperationsController::backupRestore() -> Operations\BackupService::
 * restoreBackup() -> restoreDatabaseDump() -> DB::unprepared($sql)
 * against the application's live default database, with no target
 * confirmation, no pre-restore backup, no maintenance mode, no post-
 * restore verification) has been removed entirely. The new CLI-only
 * `backup:restore` (BackupRestoreCommand -> DatabaseRestoreService) is
 * now the ONE authoritative restore mechanism. This proves the old path
 * no longer exists in any form, while everything else on the Operations
 * Disaster Recovery page keeps working.
 */
class LegacyWebRestoreRemovalTest extends TestCase
{
    use RefreshDatabase;

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

    // --- the old route/action no longer exists -----------------------

    public function test_old_restore_route_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('operations.backup.restore'));
    }

    public function test_direct_post_to_the_old_restore_url_returns_not_found(): void
    {
        $admin = $this->adminUser();
        $backup = Backup::create([
            'filename' => 'legacy_test.sql', 'path' => 'backups/legacy_test.sql',
            'type' => 'database', 'location' => 'local', 'size' => 1,
            'status' => 'completed', 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post('/admin/operations/backup/restore/' . $backup->id);

        $response->assertNotFound();
        // The backup record itself must be completely unaffected -- no restore attempt of any kind occurred.
        $this->assertDatabaseHas('backups', ['id' => $backup->id, 'status' => 'completed']);
    }

    public function test_old_restore_methods_no_longer_exist_on_the_service(): void
    {
        $this->assertFalse(method_exists(BackupService::class, 'restoreBackup'));
        $this->assertFalse(method_exists(BackupService::class, 'restoreDatabaseDump'));
    }

    // --- everything else on the page keeps working ---------------------

    public function test_disaster_recovery_page_still_loads_for_admin(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get(route('operations.backup'));

        $response->assertOk();
        // The old restore-wizard copy is gone; the CLI pointer is now shown instead.
        $response->assertSee('backup:restore');
        $response->assertDontSee('Quick Restore Wizard');
    }

    public function test_backup_listing_still_works(): void
    {
        $admin = $this->adminUser();
        Backup::create([
            'filename' => 'listed.sql', 'path' => 'backups/listed.sql',
            'type' => 'database', 'location' => 'local', 'size' => 1,
            'status' => 'completed', 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('operations.backup'));

        $response->assertOk();
        $response->assertSee('listed.sql');
    }

    public function test_backup_download_still_works(): void
    {
        $admin = $this->adminUser();
        $backup = Backup::create([
            'filename' => 'downloadable.sql', 'path' => 'backups/downloadable.sql',
            'type' => 'database', 'location' => 'local', 'size' => 4,
            'status' => 'completed', 'created_by' => $admin->id,
        ]);
        $fullPath = storage_path('app/' . $backup->path);
        File::ensureDirectoryExists(dirname($fullPath));
        File::put($fullPath, 'test');

        $response = $this->actingAs($admin)->get(route('operations.backup.download', $backup->id));

        $response->assertOk();
    }

    public function test_backup_deletion_still_works(): void
    {
        $admin = $this->adminUser();
        $backup = Backup::create([
            'filename' => 'deletable.sql', 'path' => 'backups/deletable.sql',
            'type' => 'database', 'location' => 'local', 'size' => 1,
            'status' => 'completed', 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('operations.backup.delete', $backup->id));

        $response->assertRedirect(route('operations.backup'));
        $this->assertDatabaseMissing('backups', ['id' => $backup->id]);
    }

    // --- authorization on what remains is intact ------------------------

    public function test_non_admin_cannot_reach_disaster_recovery_page(): void
    {
        $clerk = $this->clerkUser();

        $response = $this->actingAs($clerk)->get(route('operations.backup'));

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_download_a_backup_via_operations(): void
    {
        $admin = $this->adminUser();
        $clerk = $this->clerkUser();
        $backup = Backup::create([
            'filename' => 'protected.sql', 'path' => 'backups/protected.sql',
            'type' => 'database', 'location' => 'local', 'size' => 1,
            'status' => 'completed', 'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($clerk)->get(route('operations.backup.download', $backup->id));

        $response->assertForbidden();
    }

    // --- new CLI restore remains the one authoritative mechanism --------

    public function test_new_cli_restore_command_remains_discoverable(): void
    {
        $this->assertArrayHasKey('backup:restore', Artisan::all());
    }
}
