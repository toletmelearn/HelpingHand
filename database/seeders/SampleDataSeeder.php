<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\Teacher;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data with foreign key constraints disabled
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        \App\Models\Attendance::truncate();
        \App\Models\BellTiming::truncate();
        \App\Models\ExamPaper::truncate();
        Student::truncate();
        Teacher::truncate();
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        // Create comprehensive student data with all classes
        $students = [
            // Class 1
            [
                'name' => 'Arjun Malhotra',
                'father_name' => 'Ravi Malhotra',
                'mother_name' => 'Sneha Malhotra',
                'date_of_birth' => '2017-06-15',
                'aadhar_number' => '100000000001',
                'phone' => '9876543210',
                'address' => '123 MG Road, Delhi',
                'gender' => 'male',
                'category' => 'General',
                'class' => 'Class 1',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Diya Sharma',
                'father_name' => 'Rajesh Sharma',
                'mother_name' => 'Priya Sharma',
                'date_of_birth' => '2017-08-20',
                'aadhar_number' => '100000000002',
                'phone' => '9876543211',
                'address' => '456 Connaught Place, Delhi',
                'gender' => 'female',
                'category' => 'OBC',
                'class' => 'Class 1',
                'section' => 'B',
                'roll_number' => 2
            ],
            // Class 2
            [
                'name' => 'Advik Gupta',
                'father_name' => 'Sanjay Gupta',
                'mother_name' => 'Pooja Gupta',
                'date_of_birth' => '2016-04-10',
                'aadhar_number' => '100000000003',
                'phone' => '9876543212',
                'address' => '789 Nehru Place, Delhi',
                'gender' => 'male',
                'category' => 'General',
                'class' => 'Class 2',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Ananya Reddy',
                'father_name' => 'Ramesh Reddy',
                'mother_name' => 'Lakshmi Reddy',
                'date_of_birth' => '2016-07-15',
                'aadhar_number' => '100000000004',
                'phone' => '9876543213',
                'address' => '321 Banjara Hills, Hyderabad',
                'gender' => 'female',
                'category' => 'SC',
                'class' => 'Class 2',
                'section' => 'B',
                'roll_number' => 2
            ],
            // Class 3
            [
                'name' => 'Vihaan Patel',
                'father_name' => 'Dinesh Patel',
                'mother_name' => 'Sheetal Patel',
                'date_of_birth' => '2015-03-25',
                'aadhar_number' => '100000000005',
                'phone' => '9876543214',
                'address' => '654 SG Highway, Ahmedabad',
                'gender' => 'male',
                'category' => 'General',
                'class' => 'Class 3',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Aadhya Nair',
                'father_name' => 'Manoj Nair',
                'mother_name' => 'Anjali Nair',
                'date_of_birth' => '2015-09-12',
                'aadhar_number' => '100000000006',
                'phone' => '9876543215',
                'address' => '987 MG Road, Kochi',
                'gender' => 'female',
                'category' => 'OBC',
                'class' => 'Class 3',
                'section' => 'B',
                'roll_number' => 2
            ],
            // Class 4
            [
                'name' => 'Reyansh Kumar',
                'father_name' => 'Amit Kumar',
                'mother_name' => 'Sunita Kumar',
                'date_of_birth' => '2014-02-08',
                'aadhar_number' => '100000000007',
                'phone' => '9876543216',
                'address' => '147 Janpath, New Delhi',
                'gender' => 'male',
                'category' => 'General',
                'class' => 'Class 4',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Ahana Joshi',
                'father_name' => 'Vikram Joshi',
                'mother_name' => 'Ritu Joshi',
                'date_of_birth' => '2014-06-30',
                'aadhar_number' => '100000000008',
                'phone' => '9876543217',
                'address' => '258 Ashok Vihar, Delhi',
                'gender' => 'female',
                'category' => 'General',
                'class' => 'Class 4',
                'section' => 'B',
                'roll_number' => 2
            ],
            // Class 5
            [
                'name' => 'Rudra Singh',
                'father_name' => 'Raj Singh',
                'mother_name' => 'Meera Singh',
                'date_of_birth' => '2013-11-15',
                'aadhar_number' => '100000000009',
                'phone' => '9876543218',
                'address' => '369 Golf Course, Gurgaon',
                'gender' => 'male',
                'category' => 'ST',
                'class' => 'Class 5',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Kiara Mehra',
                'father_name' => 'Rohit Mehra',
                'mother_name' => 'Preeti Mehra',
                'date_of_birth' => '2013-05-22',
                'aadhar_number' => '100000000010',
                'phone' => '9876543219',
                'address' => '741 Sector 29, Noida',
                'gender' => 'female',
                'category' => 'SC',
                'class' => 'Class 5',
                'section' => 'B',
                'roll_number' => 2
            ],
            // Class 6
            [
                'name' => 'Aarav Choudhary',
                'father_name' => 'Sunil Choudhary',
                'mother_name' => 'Kavita Choudhary',
                'date_of_birth' => '2012-08-10',
                'aadhar_number' => '100000000011',
                'phone' => '9876543220',
                'address' => '852 Rajouri Garden, Delhi',
                'gender' => 'male',
                'category' => 'General',
                'class' => 'Class 6',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Myra Khan',
                'father_name' => 'Salman Khan',
                'mother_name' => 'Fatima Khan',
                'date_of_birth' => '2012-12-05',
                'aadhar_number' => '100000000012',
                'phone' => '9876543221',
                'address' => '963 Karol Bagh, Delhi',
                'gender' => 'female',
                'category' => 'OBC',
                'class' => 'Class 6',
                'section' => 'B',
                'roll_number' => 2
            ],
            // Class 7
            [
                'name' => 'Krishna Iyer',
                'father_name' => 'Gopal Iyer',
                'mother_name' => 'Sarika Iyer',
                'date_of_birth' => '2011-01-20',
                'aadhar_number' => '100000000013',
                'phone' => '9876543222',
                'address' => '159 Brigade Road, Bangalore',
                'gender' => 'male',
                'category' => 'General',
                'class' => 'Class 7',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Riya Desai',
                'father_name' => 'Nitin Desai',
                'mother_name' => 'Shalini Desai',
                'date_of_birth' => '2011-04-18',
                'aadhar_number' => '100000000014',
                'phone' => '9876543223',
                'address' => '357 Andheri East, Mumbai',
                'gender' => 'female',
                'category' => 'General',
                'class' => 'Class 7',
                'section' => 'B',
                'roll_number' => 2
            ],
            // Class 8
            [
                'name' => 'Shaurya Agarwal',
                'father_name' => 'Rakesh Agarwal',
                'mother_name' => 'Sonal Agarwal',
                'date_of_birth' => '2010-07-25',
                'aadhar_number' => '100000000015',
                'phone' => '9876543224',
                'address' => '258 Worli Sea Face, Mumbai',
                'gender' => 'male',
                'category' => 'General',
                'class' => 'Class 8',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Anvi Bhatia',
                'father_name' => 'Arun Bhatia',
                'mother_name' => 'Rashmi Bhatia',
                'date_of_birth' => '2010-10-12',
                'aadhar_number' => '100000000016',
                'phone' => '9876543225',
                'address' => '159 Koregaon Park, Pune',
                'gender' => 'female',
                'category' => 'OBC',
                'class' => 'Class 8',
                'section' => 'B',
                'roll_number' => 2
            ],
            // Class 9
            [
                'name' => 'Arnav Shah',
                'father_name' => 'Vishal Shah',
                'mother_name' => 'Priya Shah',
                'date_of_birth' => '2009-03-15',
                'aadhar_number' => '100000000017',
                'phone' => '9876543226',
                'address' => '753 Juhu Beach, Mumbai',
                'gender' => 'male',
                'category' => 'General',
                'class' => 'Class 9',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Tanvi Sharma',
                'father_name' => 'Manoj Sharma',
                'mother_name' => 'Sushma Sharma',
                'date_of_birth' => '2009-06-08',
                'aadhar_number' => '100000000018',
                'phone' => '9876543227',
                'address' => '951 Bandra West, Mumbai',
                'gender' => 'female',
                'category' => 'General',
                'class' => 'Class 9',
                'section' => 'B',
                'roll_number' => 2
            ],
            // Class 10
            [
                'name' => 'Vivaan Singhania',
                'father_name' => 'Rajat Singhania',
                'mother_name' => 'Neha Singhania',
                'date_of_birth' => '2008-11-30',
                'aadhar_number' => '100000000019',
                'phone' => '9876543228',
                'address' => '357 Salt Lake, Kolkata',
                'gender' => 'male',
                'category' => 'General',
                'class' => 'Class 10',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Ananya Verma',
                'father_name' => 'Alok Verma',
                'mother_name' => 'Sangeeta Verma',
                'date_of_birth' => '2008-02-14',
                'aadhar_number' => '100000000020',
                'phone' => '9876543229',
                'address' => '753 Park Street, Kolkata',
                'gender' => 'female',
                'category' => 'OBC',
                'class' => 'Class 10',
                'section' => 'B',
                'roll_number' => 2
            ],
            // Class 11 Science
            [
                'name' => 'Rishi Menon',
                'father_name' => 'Kiran Menon',
                'mother_name' => 'Latha Menon',
                'date_of_birth' => '2007-05-10',
                'aadhar_number' => '100000000021',
                'phone' => '9876543230',
                'address' => '159 Residency Road, Bangalore',
                'gender' => 'male',
                'category' => 'General',
                'class' => 'Class 11 Science',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Advika Nair',
                'father_name' => 'Suresh Nair',
                'mother_name' => 'Divya Nair',
                'date_of_birth' => '2007-08-25',
                'aadhar_number' => '100000000022',
                'phone' => '9876543231',
                'address' => '951 MG Road, Bangalore',
                'gender' => 'female',
                'category' => 'General',
                'class' => 'Class 11 Science',
                'section' => 'B',
                'roll_number' => 2
            ],
            // Class 11 Commerce
            [
                'name' => 'Kabir Rastogi',
                'father_name' => 'Ashok Rastogi',
                'mother_name' => 'Sunita Rastogi',
                'date_of_birth' => '2007-01-18',
                'aadhar_number' => '100000000023',
                'phone' => '9876543232',
                'address' => '357 Phoenix Market City, Pune',
                'gender' => 'male',
                'category' => 'General',
                'class' => 'Class 11 Commerce',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Ira Bhatnagar',
                'father_name' => 'Rajesh Bhatnagar',
                'mother_name' => 'Sapna Bhatnagar',
                'date_of_birth' => '2007-04-05',
                'aadhar_number' => '100000000024',
                'phone' => '9876543233',
                'address' => '753 Hinjewadi, Pune',
                'gender' => 'female',
                'category' => 'OBC',
                'class' => 'Class 11 Commerce',
                'section' => 'B',
                'roll_number' => 2
            ],
            // Class 11 Arts
            [
                'name' => 'Aryan Khanna',
                'father_name' => 'Rahul Khanna',
                'mother_name' => 'Poonam Khanna',
                'date_of_birth' => '2007-07-20',
                'aadhar_number' => '100000000025',
                'phone' => '9876543234',
                'address' => '159 Connaught Circus, Delhi',
                'gender' => 'male',
                'category' => 'General',
                'class' => 'Class 11 Arts',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Dia Chopra',
                'father_name' => 'Vikas Chopra',
                'mother_name' => 'Pooja Chopra',
                'date_of_birth' => '2007-10-12',
                'aadhar_number' => '100000000026',
                'phone' => '9876543235',
                'address' => '357 GK II, Delhi',
                'gender' => 'female',
                'category' => 'General',
                'class' => 'Class 11 Arts',
                'section' => 'B',
                'roll_number' => 2
            ],
            // Class 12 Science
            [
                'name' => 'Aarush Bansal',
                'father_name' => 'Sanjeev Bansal',
                'mother_name' => 'Shilpa Bansal',
                'date_of_birth' => '2006-03-08',
                'aadhar_number' => '100000000027',
                'phone' => '9876543236',
                'address' => '753 HSR Layout, Bangalore',
                'gender' => 'male',
                'category' => 'General',
                'class' => 'Class 12 Science',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Aarya Kulkarni',
                'father_name' => 'Dinesh Kulkarni',
                'mother_name' => 'Sheetal Kulkarni',
                'date_of_birth' => '2006-06-15',
                'aadhar_number' => '100000000028',
                'phone' => '9876543237',
                'address' => '159 Kothrud, Pune',
                'gender' => 'female',
                'category' => 'OBC',
                'class' => 'Class 12 Science',
                'section' => 'B',
                'roll_number' => 2
            ],
            // Class 12 Commerce
            [
                'name' => 'Dev Mehta',
                'father_name' => 'Rajan Mehta',
                'mother_name' => 'Komal Mehta',
                'date_of_birth' => '2006-09-22',
                'aadhar_number' => '100000000029',
                'phone' => '9876543238',
                'address' => '951 Santacruz West, Mumbai',
                'gender' => 'male',
                'category' => 'General',
                'class' => 'Class 12 Commerce',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Pari Jain',
                'father_name' => 'Rohit Jain',
                'mother_name' => 'Anjali Jain',
                'date_of_birth' => '2006-12-05',
                'aadhar_number' => '100000000030',
                'phone' => '9876543239',
                'address' => '357 Koregaon Park, Pune',
                'gender' => 'female',
                'category' => 'General',
                'class' => 'Class 12 Commerce',
                'section' => 'B',
                'roll_number' => 2
            ],
            // Class 12 Arts
            [
                'name' => 'Veer Sood',
                'father_name' => 'Manoj Sood',
                'mother_name' => 'Priya Sood',
                'date_of_birth' => '2006-02-18',
                'aadhar_number' => '100000000031',
                'phone' => '9876543240',
                'address' => '753 Malviya Nagar, Delhi',
                'gender' => 'male',
                'category' => 'General',
                'class' => 'Class 12 Arts',
                'section' => 'A',
                'roll_number' => 1
            ],
            [
                'name' => 'Anaya Oberoi',
                'father_name' => 'Vikram Oberoi',
                'mother_name' => 'Shilpa Oberoi',
                'date_of_birth' => '2006-05-30',
                'aadhar_number' => '100000000032',
                'phone' => '9876543241',
                'address' => '159 Defence Colony, Delhi',
                'gender' => 'female',
                'category' => 'OBC',
                'class' => 'Class 12 Arts',
                'section' => 'B',
                'roll_number' => 2
            ]
        ];
        
        // Create comprehensive teacher data with different types and subjects
        $teachers = [
            // Primary Wing Teachers (PRT)
            [
                'name' => 'Priya Sharma',
                'email' => 'priya.prt@school.com',
                'phone' => '9876543301',
                'qualification' => 'B.Ed',
                'subject_specialization' => 'Primary Education',
                'date_of_joining' => '2020-06-01',
                'salary' => 35000.00,
                'address' => '101 MG Road, Delhi',
                'aadhar_number' => '200000000001',
                'status' => 'active',
                'gender' => 'female',
                'wing' => 'primary',
                'teacher_type' => 'PRT',
                'designation' => 'Primary Teacher',
                'employee_id' => 'EMP001'
            ],
            [
                'name' => 'Rajesh Gupta',
                'email' => 'rajesh.prt@school.com',
                'phone' => '9876543302',
                'qualification' => 'B.Ed',
                'subject_specialization' => 'Primary Mathematics',
                'date_of_joining' => '2019-08-15',
                'salary' => 36000.00,
                'address' => '102 Connaught Place, Delhi',
                'aadhar_number' => '200000000002',
                'status' => 'active',
                'gender' => 'male',
                'wing' => 'primary',
                'teacher_type' => 'PRT',
                'designation' => 'Mathematics Teacher (Primary)',
                'employee_id' => 'EMP002'
            ],
            [
                'name' => 'Sunita Verma',
                'email' => 'sunita.prt@school.com',
                'phone' => '9876543303',
                'qualification' => 'B.Ed',
                'subject_specialization' => 'English',
                'date_of_joining' => '2021-03-10',
                'salary' => 34000.00,
                'address' => '103 South Extension, Delhi',
                'aadhar_number' => '200000000003',
                'status' => 'active',
                'gender' => 'female',
                'wing' => 'primary',
                'teacher_type' => 'PRT',
                'designation' => 'English Teacher (Primary)',
                'employee_id' => 'EMP003'
            ],
            [
                'name' => 'Amit Patel',
                'email' => 'amit.prt@school.com',
                'phone' => '9876543304',
                'qualification' => 'B.Ed',
                'subject_specialization' => 'Environmental Studies',
                'date_of_joining' => '2020-11-05',
                'salary' => 33000.00,
                'address' => '104 Greater Kailash, Delhi',
                'aadhar_number' => '200000000004',
                'status' => 'active',
                'gender' => 'male',
                'wing' => 'primary',
                'teacher_type' => 'PRT',
                'designation' => 'EVS Teacher (Primary)',
                'employee_id' => 'EMP004'
            ],
            [
                'name' => 'Kavita Singh',
                'email' => 'kavita.prt@school.com',
                'phone' => '9876543305',
                'qualification' => 'B.Ed',
                'subject_specialization' => 'Hindi',
                'date_of_joining' => '2022-01-20',
                'salary' => 32000.00,
                'address' => '105 Lajpat Nagar, Delhi',
                'aadhar_number' => '200000000005',
                'status' => 'active',
                'gender' => 'female',
                'wing' => 'primary',
                'teacher_type' => 'PRT',
                'designation' => 'Hindi Teacher (Primary)',
                'employee_id' => 'EMP005'
            ],
            // Junior Wing Teachers (TGT)
            [
                'name' => 'Anil Sharma',
                'email' => 'anil.tgt@school.com',
                'phone' => '9876543306',
                'qualification' => 'M.Ed',
                'subject_specialization' => 'Mathematics',
                'date_of_joining' => '2018-07-15',
                'salary' => 45000.00,
                'address' => '201 Nehru Place, Delhi',
                'aadhar_number' => '200000000006',
                'status' => 'active',
                'gender' => 'male',
                'wing' => 'junior',
                'teacher_type' => 'TGT',
                'designation' => 'Mathematics Teacher (TGT)',
                'employee_id' => 'EMP006'
            ],
            [
                'name' => 'Sneha Reddy',
                'email' => 'sneha.tgt@school.com',
                'phone' => '9876543307',
                'qualification' => 'M.Ed',
                'subject_specialization' => 'Science',
                'date_of_joining' => '2019-04-20',
                'salary' => 46000.00,
                'address' => '202 Jubilee Hills, Hyderabad',
                'aadhar_number' => '200000000007',
                'status' => 'active',
                'gender' => 'female',
                'wing' => 'junior',
                'teacher_type' => 'TGT',
                'designation' => 'Science Teacher (TGT)',
                'employee_id' => 'EMP007'
            ],
            [
                'name' => 'Vikram Iyer',
                'email' => 'vikram.tgt@school.com',
                'phone' => '9876543308',
                'qualification' => 'M.Ed',
                'subject_specialization' => 'English',
                'date_of_joining' => '2017-09-10',
                'salary' => 44000.00,
                'address' => '203 Indiranagar, Bangalore',
                'aadhar_number' => '200000000008',
                'status' => 'active',
                'gender' => 'male',
                'wing' => 'junior',
                'teacher_type' => 'TGT',
                'designation' => 'English Teacher (TGT)',
                'employee_id' => 'EMP008'
            ],
            [
                'name' => 'Pooja Nair',
                'email' => 'pooja.tgt@school.com',
                'phone' => '9876543309',
                'qualification' => 'M.Ed',
                'subject_specialization' => 'Social Studies',
                'date_of_joining' => '2020-02-28',
                'salary' => 43000.00,
                'address' => '204 Thiruvankulam, Kochi',
                'aadhar_number' => '200000000009',
                'status' => 'active',
                'gender' => 'female',
                'wing' => 'junior',
                'teacher_type' => 'TGT',
                'designation' => 'Social Studies Teacher (TGT)',
                'employee_id' => 'EMP009'
            ],
            [
                'name' => 'Rahul Mehta',
                'email' => 'rahul.tgt@school.com',
                'phone' => '9876543310',
                'qualification' => 'M.Ed',
                'subject_specialization' => 'Hindi',
                'date_of_joining' => '2018-12-05',
                'salary' => 42000.00,
                'address' => '205 Andheri West, Mumbai',
                'aadhar_number' => '200000000010',
                'status' => 'active',
                'gender' => 'male',
                'wing' => 'junior',
                'teacher_type' => 'TGT',
                'designation' => 'Hindi Teacher (TGT)',
                'employee_id' => 'EMP010'
            ],
            // Senior Wing Teachers (PGT)
            [
                'name' => 'Dr. Manoj Kumar',
                'email' => 'manoj.pgt@school.com',
                'phone' => '9876543311',
                'qualification' => 'Ph.D.',
                'subject_specialization' => 'Mathematics',
                'date_of_joining' => '2015-06-15',
                'salary' => 65000.00,
                'address' => '301 Sector 29, Gurgaon',
                'aadhar_number' => '200000000011',
                'status' => 'active',
                'gender' => 'male',
                'wing' => 'senior',
                'teacher_type' => 'PGT',
                'designation' => 'Mathematics HOD (PGT)',
                'employee_id' => 'EMP011'
            ],
            [
                'name' => 'Dr. Priya Agrawal',
                'email' => 'priya.pgt@school.com',
                'phone' => '9876543312',
                'qualification' => 'Ph.D.',
                'subject_specialization' => 'Physics',
                'date_of_joining' => '2016-03-20',
                'salary' => 68000.00,
                'address' => '302 Sector 40, Noida',
                'aadhar_number' => '200000000012',
                'status' => 'active',
                'gender' => 'female',
                'wing' => 'senior',
                'teacher_type' => 'PGT',
                'designation' => 'Physics HOD (PGT)',
                'employee_id' => 'EMP012'
            ],
            [
                'name' => 'Dr. Arjun Desai',
                'email' => 'arjun.pgt@school.com',
                'phone' => '9876543313',
                'qualification' => 'Ph.D.',
                'subject_specialization' => 'Chemistry',
                'date_of_joining' => '2014-08-10',
                'salary' => 67000.00,
                'address' => '303 Banjara Hills, Hyderabad',
                'aadhar_number' => '200000000013',
                'status' => 'active',
                'gender' => 'male',
                'wing' => 'senior',
                'teacher_type' => 'PGT',
                'designation' => 'Chemistry HOD (PGT)',
                'employee_id' => 'EMP013'
            ],
            [
                'name' => 'Dr. Swati Joshi',
                'email' => 'swati.pgt@school.com',
                'phone' => '9876543314',
                'qualification' => 'Ph.D.',
                'subject_specialization' => 'Biology',
                'date_of_joining' => '2017-01-15',
                'salary' => 66000.00,
                'address' => '304 Kothrud, Pune',
                'aadhar_number' => '200000000014',
                'status' => 'active',
                'gender' => 'female',
                'wing' => 'senior',
                'teacher_type' => 'PGT',
                'designation' => 'Biology HOD (PGT)',
                'employee_id' => 'EMP014'
            ],
            [
                'name' => 'Dr. Rajiv Malhotra',
                'email' => 'rajiv.pgt@school.com',
                'phone' => '9876543315',
                'qualification' => 'Ph.D.',
                'subject_specialization' => 'English Literature',
                'date_of_joining' => '2015-11-25',
                'salary' => 64000.00,
                'address' => '305 Connaught Place, Delhi',
                'aadhar_number' => '200000000015',
                'status' => 'active',
                'gender' => 'male',
                'wing' => 'senior',
                'teacher_type' => 'PGT',
                'designation' => 'English HOD (PGT)',
                'employee_id' => 'EMP015'
            ],
            // Support Staff
            [
                'name' => 'Suresh Kumar',
                'email' => 'suresh.support@school.com',
                'phone' => '9876543316',
                'qualification' => 'BA',
                'subject_specialization' => 'Administrative Support',
                'date_of_joining' => '2019-05-10',
                'salary' => 25000.00,
                'address' => '401 Old Delhi, Delhi',
                'aadhar_number' => '200000000016',
                'status' => 'active',
                'gender' => 'male',
                'wing' => 'administration',
                'teacher_type' => 'Support Staff',
                'designation' => 'Administrative Officer',
                'employee_id' => 'EMP016'
            ],
            [
                'name' => 'Meena Devi',
                'email' => 'meena.support@school.com',
                'phone' => '9876543317',
                'qualification' => 'MA Economics',
                'subject_specialization' => 'Accounting',
                'date_of_joining' => '2018-09-08',
                'salary' => 30000.00,
                'address' => '402 New Friends Colony, Delhi',
                'aadhar_number' => '200000000017',
                'status' => 'active',
                'gender' => 'female',
                'wing' => 'administration',
                'teacher_type' => 'Support Staff',
                'designation' => 'Accounts Officer',
                'employee_id' => 'EMP017'
            ],
            [
                'name' => 'Rajesh Singh',
                'email' => 'rajesh.lib@school.com',
                'phone' => '9876543318',
                'qualification' => 'MLIS',
                'subject_specialization' => 'Library Science',
                'date_of_joining' => '2020-04-12',
                'salary' => 28000.00,
                'address' => '403 Greater Kailash II, Delhi',
                'aadhar_number' => '200000000018',
                'status' => 'active',
                'gender' => 'male',
                'wing' => 'library',
                'teacher_type' => 'Librarian',
                'designation' => 'Chief Librarian',
                'employee_id' => 'EMP018'
            ],
            [
                'name' => 'Anita Sharma',
                'email' => 'anita.sports@school.com',
                'phone' => '9876543319',
                'qualification' => 'BPES',
                'subject_specialization' => 'Physical Education',
                'date_of_joining' => '2019-07-18',
                'salary' => 32000.00,
                'address' => '404 Saket, Delhi',
                'aadhar_number' => '200000000019',
                'status' => 'active',
                'gender' => 'female',
                'wing' => 'sports',
                'teacher_type' => 'Sports Instructor',
                'designation' => 'Sports Teacher',
                'employee_id' => 'EMP019'
            ],
            [
                'name' => 'Vijay Chauhan',
                'email' => 'vijay.music@school.com',
                'phone' => '9876543320',
                'qualification' => 'BMus',
                'subject_specialization' => 'Music',
                'date_of_joining' => '2021-10-05',
                'salary' => 31000.00,
                'address' => '405 Vasant Kunj, Delhi',
                'aadhar_number' => '200000000020',
                'status' => 'active',
                'gender' => 'male',
                'wing' => 'arts',
                'teacher_type' => 'Art/Music Teacher',
                'designation' => 'Music Teacher',
                'employee_id' => 'EMP020'
            ]
        ];
        
        // Insert data
        foreach ($students as $student) {
            Student::create($student);
        }
        
        foreach ($teachers as $teacher) {
            Teacher::create($teacher);
        }
        
        // Create sample attendance records
        $attendanceRecords = [];
        $today = now()->toDateString();
        
        // Create attendance for today for all primary wing students
        $primaryStudents = array_filter($students, function($student) {
            return strpos($student['class'], 'Class 1') !== false || 
                   strpos($student['class'], 'Class 2') !== false ||
                   strpos($student['class'], 'Class 3') !== false;
        });
        
        foreach (array_slice($primaryStudents, 0, 6) as $index => $student) {
            $attendanceRecords[] = [
                'student_id' => $index + 1,
                'date' => $today,
                'status' => $index % 3 == 0 ? 'absent' : 'present',
                'remarks' => $index % 3 == 0 ? 'Sick leave' : 'Regular attendance',
                'period' => 'Full Day',
                'subject' => 'General',
                'class' => $student['class'],
                'session' => date('Y') . '-' . (date('Y') + 1),
                'marked_by' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
        
        foreach ($attendanceRecords as $record) {
            \App\Models\Attendance::create($record);
        }
        
        // Create sample bell timing schedules
        $bellTimings = [
            // Monday schedule
            [
                'day_of_week' => 'Monday',
                'period_name' => 'Assembly',
                'start_time' => '08:30:00',
                'end_time' => '08:45:00',
                'class_section' => null,
                'is_active' => true,
                'is_break' => false,
                'order_index' => 0,
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'semester' => 'First',
                'color_code' => '#17a2b8',
                'created_by' => 1
            ],
            [
                'day_of_week' => 'Monday',
                'period_name' => 'Period 1',
                'start_time' => '08:45:00',
                'end_time' => '09:30:00',
                'class_section' => null,
                'is_active' => true,
                'is_break' => false,
                'order_index' => 1,
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'semester' => 'First',
                'color_code' => '#007bff',
                'created_by' => 1
            ],
            [
                'day_of_week' => 'Monday',
                'period_name' => 'Break',
                'start_time' => '09:30:00',
                'end_time' => '09:45:00',
                'class_section' => null,
                'is_active' => true,
                'is_break' => true,
                'order_index' => 2,
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'semester' => 'First',
                'color_code' => '#ffc107',
                'created_by' => 1
            ],
            [
                'day_of_week' => 'Monday',
                'period_name' => 'Period 2',
                'start_time' => '09:45:00',
                'end_time' => '10:30:00',
                'class_section' => null,
                'is_active' => true,
                'is_break' => false,
                'order_index' => 3,
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'semester' => 'First',
                'color_code' => '#28a745',
                'created_by' => 1
            ],
            [
                'day_of_week' => 'Monday',
                'period_name' => 'Lunch Break',
                'start_time' => '10:30:00',
                'end_time' => '11:00:00',
                'class_section' => null,
                'is_active' => true,
                'is_break' => true,
                'order_index' => 4,
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'semester' => 'First',
                'color_code' => '#fd7e14',
                'created_by' => 1
            ],
            [
                'day_of_week' => 'Monday',
                'period_name' => 'Period 3',
                'start_time' => '11:00:00',
                'end_time' => '11:45:00',
                'class_section' => null,
                'is_active' => true,
                'is_break' => false,
                'order_index' => 5,
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'semester' => 'First',
                'color_code' => '#6f42c1',
                'created_by' => 1
            ],
            [
                'day_of_week' => 'Monday',
                'period_name' => 'Period 4',
                'start_time' => '11:45:00',
                'end_time' => '12:30:00',
                'class_section' => null,
                'is_active' => true,
                'is_break' => false,
                'order_index' => 6,
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'semester' => 'First',
                'color_code' => '#e83e8c',
                'created_by' => 1
            ],
            // Tuesday schedule
            [
                'day_of_week' => 'Tuesday',
                'period_name' => 'Assembly',
                'start_time' => '08:30:00',
                'end_time' => '08:45:00',
                'class_section' => null,
                'is_active' => true,
                'is_break' => false,
                'order_index' => 0,
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'semester' => 'First',
                'color_code' => '#17a2b8',
                'created_by' => 1
            ],
            [
                'day_of_week' => 'Tuesday',
                'period_name' => 'Period 1',
                'start_time' => '08:45:00',
                'end_time' => '09:30:00',
                'class_section' => 'Class 5',
                'is_active' => true,
                'is_break' => false,
                'order_index' => 1,
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'semester' => 'First',
                'color_code' => '#007bff',
                'created_by' => 1
            ],
            [
                'day_of_week' => 'Tuesday',
                'period_name' => 'Period 2',
                'start_time' => '09:30:00',
                'end_time' => '10:15:00',
                'class_section' => 'Class 5',
                'is_active' => true,
                'is_break' => false,
                'order_index' => 2,
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'semester' => 'First',
                'color_code' => '#28a745',
                'created_by' => 1
            ],
            [
                'day_of_week' => 'Tuesday',
                'period_name' => 'Break',
                'start_time' => '10:15:00',
                'end_time' => '10:30:00',
                'class_section' => 'Class 5',
                'is_active' => true,
                'is_break' => true,
                'order_index' => 3,
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'semester' => 'First',
                'color_code' => '#ffc107',
                'created_by' => 1
            ],
        ];
        
        foreach ($bellTimings as $timing) {
            \App\Models\BellTiming::create($timing);
        }
        
        // Create sample exam papers
        $examPapers = [
            [
                'title' => 'Mathematics Mid-Term Exam',
                'subject' => 'Mathematics',
                'class_section' => 'Class 5',
                'exam_type' => \App\Models\ExamPaper::EXAM_TYPE_MIDTERM,
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'semester' => 'First',
                'paper_type' => \App\Models\ExamPaper::PAPER_TYPE_QUESTION,
                'file_path' => 'exam-papers/sample_math_midterm.pdf',
                'file_name' => 'Math_MidTerm_Class5.pdf',
                'file_size' => 102400,
                'file_extension' => 'pdf',
                'uploaded_by' => 1,
                'is_published' => true,
                'is_answer_key' => false,
                'duration_minutes' => 180,
                'total_marks' => 80,
                'instructions' => 'Attempt all questions. Use blue/black pen only.',
                'exam_date' => now()->addDays(7)->toDateString(),
                'access_level' => \App\Models\ExamPaper::ACCESS_STUDENTS_ONLY,
                'created_by' => 1,
                'is_approved' => true,
                'approved_by' => 1
            ],
            [
                'title' => 'English Final Exam',
                'subject' => 'English',
                'class_section' => 'Class 6',
                'exam_type' => \App\Models\ExamPaper::EXAM_TYPE_FINAL,
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'semester' => 'First',
                'paper_type' => \App\Models\ExamPaper::PAPER_TYPE_QUESTION,
                'file_path' => 'exam-papers/sample_english_final.pdf',
                'file_name' => 'English_Final_Class6.pdf',
                'file_size' => 153600,
                'file_extension' => 'pdf',
                'uploaded_by' => 1,
                'is_published' => true,
                'is_answer_key' => false,
                'duration_minutes' => 180,
                'total_marks' => 100,
                'instructions' => 'Read all questions carefully. Answer in complete sentences.',
                'exam_date' => now()->addDays(14)->toDateString(),
                'access_level' => \App\Models\ExamPaper::ACCESS_STUDENTS_ONLY,
                'created_by' => 1,
                'is_approved' => true,
                'approved_by' => 1
            ],
            [
                'title' => 'Science Unit Test',
                'subject' => 'Science',
                'class_section' => 'Class 7',
                'exam_type' => \App\Models\ExamPaper::EXAM_TYPE_UNIT_TEST,
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'semester' => 'First',
                'paper_type' => \App\Models\ExamPaper::PAPER_TYPE_QUESTION,
                'file_path' => 'exam-papers/sample_science_unit.pdf',
                'file_name' => 'Science_UnitTest_Class7.pdf',
                'file_size' => 81920,
                'file_extension' => 'pdf',
                'uploaded_by' => 1,
                'is_published' => true,
                'is_answer_key' => false,
                'duration_minutes' => 120,
                'total_marks' => 50,
                'instructions' => 'Draw diagrams where required. Use pencil for diagrams.',
                'exam_date' => now()->addDays(3)->toDateString(),
                'access_level' => \App\Models\ExamPaper::ACCESS_STUDENTS_ONLY,
                'created_by' => 1,
                'is_approved' => true,
                'approved_by' => 1
            ],
            [
                'title' => 'Mathematics Answer Key',
                'subject' => 'Mathematics',
                'class_section' => 'Class 5',
                'exam_type' => \App\Models\ExamPaper::EXAM_TYPE_MIDTERM,
                'academic_year' => date('Y') . '-' . (date('Y') + 1),
                'semester' => 'First',
                'paper_type' => \App\Models\ExamPaper::PAPER_TYPE_ANSWER_KEY,
                'file_path' => 'exam-papers/sample_math_answer_key.pdf',
                'file_name' => 'Math_AnswerKey_Class5.pdf',
                'file_size' => 71680,
                'file_extension' => 'pdf',
                'uploaded_by' => 1,
                'is_published' => true,
                'is_answer_key' => true,
                'duration_minutes' => null,
                'total_marks' => null,
                'instructions' => 'Official answer key for Mathematics Mid-Term Exam.',
                'exam_date' => now()->subDays(1)->toDateString(),
                'access_level' => \App\Models\ExamPaper::ACCESS_TEACHERS_ONLY,
                'created_by' => 1,
                'is_approved' => true,
                'approved_by' => 1
            ]
        ];
        
        foreach ($examPapers as $paper) {
            \App\Models\ExamPaper::create($paper);
        }
        
        echo "✅ Sample data created successfully!\n";
        echo "   • " . count($students) . " Students created\n";
        echo "   • " . count($teachers) . " Teachers created\n";
        echo "   • " . count($attendanceRecords) . " Attendance records created\n";
        echo "   • " . count($bellTimings) . " Bell timing schedules created\n";
        echo "   • " . count($examPapers) . " Exam papers created\n";
    }
}