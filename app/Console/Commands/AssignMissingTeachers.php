<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignMissingTeachers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:assign-missing-teachers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically assign classes and subjects to teachers who have no assignments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for teachers with no assignments...');
        
        // Get all teachers who don't have any assignments
        $teachers = DB::table('teachers')
            ->leftJoin('teacher_class_subject_assignments', 'teachers.id', '=', 'teacher_class_subject_assignments.teacher_id')
            ->whereNull('teacher_class_subject_assignments.teacher_id')
            ->select('teachers.*')
            ->get();
            
        if ($teachers->isEmpty()) {
            $this->info('All teachers have assignments. No action needed.');
            return;
        }
        
        $this->info("Found {$teachers->count()} teachers with no assignments:");
        
        foreach ($teachers as $teacher) {
            $this->warn("- {$teacher->name} (Email: {$teacher->email})");
        }
        
        if ($this->confirm('Do you want to assign these teachers to some default classes and subjects?')) {
            $classes = DB::table('school_classes')->get();
            $subjects = DB::table('subjects')->get();
            
            if ($classes->isEmpty() || $subjects->isEmpty()) {
                $this->error('No classes or subjects found to assign. Please create classes and subjects first.');
                return;
            }
            
            foreach ($teachers as $teacher) {
                // Assign first class and first subject to each teacher
                $assignment = [
                    'teacher_id' => $teacher->id,
                    'class_id' => $classes->first()->id,
                    'subject_id' => $subjects->first()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                DB::table('teacher_class_subject_assignments')->insert($assignment);
                
                $this->info("Assigned {$classes->first()->name} and {$subjects->first()->name} to {$teacher->name}");
            }
            
            $this->info('Assignment completed successfully!');
        } else {
            $this->info('Operation cancelled.');
        }
    }
}