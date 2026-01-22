<?php

namespace App\Http\Controllers\API;

use App\Models\ExamPaper;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ExamPaperController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ExamPaper::with(['uploadedBy', 'approvedBy']);

            // Apply filters
            if ($request->filled('subject')) {
                $query->where('subject', $request->subject);
            }

            if ($request->filled('class_section')) {
                $query->where('class_section', $request->class_section);
            }

            if ($request->filled('exam_type')) {
                $query->where('exam_type', $request->exam_type);
            }

            if ($request->filled('paper_type')) {
                $query->where('paper_type', $request->paper_type);
            }

            if ($request->filled('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }

            if ($request->filled('is_published')) {
                $query->where('is_published', $request->is_published);
            }

            $examPapers = $query->orderBy('exam_date', 'desc')
                               ->orderBy('created_at', 'desc')
                               ->get();

            return $this->success($examPapers, 'Exam papers retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve exam papers: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'subject' => 'required|string|max:100',
                'class_section' => 'required|string|max:50',
                'exam_type' => 'required|in:midterm,final,unit_test,quiz,assignment,project,practical',
                'paper_type' => 'required|in:question,answer_key,solution,syllabus,sample,previous_year',
                'file' => 'required|file|mimes:pdf,doc,docx,txt,jpeg,jpg,png|max:10240', // 10MB max
                'academic_year' => 'nullable|string|max:20',
                'semester' => 'nullable|string|max:20',
                'duration_minutes' => 'nullable|integer|min:1',
                'total_marks' => 'nullable|integer|min:1',
                'exam_date' => 'nullable|date',
                'exam_time' => 'nullable|date_format:H:i',
                'access_level' => 'required|in:public,teachers_only,students_only,private',
                'instructions' => 'nullable|string',
                'is_published' => 'boolean',
                'is_answer_key' => 'boolean',
                'password_protected' => 'boolean',
                'access_password' => 'nullable|required_if:password_protected,true|string|min:6'
            ]);

            // Handle file upload
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $fileName = time() . '_' . uniqid() . '.' . $extension;
                $filePath = $file->storeAs('exam-papers', $fileName, 'public');
                $fileSize = $file->getSize();
            } else {
                return $this->error('File is required', 400);
            }

            $examPaper = new ExamPaper();
            $examPaper->fill($validated);
            $examPaper->file_path = $filePath;
            $examPaper->file_name = $originalName;
            $examPaper->file_size = $fileSize;
            $examPaper->file_extension = $extension;
            $examPaper->uploaded_by = auth()->id(); // Current authenticated user
            $examPaper->created_by = auth()->id(); // Current authenticated user
            $examPaper->is_approved = true; // Auto-approve for demo purposes
            $examPaper->approved_by = auth()->id(); // Current authenticated user
            
            if ($request->password_protected) {
                $examPaper->access_password = bcrypt($request->access_password);
            }

            $examPaper->save();

            return $this->success($examPaper, 'Exam paper uploaded successfully', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to upload exam paper: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $examPaper = ExamPaper::with(['uploadedBy', 'approvedBy'])->findOrFail($id);
            
            // Check if user can access this file
            if (!$examPaper->canBeAccessedBy(auth()->user())) {
                return $this->error('Unauthorized to access this file', 403);
            }
            
            return $this->success($examPaper, 'Exam paper retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Exam paper not found: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $examPaper = ExamPaper::findOrFail($id);

            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'subject' => 'sometimes|required|string|max:100',
                'class_section' => 'sometimes|required|string|max:50',
                'exam_type' => 'sometimes|required|in:midterm,final,unit_test,quiz,assignment,project,practical',
                'paper_type' => 'sometimes|required|in:question,answer_key,solution,syllabus,sample,previous_year',
                'file' => 'nullable|file|mimes:pdf,doc,docx,txt,jpeg,jpg,png|max:10240', // 10MB max
                'academic_year' => 'nullable|string|max:20',
                'semester' => 'nullable|string|max:20',
                'duration_minutes' => 'nullable|integer|min:1',
                'total_marks' => 'nullable|integer|min:1',
                'exam_date' => 'nullable|date',
                'exam_time' => 'nullable|date_format:H:i',
                'access_level' => 'sometimes|required|in:public,teachers_only,students_only,private',
                'instructions' => 'nullable|string',
                'is_published' => 'boolean',
                'is_answer_key' => 'boolean',
                'password_protected' => 'boolean',
                'access_password' => 'nullable|required_if:password_protected,true|string|min:6'
            ]);

            // Handle file upload if provided
            if ($request->hasFile('file')) {
                // Delete old file
                if ($examPaper->file_path) {
                    Storage::disk('public')->delete($examPaper->file_path);
                }

                $file = $request->file('file');
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $fileName = time() . '_' . uniqid() . '.' . $extension;
                $filePath = $file->storeAs('exam-papers', $fileName, 'public');
                $fileSize = $file->getSize();

                $examPaper->file_path = $filePath;
                $examPaper->file_name = $originalName;
                $examPaper->file_size = $fileSize;
                $examPaper->file_extension = $extension;
            }

            $examPaper->fill($validated);
            
            if ($request->filled('password_protected')) {
                if ($request->password_protected) {
                    $examPaper->access_password = bcrypt($request->access_password);
                } else {
                    $examPaper->access_password = null;
                }
            }

            $examPaper->save();

            return $this->success($examPaper, 'Exam paper updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update exam paper: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $examPaper = ExamPaper::findOrFail($id);
            
            // Delete the physical file
            if ($examPaper->file_path) {
                Storage::disk('public')->delete($examPaper->file_path);
            }

            $examPaper->delete();

            return $this->success(null, 'Exam paper deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete exam paper: ' . $e->getMessage());
        }
    }

    /**
     * Download the specified exam paper file.
     */
    public function download(Request $request, int $id): JsonResponse
    {
        try {
            $examPaper = ExamPaper::findOrFail($id);
            
            // Check if user can access this file
            if (!$examPaper->canBeAccessedBy(auth()->user())) {
                return $this->error('Unauthorized to access this file', 403);
            }

            // Check if password is required
            if ($examPaper->password_protected) {
                $password = $request->input('password');
                
                // Verify password
                if (!$password || !password_verify($password, $examPaper->access_password)) {
                    return $this->error('Invalid password for this exam paper', 401);
                }
            }

            // Increment download count
            $examPaper->incrementDownloadCount();

            // Return file download URL
            $downloadUrl = Storage::disk('public')->url($examPaper->file_path);
            
            return $this->success([
                'download_url' => $downloadUrl,
                'file_name' => $examPaper->file_name,
                'file_size' => $examPaper->file_size
            ], 'Download URL generated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to download exam paper: ' . $e->getMessage());
        }
    }

    /**
     * Toggle publish status.
     */
    public function togglePublish(int $id): JsonResponse
    {
        try {
            $examPaper = ExamPaper::findOrFail($id);
            $examPaper->is_published = !$examPaper->is_published;
            $examPaper->save();

            $status = $examPaper->is_published ? 'published' : 'unpublished';
            return $this->success($examPaper, "Exam paper {$status} successfully!");
        } catch (\Exception $e) {
            return $this->error('Failed to toggle publish status: ' . $e->getMessage());
        }
    }
}