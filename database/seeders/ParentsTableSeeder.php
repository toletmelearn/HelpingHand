<?php

use Illuminate\Database\Seeder;
use App\Models\ParentModel;
use App\Models\Student;

class ParentsTableSeeder extends Seeder
{
    public function run()
    {
        // Get some students to link to parents
        $students = Student::take(3)->get();
        
        foreach ($students as $index => $student) {
            ParentModel::create([
                'name' => 'Parent of ' . $student->name,
                'email' => 'parent' . ($index + 1) . '@example.com',
                'phone' => '987654321' . ($index + 1),
                'password' => bcrypt('123456'),
                'student_id' => $student->id,
            ]);
        }
        
        echo "Created " . count($students) . " parent records\n";
    }
}