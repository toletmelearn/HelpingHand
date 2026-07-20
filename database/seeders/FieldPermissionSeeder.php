<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FieldPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if the table exists
        if (!Schema::hasTable('field_permissions')) {
            $this->command->info('Field permissions table does not exist. Please run migrations first.');
            return;
        }
        
        // Define default field permissions
        $permissions = [
            // Student field permissions
            [
                'model_type' => 'student',
                'field_name' => 'name',
                'role' => 'admin',
                'permission_level' => 'editable',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'model_type' => 'student',
                'field_name' => 'name',
                'role' => 'class_teacher',
                'permission_level' => 'read_only',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'model_type' => 'student',
                'field_name' => 'address',
                'role' => 'admin',
                'permission_level' => 'editable',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'model_type' => 'student',
                'field_name' => 'address',
                'role' => 'class_teacher',
                'permission_level' => 'hidden',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'model_type' => 'student',
                'field_name' => 'phone',
                'role' => 'admin',
                'permission_level' => 'editable',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'model_type' => 'student',
                'field_name' => 'phone',
                'role' => 'class_teacher',
                'permission_level' => 'read_only',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            // Teacher field permissions
            [
                'model_type' => 'teacher',
                'field_name' => 'name',
                'role' => 'admin',
                'permission_level' => 'editable',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'model_type' => 'teacher',
                'field_name' => 'name',
                'role' => 'class_teacher',
                'permission_level' => 'read_only',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'model_type' => 'teacher',
                'field_name' => 'email',
                'role' => 'admin',
                'permission_level' => 'editable',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'model_type' => 'teacher',
                'field_name' => 'email',
                'role' => 'class_teacher',
                'permission_level' => 'hidden',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'model_type' => 'teacher',
                'field_name' => 'phone',
                'role' => 'admin',
                'permission_level' => 'editable',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'model_type' => 'teacher',
                'field_name' => 'phone',
                'role' => 'class_teacher',
                'permission_level' => 'read_only',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];
        
        // Insert permissions if they don't exist
        foreach ($permissions as $permission) {
            $exists = DB::table('field_permissions')
                ->where('model_type', $permission['model_type'])
                ->where('field_name', $permission['field_name'])
                ->where('role', $permission['role'])
                ->exists();
                
            if (!$exists) {
                DB::table('field_permissions')->insert($permission);
            }
        }
        
        $this->command->info('Field permissions seeded successfully.');
    }
}
