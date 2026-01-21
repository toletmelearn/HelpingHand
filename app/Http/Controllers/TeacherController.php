<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    // Display all teachers
    public function index()
    {
        $teachers = Teacher::all();
        return view('teachers.index', compact('teachers'));
    }

    // Show create form
    public function create()
    {
        $subjects = ['Mathematics', 'Science', 'English', 'Hindi', 'Social Studies', 
                    'Physics', 'Chemistry', 'Biology', 'Computer Science', 'Physical Education'];
        $qualifications = ['B.Ed', 'M.Ed', 'B.Sc B.Ed', 'M.Sc B.Ed', 'Ph.D', 'Other'];
        
        return view('teachers.create', compact('subjects', 'qualifications'));
    }

    // Store new teacher
    public function store(Request $request)
    {
        $validated = $request->validate(
    Teacher::storeRules(),
    [
        'name.required' => 'Teacher name is required',
        'email.required' => 'Email is required',
        'email.email' => 'Enter a valid email address',
        'email.unique' => 'This email already exists',
        'phone.required' => 'Phone number is required',
        'phone.digits' => 'Phone number must be 10 digits',
        'designation.required' => 'Designation is required',
    ]
);

        
        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            $imagePath = $request->file('profile_image')->store('teacher_profiles', 'public');
            $validated['profile_image'] = $imagePath;
        }
        
        Teacher::create($validated);
        
        return redirect()->route('teachers.index')
                         ->with('success', 'Teacher added successfully!');
    }

    // Show single teacher (route-model binding)
    public function show(Teacher $teacher)
    {
        return view('teachers.show', compact('teacher'));
    }

    // Show edit form (route-model binding)
    public function edit(Teacher $teacher)
    {
        $subjects = ['Mathematics', 'Science', 'English', 'Hindi', 'Social Studies', 
                    'Physics', 'Chemistry', 'Biology', 'Computer Science', 'Physical Education'];
        $qualifications = ['B.Ed', 'M.Ed', 'B.Sc B.Ed', 'M.Sc B.Ed', 'Ph.D', 'Other'];
        
        return view('teachers.edit', compact('teacher', 'subjects', 'qualifications'));
    }

    // Update teacher (route-model binding)
    public function update(Request $request, Teacher $teacher)
    {
        $validated = $request->validate(Teacher::updateRules($teacher->id));

        // Handle profile image update
        if ($request->hasFile('profile_image')) {
            // Delete old image if exists
            if ($teacher->profile_image) {
                \Storage::disk('public')->delete($teacher->profile_image);
            }

            $imagePath = $request->file('profile_image')->store('teacher_profiles', 'public');
            $validated['profile_image'] = $imagePath;
        }

        $teacher->update($validated);

        return redirect()->route('teachers.index')
                         ->with('success', 'Teacher updated successfully!');
    }

    // Delete teacher (route-model binding)
    public function destroy(Teacher $teacher)
    {
        $teacher->delete();

        return redirect()->route('teachers.index')
                         ->with('success', 'Teacher deleted');
    }
}