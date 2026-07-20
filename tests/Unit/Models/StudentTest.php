<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\ClassManagement;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_fillable_attributes()
    {
        $student = new Student();
        
        $this->assertContains('name', $student->getFillable());
        $this->assertContains('roll_number', $student->getFillable());
    }

    /** @test */
    public function it_belongs_to_a_class()
    {
        $class = SchoolClass::create([
            'name' => 'Class 10',
            'class_order' => 10,
        ]);

        $student = Student::create([
            'name' => 'John Doe',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2010-01-01',
            'aadhar_number' => '123456789012',
            'address' => 'Address',
            'phone' => '1234567890',
            'class_id' => $class->id,
        ]);
        
        $this->assertInstanceOf(SchoolClass::class, $student->class);
        $this->assertEquals($class->id, $student->class->id);
    }

    /** @test */
    public function it_belongs_to_a_section()
    {
        $classManagement = ClassManagement::create([
            'name' => 'Class 10',
            'section' => 'A',
        ]);

        $section = Section::create([
            'name' => 'Section A',
            'class_id' => $classManagement->id,
        ]);

        $student = Student::create([
            'name' => 'John Doe',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2010-01-01',
            'aadhar_number' => '123456789012',
            'address' => 'Address',
            'phone' => '1234567890',
            'section_id' => $section->id,
        ]);
        
        $this->assertInstanceOf(Section::class, $student->section);
        $this->assertEquals($section->id, $student->section->id);
    }

    /** @test */
    public function it_can_get_full_name()
    {
        $student = new Student(['name' => 'John Doe']);
        
        $this->assertEquals('John Doe', $student->name);
    }
}
