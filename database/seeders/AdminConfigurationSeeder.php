<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the table if it doesn't exist
        if (!Schema::hasTable('admin_configurations')) {
            Schema::create('admin_configurations', function ($table) {
                $table->id();
                $table->string('module'); // e.g., 'biometric', 'fee', 'exam', 'attendance'
                $table->string('key'); // e.g., 'enable_biometric', 'auto_calculate_fees'
                $table->text('value')->nullable(); // JSON encoded for complex values
                $table->string('type')->default('boolean'); // boolean, string, integer, json
                $table->string('label'); // Human readable label
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
                $table->unique(['module', 'key']);
                $table->index('module');
                $table->index('is_active');
            });
            
            $this->command->info('Admin configurations table created successfully.');
        }
        
        // Seed basic configuration data
        $user = DB::table('users')->first();
        $userId = $user ? $user->id : null;
        
        $configurations = [
            [
                'module' => 'biometric',
                'key' => 'enable_biometric',
                'value' => 'true',
                'type' => 'boolean',
                'label' => 'Enable Biometric System',
                'description' => 'Enable or disable the biometric attendance system',
                'is_active' => true,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'module' => 'biometric',
                'key' => 'auto_sync_interval',
                'value' => '30',
                'type' => 'integer',
                'label' => 'Auto Sync Interval (minutes)',
                'description' => 'Interval in minutes for automatic biometric data synchronization',
                'is_active' => true,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'module' => 'fee',
                'key' => 'auto_calculate_fees',
                'value' => 'true',
                'type' => 'boolean',
                'label' => 'Auto Calculate Fees',
                'description' => 'Automatically calculate fees based on student category and class',
                'is_active' => true,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'module' => 'exam',
                'key' => 'enable_online_exams',
                'value' => 'false',
                'type' => 'boolean',
                'label' => 'Enable Online Exams',
                'description' => 'Enable or disable online examination system',
                'is_active' => true,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'module' => 'attendance',
                'key' => 'working_days_per_week',
                'value' => '6',
                'type' => 'integer',
                'label' => 'Working Days Per Week',
                'description' => 'Number of working days in a week for attendance calculation',
                'is_active' => true,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];
        
        // Insert configurations if they don't exist
        foreach ($configurations as $config) {
            $exists = DB::table('admin_configurations')
                ->where('module', $config['module'])
                ->where('key', $config['key'])
                ->exists();
                
            if (!$exists) {
                DB::table('admin_configurations')->insert($config);
            }
        }
        
        $this->command->info('Admin configurations seeded successfully.');
    }
}
