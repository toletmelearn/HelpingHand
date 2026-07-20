<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DummyStudentsSeeder extends Seeder
{
    public function run()
    {
        $classes = DB::table('classes')->get();
        $sections = DB::table('sections')->get();

        foreach ($classes as $class) {

            foreach ($sections->where('class_id',$class->id) as $section) {

                for ($i = 1; $i <= 10; $i++) {

                    DB::table('students')->insert([
                        'name' => 'Student '.$class->id.$section->id.$i,
                        'roll_no' => $i,
                        'class_id' => $class->id,
                        'section_id' => $section->id,
                        'father_name' => 'Father '.$i,
                        'mother_name' => 'Mother '.$i,
                        'phone' => '99999999'.$i,
                        'email' => 'student'.$class->id.$section->id.$i.'@test.com',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                }

            }
        }
    }
}
