<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Legacy root student controller
|--------------------------------------------------------------------------
|
| Phase 4O: Root student CRUD is dormant. Canonical student CRUD lives in
| AdminStudentController, and import/export lives in StudentImportExportController.
| Do not route these methods without first normalizing class/section writes.
|
*/
class StudentController extends Controller
{
    /**
     * Display a listing of all students.
     */
    public function index(Request $request)
    {
        return redirect()->route('admin.students.index');
    }

    /**
     * Show the form for creating a new student.
     */
    public function create()
    {
        return redirect()->route('admin.students.create');
    }

    /**
     * Store a newly created student in database.
     */
    public function store(Request $request)
    {
        abort(410, 'Legacy student store is disabled. Use admin student CRUD.');
    }

    /**
     * Display the specified student.
     */
    public function show($id)
    {
        return redirect()->route('admin.students.show', $id);
    }

    /**
     * Show the form for editing a student.
     */
    public function edit($id)
    {
        return redirect()->route('admin.students.edit', $id);
    }

    /**
     * Update the specified student.
     */
    public function update(Request $request, $id)
    {
        abort(410, 'Legacy student update is disabled. Use admin student CRUD.');
    }

    /**
     * Remove the specified student.
     */
    public function destroy($id)
    {
        abort(410, 'Legacy student delete is disabled. Use admin student CRUD.');
    }

    // Phase 4M: import/export routes moved to StudentImportExportController.
}
