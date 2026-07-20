<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subject;
use App\Models\AcademicSession;
use App\Models\Syllabus;
use App\Models\DailyTeachingWork;
use App\Models\LessonPlan;
use App\Models\ClassManagement;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;

class AcademicDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create academic sessions
        $this->seedAcademicSessions();
        
        // Create subjects
        $this->seedSubjects();
        
        // Create syllabus
        $this->seedSyllabus();
        
        // Create lesson plans
        $this->seedLessonPlans();
        
        // Create daily teaching work
        $this->seedDailyTeachingWork();
        
        echo "Academic data seeded successfully!\n";
    }
    
    private function seedAcademicSessions(): void
    {
        $sessions = [
            [
                'name' => '2025-2026',
                'code' => '2025-2026',
                'start_date' => '2025-04-01',
                'end_date' => '2026-03-31',
                'description' => 'Academic Session 2025-2026',
                'is_current' => true,
                'is_active' => true
            ],
            [
                'name' => '2024-2025',
                'code' => '2024-2025',
                'start_date' => '2024-04-01',
                'end_date' => '2025-03-31',
                'description' => 'Previous Academic Session 2024-2025',
                'is_current' => false,
                'is_active' => true
            ],
            [
                'name' => '2026-2027',
                'code' => '2026-2027',
                'start_date' => '2026-04-01',
                'end_date' => '2027-03-31',
                'description' => 'Upcoming Academic Session 2026-2027',
                'is_current' => false,
                'is_active' => true
            ]
        ];
        
        foreach ($sessions as $session) {
            AcademicSession::firstOrCreate(
                ['name' => $session['name']],
                $session
            );
        }
    }
    
    private function seedSubjects(): void
    {
        $subjects = [
            ['name' => 'Mathematics', 'code' => 'MATH', 'description' => 'Study of numbers, quantities, and shapes', 'is_active' => true],
            ['name' => 'English', 'code' => 'ENG', 'description' => 'Language and literature studies', 'is_active' => true],
            ['name' => 'Hindi', 'code' => 'HIN', 'description' => 'Indian national language', 'is_active' => true],
            ['name' => 'Science', 'code' => 'SCI', 'description' => 'Physical and natural sciences', 'is_active' => true],
            ['name' => 'Social Studies', 'code' => 'SST', 'description' => 'History, geography, and civics', 'is_active' => true],
            ['name' => 'Physics', 'code' => 'PHY', 'description' => 'Branch of science dealing with matter and energy', 'is_active' => true],
            ['name' => 'Chemistry', 'code' => 'CHEM', 'description' => 'Study of substances and their interactions', 'is_active' => true],
            ['name' => 'Biology', 'code' => 'BIO', 'description' => 'Study of living organisms', 'is_active' => true],
            ['name' => 'Computer Science', 'code' => 'CS', 'description' => 'Study of computers and computational systems', 'is_active' => true],
            ['name' => 'Economics', 'code' => 'ECO', 'description' => 'Study of production and consumption', 'is_active' => true],
            ['name' => 'Accountancy', 'code' => 'ACC', 'description' => 'Study of financial reporting and analysis', 'is_active' => true],
            ['name' => 'Business Studies', 'code' => 'BS', 'description' => 'Study of business operations and management', 'is_active' => true],
            ['name' => 'History', 'code' => 'HIS', 'description' => 'Study of past events', 'is_active' => true],
            ['name' => 'Geography', 'code' => 'GEO', 'description' => 'Study of Earth\'s landscapes and environments', 'is_active' => true],
            ['name' => 'Political Science', 'code' => 'POL', 'description' => 'Study of politics and government', 'is_active' => true],
            ['name' => 'Psychology', 'code' => 'PSY', 'description' => 'Study of mind and behavior', 'is_active' => true],
        ];
        
        foreach ($subjects as $subject) {
            Subject::firstOrCreate(
                ['name' => $subject['name']],
                $subject
            );
        }
    }
    
    private function seedSyllabus(): void
    {
        $academicSession = AcademicSession::where('is_current', true)->first();
        $classes = ClassManagement::all();
        $subjects = Subject::all();
        
        foreach ($classes as $class) {
            foreach ($subjects as $subject) {
                // Only create syllabus for relevant subjects for each class level
                $relevantSubjects = $this->getRelevantSubjectsForClass($class->name);
                
                if (in_array($subject->name, $relevantSubjects)) {
                    Syllabus::firstOrCreate([
                        'subject' => $subject->name,
                        'class_name' => $class->name,
                        'academic_session' => $academicSession->name,
                    ], [
                        'subject' => $subject->name,
                        'class_name' => $class->name,
                        'section' => '',
                        'title' => "{$subject->name} Syllabus for {$class->name}",
                        'description' => "Complete {$subject->name} syllabus for {$class->name}",
                        'chapters' => $this->getDefaultChapters($subject->name, $class->name),
                        'start_date' => $academicSession->start_date,
                        'end_date' => $academicSession->end_date,
                        'total_duration_hours' => 120.00,
                        'learning_objectives' => $this->getDefaultLearningObjectives($subject->name, $class->name),
                        'assessment_criteria' => ['assignments' => 20, 'midterm' => 30, 'final' => 50],
                        'status' => 'active',
                        'created_by' => 1,
                        'updated_by' => 1,
                    ]);
                }
            }
        }
    }
    
    private function seedLessonPlans(): void
    {
        $teachers = Teacher::all();
        $classes = ClassManagement::all();
        $sections = Section::all();
        $subjects = Subject::all();
        
        if ($teachers->isEmpty() || $classes->isEmpty() || $sections->isEmpty() || $subjects->isEmpty()) {
            // If no teachers, classes, sections or subjects exist, create basic ones first
            return;
        }
        
        // Create lesson plans for recent dates
        $dates = [
            Carbon::now()->subDays(5),
            Carbon::now()->subDays(4),
            Carbon::now()->subDays(3),
            Carbon::now()->subDays(2),
            Carbon::now()->subDays(1),
            Carbon::now(),
        ];
        
        foreach ($dates as $date) {
            foreach ($teachers->take(3) as $teacher) { // Take first 3 teachers to avoid too many records
                $class = $classes->random();
                $section = $sections->random();
                $subject = $subjects->random();
                
                LessonPlan::firstOrCreate([
                    'teacher_id' => $teacher->user_id ?? 1,
                    'class_id' => $class->id,
                    'section_id' => $section->id,
                    'subject_id' => $subject->id,
                    'date' => $date,
                ], [
                    'teacher_id' => $teacher->user_id ?? 1,
                    'class_id' => $class->id,
                    'section_id' => $section->id,
                    'subject_id' => $subject->id,
                    'date' => $date,
                    'topic' => "Topic for {$subject->name} on {$date->format('Y-m-d')}",
                    'learning_objectives' => "Students will learn key concepts of {$subject->name}",
                    'teaching_method' => 'Lecture and Interactive Discussion',
                    'homework_classwork' => "Practice exercises from chapter",
                    'books_notebooks_required' => "Textbook and notebook",
                    'submission_assessment_notes' => "Assess student understanding",
                    'plan_type' => 'daily',
                    'created_by' => 1,
                    'modified_by' => 1,
                ]);
            }
        }
    }
    
    private function seedDailyTeachingWork(): void
    {
        $teachers = Teacher::all();
        $classes = ClassManagement::all();
        $sections = Section::all();
        $subjects = Subject::all();
        $academicSession = AcademicSession::where('is_current', true)->first();
        
        if (!$academicSession || $teachers->isEmpty() || $classes->isEmpty() || $sections->isEmpty() || $subjects->isEmpty()) {
            return;
        }
        
        // Create daily teaching work for recent dates
        $dates = [
            Carbon::now()->subDays(5),
            Carbon::now()->subDays(4),
            Carbon::now()->subDays(3),
            Carbon::now()->subDays(2),
            Carbon::now()->subDays(1),
            Carbon::now(),
        ];
        
        foreach ($dates as $date) {
            foreach ($teachers->take(3) as $teacher) { // Take first 3 teachers to avoid too many records
                $class = $classes->random();
                $section = $sections->random();
                $subject = $subjects->random();
                
                DailyTeachingWork::firstOrCreate([
                    'date' => $date,
                    'class_name' => $class->name,
                    'section' => $section->name,
                    'subject' => $subject->name,
                    'teacher_id' => $teacher->id,
                ], [
                    'date' => $date,
                    'class_name' => $class->name,
                    'section' => $section->name,
                    'subject' => $subject->name,
                    'period_number' => rand(1, 8),
                    'teacher_id' => $teacher->id,
                    'topic_covered' => "Covered {$subject->name} topic in {$class->name}",
                    'teaching_summary' => "Discussed key concepts and examples",
                    'attachments' => [],
                    'homework' => ['assignment' => "Complete exercises 1-5"],
                    'status' => 'published',
                    'academic_session' => $academicSession->name,
                    'syllabus_mapping' => ['syllabus_id' => 1, 'chapter' => 'Introduction'],
                    'created_by' => 1,
                    'updated_by' => 1,
                ]);
            }
        }
    }
    
    private function getRelevantSubjectsForClass($className): array
    {
        if (strpos(strtolower($className), 'nursery') !== false || 
            strpos(strtolower($className), 'lkg') !== false || 
            strpos(strtolower($className), 'ukg') !== false) {
            return ['English', 'Hindi', 'Mathematics', 'Science'];
        } elseif (strpos(strtolower($className), 'class 1') !== false || 
                 strpos(strtolower($className), 'class 2') !== false || 
                 strpos(strtolower($className), 'class 3') !== false || 
                 strpos(strtolower($className), 'class 4') !== false || 
                 strpos(strtolower($className), 'class 5') !== false) {
            return ['English', 'Hindi', 'Mathematics', 'Science', 'Social Studies'];
        } elseif (strpos(strtolower($className), 'class 6') !== false || 
                 strpos(strtolower($className), 'class 7') !== false || 
                 strpos(strtolower($className), 'class 8') !== false || 
                 strpos(strtolower($className), 'class 9') !== false || 
                 strpos(strtolower($className), 'class 10') !== false) {
            return ['English', 'Hindi', 'Mathematics', 'Science', 'Social Studies'];
        } else { // Classes 11 and 12
            return ['English', 'Physics', 'Chemistry', 'Biology', 'Mathematics', 'Computer Science', 'Economics', 'Accountancy', 'Business Studies', 'History', 'Geography', 'Political Science', 'Psychology'];
        }
    }
    
    private function getDefaultChapters($subjectName, $className): array
    {
        $chapters = [];
        
        if (strpos(strtolower($className), 'nursery') !== false || 
            strpos(strtolower($className), 'lkg') !== false || 
            strpos(strtolower($className), 'ukg') !== false) {
            $chapters = ['Alphabets', 'Numbers', 'Colors', 'Shapes', 'Animals', 'Family Members'];
        } elseif (strpos(strtolower($className), 'class 1') !== false || 
                 strpos(strtolower($className), 'class 2') !== false) {
            $chapters = ['Reading', 'Writing', 'Basic Addition', 'Basic Subtraction', 'Simple Sentences', 'Days of Week'];
        } elseif (strpos(strtolower($className), 'class 3') !== false || 
                 strpos(strtolower($className), 'class 4') !== false || 
                 strpos(strtolower($className), 'class 5') !== false) {
            $chapters = ['Grammar Basics', 'Multiplication Tables', 'Division', 'Simple Geometry', 'Reading Comprehension', 'Environmental Science'];
        } elseif (strpos(strtolower($className), 'class 6') !== false || 
                 strpos(strtolower($className), 'class 7') !== false || 
                 strpos(strtolower($className), 'class 8') !== false) {
            $chapters = ['Algebra', 'Geometry', 'Matter', 'Living Organisms', 'Indian History', 'World Geography'];
        } elseif (strpos(strtolower($className), 'class 9') !== false || 
                 strpos(strtolower($className), 'class 10') !== false) {
            $chapters = ['Polynomials', 'Triangles', 'Chemical Reactions', 'Life Processes', 'Nationalism in India', 'Resources and Development'];
        } else { // Classes 11 and 12
            if (in_array($subjectName, ['Physics', 'Chemistry', 'Biology', 'Mathematics'])) {
                $chapters = ['Physical World', 'Units and Measurements', 'Motion in a Straight Line', 'Laws of Motion', 'Work, Energy and Power', 'System of Particles'];
            } elseif ($subjectName === 'Computer Science') {
                $chapters = ['Python Fundamentals', 'Functions', 'Data Structures', 'File Handling', 'Database Concepts', 'Web Technologies'];
            } elseif ($subjectName === 'Economics') {
                $chapters = ['Introduction to Microeconomics', 'Consumer\'s Equilibrium', 'Producer Behavior', 'Forms of Market', 'National Income', 'Money and Banking'];
            } elseif ($subjectName === 'Accountancy') {
                $chapters = ['Introduction to Accounting', 'Theory Base of Accounting', 'Recording of Transactions', 'Bank Reconciliation Statement', 'Depreciation', 'Provisions and Reserves'];
            } elseif ($subjectName === 'Business Studies') {
                $chapters = ['Nature and Purpose of Business', 'Forms of Business', 'Private, Public and Global Enterprises', 'Business Services', 'Emerging Modes of Business', 'Social Responsibility'];
            }
        }
        
        return $chapters;
    }
    
    private function getDefaultLearningObjectives($subjectName, $className): array
    {
        return [
            "Understand fundamental concepts of {$subjectName}",
            "Apply theoretical knowledge to practical situations",
            "Develop critical thinking skills",
            "Enhance problem-solving abilities",
            "Build strong foundation for advanced topics"
        ];
    }
}