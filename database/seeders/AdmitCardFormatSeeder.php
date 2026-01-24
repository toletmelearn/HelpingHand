<?php

namespace Database\Seeders;

use App\Models\AdmitCardFormat;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdmitCardFormatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@school.com')->first();
        
        // Create sample admit card formats
        AdmitCardFormat::create([
            'name' => 'Standard Format',
            'header_html' => '<h1>{school_name}</h1><p>Admit Card for Academic Session: {academic_session}</p>',
            'body_html' => '',
            'footer_html' => '<p>Please bring this admit card and valid ID proof to the exam hall.</p>',
            'is_active' => true,
            'created_by' => $admin ? $admin->id : null
        ]);
        
        AdmitCardFormat::create([
            'name' => 'Board Exam Format',
            'header_html' => '<h1>BOARD EXAMINATION</h1><h2>{school_name}</h2>',
            'body_html' => '',
            'footer_html' => '<p>Signature: _____________ Principal</p>',
            'is_active' => true,
            'created_by' => $admin ? $admin->id : null
        ]);
        
        $this->command->info('Admit card formats created successfully!');
    }
}
