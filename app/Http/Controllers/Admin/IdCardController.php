<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\IdCard;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Str;

class IdCardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $idCards = IdCard::with(['student', 'printedBy'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.id-cards.index', compact('idCards'));
    }

    public function create()
    {
        $students = Student::orderBy('name')->get();
        $users = User::role('admin')->orWhere('role', 'accountant')->get();

        return view('admin.id-cards.create', compact('students', 'users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'issue_date' => 'required|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
            'status' => 'required|in:active,expired,suspended,lost',
            'printed_by' => 'nullable|exists:users,id'
        ]);

        // Generate unique card number
        $cardNumber = 'ID-' . strtoupper(Str::random(8));

        IdCard::create([
            'student_id' => $request->student_id,
            'card_number' => $cardNumber,
            'issue_date' => $request->issue_date,
            'expiry_date' => $request->expiry_date,
            'status' => $request->status,
            'printed_by' => $request->printed_by,
            'printed_at' => $request->printed_by ? now() : null
        ]);

        return redirect()->route('admin.id-cards.index')
            ->with('success', 'ID Card created successfully!');
    }

    public function show(IdCard $idCard)
    {
        $idCard->load(['student', 'printedBy']);

        return view('admin.id-cards.show', compact('idCard'));
    }

    public function edit(IdCard $idCard)
    {
        $students = Student::orderBy('name')->get();
        $users = User::role('admin')->orWhere('role', 'accountant')->get();

        return view('admin.id-cards.edit', compact('idCard', 'students', 'users'));
    }

    public function update(Request $request, IdCard $idCard)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'issue_date' => 'required|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
            'status' => 'required|in:active,expired,suspended,lost',
            'printed_by' => 'nullable|exists:users,id'
        ]);

        $idCard->update([
            'student_id' => $request->student_id,
            'issue_date' => $request->issue_date,
            'expiry_date' => $request->expiry_date,
            'status' => $request->status,
            'printed_by' => $request->printed_by,
            'printed_at' => $request->printed_by ? now() : null
        ]);

        return redirect()->route('admin.id-cards.index')
            ->with('success', 'ID Card updated successfully!');
    }

    public function destroy(IdCard $idCard)
    {
        $idCard->delete();

        return redirect()->route('admin.id-cards.index')
            ->with('success', 'ID Card deleted successfully!');
    }

    public function print(IdCard $idCard)
    {
        $idCard->load(['student', 'student.schoolClass']);

        return view('admin.id-cards.print', compact('idCard'));
    }

    public function generateForStudent(Request $request, $studentId)
    {
        $student = Student::findOrFail($studentId);

        // Check if ID card already exists
        $existingCard = IdCard::where('student_id', $studentId)->first();
        if ($existingCard) {
            return redirect()->route('admin.id-cards.index')
                ->with('error', 'ID Card already exists for this student!');
        }

        // Generate new ID card
        $cardNumber = 'ID-' . strtoupper(Str::random(8));

        IdCard::create([
            'student_id' => $studentId,
            'card_number' => $cardNumber,
            'issue_date' => today(),
            'expiry_date' => today()->addYears(4), // Valid for 4 years
            'status' => 'active'
        ]);

        return redirect()->route('admin.id-cards.index')
            ->with('success', 'ID Card generated successfully for ' . $student->name . '!');
    }
}