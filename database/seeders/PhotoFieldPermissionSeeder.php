<?php

namespace Database\Seeders;

use App\Models\FieldPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PhotoFieldPermissionSeeder extends Seeder
{
    /**
     * Roles allowed to upload a Student/Teacher photo. Uses the real
     * runtime Role name 'class-teacher' (hyphenated) -- not the
     * 'class_teacher' (underscore) string FieldPermissionSeeder mistakenly
     * uses elsewhere, which never matches a real role at runtime.
     */
    private const EDITABLE_ROLES = ['admin', 'super-admin', 'clerk', 'accountant', 'receptionist', 'class-teacher'];

    /**
     * Every other role seeded in this app today. FieldPermissionHelper
     * defaults to "allow" when no row exists for a role/field pair, so
     * these need an explicit 'hidden' row to actually be excluded.
     */
    private const OTHER_ROLES = ['teacher', 'student', 'parent', 'guard'];

    private const GOVERNED_FIELDS = [
        ['model_type' => 'student', 'field_name' => 'photo'],
        ['model_type' => 'teacher', 'field_name' => 'profile_image'],
    ];

    public function run(): void
    {
        if (!Schema::hasTable('field_permissions')) {
            $this->command->info('field_permissions table does not exist. Please run migrations first.');
            return;
        }

        foreach (self::GOVERNED_FIELDS as $field) {
            foreach (self::EDITABLE_ROLES as $role) {
                $this->upsert($field['model_type'], $field['field_name'], $role, 'editable');
            }
            foreach (self::OTHER_ROLES as $role) {
                $this->upsert($field['model_type'], $field['field_name'], $role, 'hidden');
            }
        }

        $this->command->info('Photo field permissions seeded successfully.');
    }

    private function upsert(string $modelType, string $fieldName, string $role, string $permissionLevel): void
    {
        FieldPermission::firstOrCreate(
            ['model_type' => $modelType, 'field_name' => $fieldName, 'role' => $role],
            ['permission_level' => $permissionLevel, 'is_active' => true]
        );
    }
}
