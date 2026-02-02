<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestDataModel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-data-model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the student promotion data model';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== DATA MODEL VERIFICATION ===');
        
        // Check classes
        $this->info('\n1. Classes in database:');
        $classes = \App\Models\SchoolClass::orderBy('class_order')->get(['id', 'name', 'class_order']);
        foreach ($classes as $class) {
            $this->line("  - ID: {$class->id}, Name: {$class->name}, Order: {$class->class_order}");
        }
        
        $this->info('\n2. Students with class_id:');
        $students = \App\Models\Student::with('schoolClass')
            ->limit(5)
            ->get();
        
        foreach ($students as $student) {
            $this->line("  - {$student->name} -> {$student->schoolClass->name} (Order: {$student->schoolClass->class_order})");
        }
        
        $this->info('\n3. Class hierarchy verification:');
        $class1 = \App\Models\SchoolClass::where('name', 'Class 1')->first();
        $class2 = \App\Models\SchoolClass::where('name', 'Class 2')->first();
        
        if ($class1 && $class2) {
            $this->line("  - Class 1 order: {$class1->class_order}");
            $this->line("  - Class 2 order: {$class2->class_order}");
            $this->line("  - Class 2 > Class 1: " . ($class2->class_order > $class1->class_order ? 'YES' : 'NO'));
        }
        
        $this->info('\n4. Students count by class:');
        $studentCounts = \App\Models\Student::join('school_classes', 'students.class_id', '=', 'school_classes.id')
            ->select('school_classes.name', \DB::raw('COUNT(*) as count'))
            ->groupBy('school_classes.name')
            ->orderBy('school_classes.class_order')
            ->get();
        
        foreach ($studentCounts as $count) {
            $this->line("  - {$count->name}: {$count->count} students");
        }
        
        $this->info('\n=== DATA MODEL READY FOR STUDENT PROMOTION ===');
    }
}
