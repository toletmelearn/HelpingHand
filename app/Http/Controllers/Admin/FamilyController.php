<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Family;

class FamilyController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:accountant']);
    }

    public function index()
    {
        $families = Family::withCount('students')->with('students')->orderBy('guardian_name')->paginate(20);

        return view('admin.families.index', compact('families'));
    }

    public function show(Family $family)
    {
        $family->load('students.schoolClass');

        return view('admin.families.show', compact('family'));
    }
}
