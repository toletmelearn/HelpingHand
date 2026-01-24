<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudentVerificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the student verification form
     */
    public function index(Request $request)
    {
        $students = Student::with(['documents'])->orderBy('name')->paginate(15);
        
        return view('admin.students.verify', compact('students'));
    }

    /**
     * Show verification form for a specific student
     */
    public function show(Student $student)
    {
        $student->load(['documents']);
        
        return view('admin.students.verify-single', compact('student'));
    }

    /**
     * Upload student documents for verification
     */
    public function uploadDocuments(Request $request, Student $student)
    {
        $request->validate([
            'documents' => 'required|array|min:1',
            'documents.*' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // max 5MB
        ]);

        foreach ($request->file('documents') as $file) {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $size = $file->getSize();
            $mimeType = $file->getMimeType();
            
            $filename = Str::random(40) . '.' . $extension;
            $path = $file->storeAs('student-documents/' . $student->id, $filename, 'public');
            
            $documentType = $this->determineDocumentType($originalName, $request->input('document_types', []));
            
            StudentDocument::create([
                'student_id' => $student->id,
                'document_type' => $documentType,
                'document_path' => $path,
                'original_filename' => $originalName,
                'file_size' => $size,
                'file_mime_type' => $mimeType,
            ]);
        }
        
        return redirect()->back()->with('success', 'Documents uploaded successfully!');
    }

    /**
     * Verify a specific document
     */
    public function verifyDocument(Request $request, StudentDocument $document)
    {
        $request->validate([
            'is_verified' => 'required|boolean',
            'verification_notes' => 'nullable|string|max:1000',
        ]);
        
        $document->update([
            'is_verified' => $request->is_verified,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'verification_notes' => $request->verification_notes,
        ]);
        
        return redirect()->back()->with('success', 'Document verification updated successfully!');
    }

    /**
     * Determine document type based on filename
     */
    private function determineDocumentType($filename, $documentTypes)
    {
        $lowerFilename = strtolower($filename);
        
        if (strpos($lowerFilename, 'birth') !== false || strpos($lowerFilename, 'dob') !== false) {
            return 'birth_certificate';
        } elseif (strpos($lowerFilename, 'aadhaar') !== false || strpos($lowerFilename, 'aadhar') !== false) {
            return 'aadhaar_card';
        } elseif (strpos($lowerFilename, 'address') !== false || strpos($lowerFilename, 'proof') !== false) {
            return 'address_proof';
        } elseif (strpos($lowerFilename, 'mark') !== false || strpos($lowerFilename, 'certificate') !== false) {
            return 'academic_certificate';
        }
        
        return 'other';
    }

    /**
     * Mark student as verified
     */
    public function markAsVerified(Request $request, Student $student)
    {
        // Check if all required documents are verified
        $requiredDocs = ['birth_certificate', 'aadhaar_card'];
        $verifiedDocs = $student->documents()->whereIn('document_type', $requiredDocs)
                                          ->where('is_verified', true)->count();
        
        if ($verifiedDocs >= count($requiredDocs)) {
            $student->update(['is_verified' => true]);
            return redirect()->back()->with('success', 'Student marked as verified successfully!');
        } else {
            return redirect()->back()->with('error', 'Not all required documents are verified!');
        }
    }
}
