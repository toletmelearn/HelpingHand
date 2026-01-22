<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class TeacherController extends Controller
{
    // Display all teachers
    public function index()
    {
        $this->authorize('viewAny', Teacher::class);
        $teachers = Teacher::all();
        return view('teachers.index', compact('teachers'));
    }

    // Show create form
    public function create()
    {
        $this->authorize('create', Teacher::class);
        $subjects = ['Mathematics', 'Science', 'English', 'Hindi', 'Social Studies', 
                    'Physics', 'Chemistry', 'Biology', 'Computer Science', 'Physical Education'];
        $qualifications = ['B.Ed', 'M.Ed', 'B.Sc B.Ed', 'M.Sc B.Ed', 'Ph.D', 'Other'];
        
        return view('teachers.create', compact('subjects', 'qualifications'));
    }

    // Store new teacher
    public function store(Request $request)
    {
        $this->authorize('create', Teacher::class);
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
        
        $teacher = Teacher::create($validated);
        
        return redirect()->route('teachers.index')
                         ->with('success', 'Teacher added successfully!');
    }

    // Show single teacher (route-model binding)
    public function show(Teacher $teacher)
    {
        $this->authorize('view', $teacher);
        return view('teachers.show', compact('teacher'));
    }

    // Show edit form (route-model binding)
    public function edit(Teacher $teacher)
    {
        $this->authorize('update', $teacher);
        $subjects = ['Mathematics', 'Science', 'English', 'Hindi', 'Social Studies', 
                    'Physics', 'Chemistry', 'Biology', 'Computer Science', 'Physical Education'];
        $qualifications = ['B.Ed', 'M.Ed', 'B.Sc B.Ed', 'M.Ed', 'Ph.D', 'Other'];
        
        return view('teachers.edit', compact('teacher', 'subjects', 'qualifications'));
    }

    // Update teacher (route-model binding)
    public function update(Request $request, Teacher $teacher)
    {
        $this->authorize('update', $teacher);
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
        $this->authorize('delete', $teacher);
        $teacher->delete();

        return redirect()->route('teachers.index')
                         ->with('success', 'Teacher deleted');
    }
}