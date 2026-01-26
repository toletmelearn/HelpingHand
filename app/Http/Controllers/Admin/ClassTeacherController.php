    public function studentRecords(Request $request)
    {
        $this->authorize('viewStudentRecords');

        $query = Student::with(['user', 'class', 'section']);

        // Filter by class if user is a class teacher
        if (auth()->user() && auth()->user()->roles->pluck('name')->contains('class-teacher')) {
            $classTeacher = Teacher::where('user_id', auth()->user()->id)->first();
            if ($classTeacher) {
                $classIds = $classTeacher->classes()->pluck('class_management.id')->toArray();
                $query->whereIn('class_id', $classIds);
            }
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->filled('section_id')) {
            $query->where('section_id', $request->section_id);
        }

        $students = $query->paginate(20);
        $classes = ClassManagement::all();
        $sections = \App\Models\Section::all();

        return view('admin.class-teacher-control.student-records', compact('students', 'classes', 'sections'));
    }

    public function editStudent($id)
    {
        $student = Student::findOrFail($id);
        $this->authorize('updateClassStudent', $student);

        // Get field permissions for the student model based on the user's role
        $userRole = auth()->user()->roles->first()->name;
        $fieldPermissions = FieldPermission::getPermissionsForRole(Student::class, $userRole);

        $classes = ClassManagement::all();
        $sections = \App\Models\Section::all();

        return view('admin.class-teacher-control.edit-student', compact('student', 'fieldPermissions', 'classes', 'sections'));
    }

    public function updateStudent(Request $request, $id)
    {
        $student = Student::findOrFail($id);
        $this->authorize('updateClassStudent', $student);

        // Get field permissions to validate which fields can be updated
        $userRole = auth()->user()->roles->first()->name;
        $fieldPermissions = FieldPermission::getPermissionsForRole(Student::class, $userRole);

        // Validate that only allowed fields are being updated
        $allowedFields = [];
        foreach ($fieldPermissions as $field => $permission) {
            if ($permission === 'editable') {
                $allowedFields[] = $field;
            }
        }

        // Prepare validation rules for allowed fields only
        $validationRules = [];
        $allowedInput = [];

        foreach ($request->all() as $key => $value) {
            if (in_array($key, $allowedFields)) {
                // Add basic validation rules for allowed fields
                switch ($key) {
                    case 'name':
                        $validationRules[$key] = 'string|max:255';
                        break;
                    case 'father_name':
                    case 'mother_name':
                        $validationRules[$key] = 'string|max:255';
                        break;
                    case 'address':
                        $validationRules[$key] = 'string|max:500';
                        break;
                    case 'phone':
                        $validationRules[$key] = 'string|max:15';
                        break;
                    case 'email':
                        $validationRules[$key] = 'email|nullable';
                        break;
                    default:
                        $validationRules[$key] = 'string|nullable';
                }
                
                $allowedInput[$key] = $value;
            }
        }

        $request->validate($validationRules);

        // Track changes for audit logging
        $changes = [];
        foreach ($allowedInput as $field => $newValue) {
            $oldValue = $student->$field;
            if ($oldValue != $newValue) {
                $changes[] = [
                    'field' => $field,
                    'old_value' => $oldValue,
                    'new_value' => $newValue
                ];
            }
        }

        // Update the student record
        $student->update($allowedInput);

        // Log changes to audit log
        foreach ($changes as $change) {
            AuditLog::create([
                'user_type' => $userRole,
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name,
                'model_type' => Student::class,
                'model_id' => $student->id,
                'field_name' => $change['field'],
                'old_value' => $change['old_value'],
                'new_value' => $change['new_value'],
                'action' => 'update',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect()->route('admin.class-teacher-control.student-records')
                         ->with('success', 'Student record updated successfully.');
    }