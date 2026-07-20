<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $roles = [
            [
                'name' => 'accountant',
                'display_name' => 'Accountant',
                'description' => 'Accountant with access to fee collection, bookkeeping, reconciliation and financial records',
            ],
            [
                'name' => 'clerk',
                'display_name' => 'Clerk',
                'description' => 'Clerk with access to student admissions, daily operations and administrative data entry',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::whereIn('name', ['accountant', 'clerk'])->delete();
    }
};
