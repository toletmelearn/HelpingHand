<?php

namespace App\Http\Controllers;

use App\Models\AdmitCard;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class StudentAdmitCardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display the student's admit cards.
     */
    public function index()
    {
        $student = Auth::user()->student;
        
        if (!$student) {
            return redirect()->back()->with('error', 'Student profile not found.');
        }
        
        $admitCards = AdmitCard::with(['exam', 'format'])
                           ->where('student_id', $student->id)
                           ->whereIn('status', ['published', 'locked'])
                           ->latest()
                           ->get();
        
        return view('student.admit-cards.index', compact('admitCards'));
    }
    
    /**
     * Show a specific admit card.
     */
    public function show(AdmitCard $admitCard)
    {
        $student = Auth::user()->student;
        
        if (!$student || $admitCard->student_id !== $student->id) {
            abort(403, 'Unauthorized to view this admit card.');
        }
        
        if (!in_array($admitCard->status, ['published', 'locked']) || $admitCard->status === 'revoked') {
            abort(403, 'This admit card is not available yet or has been revoked.');
        }
        
        return view('student.admit-cards.show', compact('admitCard'));
    }
    
    /**
     * Download an admit card as PDF.
     */
    public function downloadPdf(AdmitCard $admitCard)
    {
        $student = Auth::user()->student;
        
        if (!$student || $admitCard->student_id !== $student->id) {
            abort(403, 'Unauthorized to download this admit card.');
        }
        
        if (!in_array($admitCard->status, ['published', 'locked']) || $admitCard->status === 'revoked') {
            abort(403, 'This admit card is not available for download or has been revoked.');
        }
        
        $pdf = Pdf::loadView('student.admit-cards.pdf', compact('admitCard'));
        return $pdf->download("admit-card-{$admitCard->id}.pdf");
    }
}
