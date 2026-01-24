<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fee;
use App\Models\Student;
use App\Models\FeeStructure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Fee::class);
        $fees = Fee::with(['student', 'feeStructure'])->paginate(15);
        return view('admin.fees.index', compact('fees'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Fee::class);
        $students = Student::all();
        $feeStructures = FeeStructure::where('is_active', true)->get();
        return view('admin.fees.create', compact('students', 'feeStructures'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Fee::class);
        
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'fee_structure_id' => 'required|exists:fee_structures,id',
            'academic_year' => 'required|string|max:20',
            'term' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0|lte:amount',
            'due_date' => 'required|date',
            'status' => 'required|in:pending,paid,partial',
            'notes' => 'nullable|string'
        ]);

        $fee = Fee::create($request->all());
        
        // Update status based on payment
        $fee->updateStatus();

        return redirect()->route('admin.fees.index')
                         ->with('success', 'Fee record created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Fee $fee)
    {
        $this->authorize('view', $fee);
        return view('admin.fees.show', compact('fee'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Fee $fee)
    {
        $this->authorize('update', $fee);
        $students = Student::all();
        $feeStructures = FeeStructure::where('is_active', true)->get();
        return view('admin.fees.edit', compact('fee', 'students', 'feeStructures'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Fee $fee)
    {
        $this->authorize('update', $fee);
        
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'fee_structure_id' => 'required|exists:fee_structures,id',
            'academic_year' => 'required|string|max:20',
            'term' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0|lte:amount',
            'due_date' => 'required|date',
            'status' => 'required|in:pending,paid,partial',
            'notes' => 'nullable|string'
        ]);

        $fee->update($request->all());
        
        // Update status based on payment
        $fee->updateStatus();

        return redirect()->route('admin.fees.index')
                         ->with('success', 'Fee record updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Fee $fee)
    {
        $this->authorize('delete', $fee);
        
        $fee->delete();

        return redirect()->route('admin.fees.index')
                         ->with('success', 'Fee record deleted successfully.');
    }
}
