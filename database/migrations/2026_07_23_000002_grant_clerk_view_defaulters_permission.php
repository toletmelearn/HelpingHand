<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Clerk is meant to sit alongside Admin/Principal/Accountant in the
     * "unscoped, see-every-defaulter" tier -- documented as a gap in
     * 2026_07_23_000001 but never actually granted. Needed so Clerk can
     * view and export the Defaulter Registry (Class-wise/Month-wise/
     * Quarter-wise PDF and Excel), same as Admin and Accountant.
     *
     * 'view-defaulters' is normally created by database/seeders/
     * PermissionSeeder.php, a seeder that doesn't run automatically on a
     * fresh migrate (RefreshDatabase in tests, or a fresh install that
     * hasn't run db:seed yet) -- grantPermission() silently no-ops if the
     * Permission row doesn't exist yet, so firstOrCreate it here rather
     * than assuming it's already there.
     */
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'view-defaulters']);

        $clerkRole = Role::where('name', 'clerk')->first();
        if ($clerkRole) {
            $clerkRole->grantPermission('view-defaulters');
        }
    }

    public function down(): void
    {
        // Roles/permissions are shared reference data -- intentionally not
        // deleted on rollback, matching this codebase's existing convention.
    }
};
