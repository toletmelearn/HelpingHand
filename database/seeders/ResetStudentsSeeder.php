<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResetStudentsSeeder extends Seeder
{
    public function run()
    {
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        \DB::table('students')->truncate();
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // get classes
        $classes = \DB::table('school_classes')->get();
        $sections = \DB::table('sections')->get();

        foreach ($classes as $class) {

            // Since all sections have null class_id, we'll use all sections for all classes
            foreach ($sections as $section){

                for($i=1;$i<=10;$i++){

                    \DB::table('students')->insert([
                        'name' => 'Demo Student '.$class->id.$section->id.$i,
                        'roll_number' => $i,
                        'school_class_id' => $class->id,
                        'section_id' => $section->id,
                        'father_name' => 'Father '.$i,
                        'mother_name' => 'Mother '.$i,
                        'phone' => '90000000'.$i,
                        'mobile' => '98'.rand(10000000, 99999999),
                        'date_of_birth' => '2010-01-01',
                        'aadhar_number' => '1234567890'.$class->id.$section->id.$i,
                        'address' => 'Test Address',
                        'gender' => 'male',
                        'category' => 'General',
                        'class' => $class->name,
                        'section' => $section->name,
                        'nationality' => 'Indian',
                        'is_verified' => 1,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                }

            }
        }

        echo "Dummy students inserted successfully";
    }
}