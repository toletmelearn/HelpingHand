<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CertificateTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Schema::hasTable('certificate_templates') && Schema::hasTable('users')) {
            $user = DB::table('users')->first();
            
            if ($user) {
                $templates = [
                    [
                        'name' => 'Transfer Certificate',
                        'type' => 'tc',
                        'template_content' => '<html><body><h1>Transfer Certificate</h1><p>Student Name: {{student_name}}</p><p>Class: {{class}}</p><p>Date: {{date}}</p></body></html>',
                        'template_variables' => json_encode(['student_name', 'class', 'date']),
                        'is_default' => true,
                        'is_active' => true,
                        'created_by' => $user->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ],
                    [
                        'name' => 'Bonafide Certificate',
                        'type' => 'bonafide',
                        'template_content' => '<html><body><h1>Bonafide Certificate</h1><p>This is to certify that {{student_name}} is a bonafide student.</p><p>Class: {{class}}</p><p>Date: {{date}}</p></body></html>',
                        'template_variables' => json_encode(['student_name', 'class', 'date']),
                        'is_default' => false,
                        'is_active' => true,
                        'created_by' => $user->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ],
                    [
                        'name' => 'Character Certificate',
                        'type' => 'character',
                        'template_content' => '<html><body><h1>Character Certificate</h1><p>This is to certify that {{student_name}} has good character.</p><p>Class: {{class}}</p><p>Date: {{date}}</p></body></html>',
                        'template_variables' => json_encode(['student_name', 'class', 'date']),
                        'is_default' => false,
                        'is_active' => true,
                        'created_by' => $user->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                ];
                
                DB::table('certificate_templates')->insert($templates);
                $this->command->info('Certificate templates seeded successfully.');
            } else {
                $this->command->info('No users found. Please create a user first.');
            }
        } else {
            $this->command->info('Required tables not found.');
        }
    }
}
