<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\Teacher;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data
        Student::truncate();
        Teacher::truncate();
        
        // Create 5 Students with UNIQUE Aadhar numbers
        $students = [
            [
                'name' => 'Rahul Sharma',
                'father_name' => 'Rajesh Sharma',
                'mother_name' => 'Priya Sharma',
                'date_of_birth' => '2010-05-15',
                'aadhar_number' => '100000000001', // Changed from 111111111111
                'phone' => '9876543210',
                'address' => '123 Main Street, Delhi',
                'gender' => 'male',
                'category' => 'General',
                'class' => 'Class 5',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Priya Singh',
                'father_name' => 'Amit Singh',
                'mother_name' => 'Sunita Singh',
                'date_of_birth' => '2011-08-20',
                'aadhar_number' => '100000000002', // Changed from 222222222222
                'phone' => '9876543211',
                'address' => '456 Park Road, Delhi',
                'gender' => 'female',
                'category' => 'OBC',
                'class' => 'Class 6',
                'section' => 'B',
                'roll_number' => 2
            ],
            [
                'name' => 'Amit Kumar',
                'father_name' => 'Ramesh Kumar',
                'mother_name' => 'Sita Kumar',
                'date_of_birth' => '2010-11-30',
                'aadhar_number' => '100000000003', // Changed from 333333333333
                'phone' => '9876543212',
                'address' => '789 Colony, Delhi',
                'gender' => 'male',
                'category' => 'SC',
                'class' => 'Class 7',
                'section' => 'C',
                'roll_number' => 3
            ],
            [
                'name' => 'Neha Gupta',
                'father_name' => 'Sanjay Gupta',
                'mother_name' => 'Rekha Gupta',
                'date_of_birth' => '2012-03-25',
                'aadhar_number' => '100000000004', // Changed from 444444444444
                'phone' => '9876543213',
                'address' => '321 Market, Delhi',
                'gender' => 'female',
                'category' => 'General',
                'class' => 'Class 4',
                'section' => 'A',
                'roll_number' => 4
            ],
            [
                'name' => 'Rohit Yadav',
                'father_name' => 'Vijay Yadav',
                'mother_name' => 'Mona Yadav',
                'date_of_birth' => '2009-12-10',
                'aadhar_number' => '100000000005', // Changed from 555555555555
                'phone' => '9876543214',
                'address' => '654 Street, Delhi',
                'gender' => 'male',
                'category' => 'ST',
                'class' => 'Class 8',
                'section' => 'B',
                'roll_number' => 5
            ]
        ];
        
        // Create 5 Teachers with UNIQUE Aadhar numbers
        $teachers = [
            [
                'name' => 'Rajesh Kumar',
                'email' => 'rajesh@school.com',
                'phone' => '9876543220',
                'qualification' => 'M.Ed',
                'subject_specialization' => 'Mathematics',
                'date_of_joining' => '2020-06-01',
                'salary' => 45000.00,
                'address' => '789 Teacher Colony, Delhi',
                'aadhar_number' => '200000000001', // Changed from 666666666666
                'status' => 'active',
                'gender' => 'male',
                'wing' => 'senior',
                'teacher_type' => 'TGT',
                'designation' => 'Mathematics Teacher'
            ],
            [
                'name' => 'Sunita Verma',
                'email' => 'sunita@school.com',
                'phone' => '9876543221',
                'qualification' => 'B.Ed',
                'subject_specialization' => 'English',
                'date_of_joining' => '2021-03-15',
                'salary' => 38000.00,
                'address' => '321 Educator Street, Delhi',
                'aadhar_number' => '200000000002', // Changed from 777777777777
                'status' => 'active',
                'gender' => 'female',
                'wing' => 'junior',
                'teacher_type' => 'PRT',
                'designation' => 'English Teacher'
            ],
            [
                'name' => 'Anil Sharma',
                'email' => 'anil@school.com',
                'phone' => '9876543222',
                'qualification' => 'M.Sc B.Ed',
                'subject_specialization' => 'Science',
                'date_of_joining' => '2019-08-10',
                'salary' => 52000.00,
                'address' => '123 Science Nagar, Delhi',
                'aadhar_number' => '200000000003', // Changed from 888888888888
                'status' => 'active',
                'gender' => 'male',
                'wing' => 'senior',
                'teacher_type' => 'PGT',
                'designation' => 'Science HOD'
            ],
            [
                'name' => 'Kavita Singh',
                'email' => 'kavita@school.com',
                'phone' => '9876543223',
                'qualification' => 'B.Ed',
                'subject_specialization' => 'Hindi',
                'date_of_joining' => '2022-01-20',
                'salary' => 35000.00,
                'address' => '456 Hindi Lane, Delhi',
                'aadhar_number' => '200000000004', // Changed from 999999999999
                'status' => 'on_leave',
                'gender' => 'female',
                'wing' => 'primary',
                'teacher_type' => 'PRT',
                'designation' => 'Hindi Teacher'
            ],
            [
                'name' => 'Vikram Patel',
                'email' => 'vikram@school.com',
                'phone' => '9876543224',
                'qualification' => 'M.Ed',
                'subject_specialization' => 'Social Studies',
                'date_of_joining' => '2018-04-05',
                'salary' => 48000.00,
                'address' => '789 Patel Street, Delhi',
                'aadhar_number' => '200000000005', // Changed from 000000000001
                'status' => 'inactive',
                'gender' => 'male',
                'wing' => 'junior',
                'teacher_type' => 'TGT',
                'designation' => 'Social Studies Teacher'
            ]
        ];
        
        // Insert data
        foreach ($students as $student) {
            Student::create($student);
        }
        
        foreach ($teachers as $teacher) {
            Teacher::create($teacher);
        }
        
        echo "✅ Sample data created successfully!\n";
        echo "   • 5 Students created\n";
        echo "   • 5 Teachers created\n";
    }
}