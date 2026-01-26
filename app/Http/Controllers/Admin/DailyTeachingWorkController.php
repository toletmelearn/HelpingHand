<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyTeachingWork;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DailyTeachingWorkController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = DailyTeachingWork::with(['teacher', 'createdBy'])
                    ->orderBy('date', 'desc')
                    ->orderBy('class_name')
                    ->orderBy('subject');
        
        // Apply filters
        if ($request->filled('class_name')) {
            $query->where('class_name', $request->class_name);
        }
        
        if ($request->filled('subject')) {
            $query->where('subject', $request->subject);
        }
        
        if ($request->filled('section')) {
            $query->where('section', $request->section);
        }
        
        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }
        
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }
        
        $dailyWorks = $query->paginate(15);
        
        // Get filters for dropdowns
        $teachers = Teacher::all();
        $classes = DailyTeachingWork::select('class_name')->distinct()->pluck('class_name');
        $subjects = DailyTeachingWork::select('subject')->distinct()->pluck('subject');
        $sections = DailyTeachingWork::select('section')->distinct()->pluck('section');
        
        return view('admin.daily-teaching-work.index', compact('dailyWorks', 'teachers', 'classes', 'subjects', 'sections'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $teachers = Teacher::all();
        $classes = collect(["Nursery", "LKG", "UKG", "1st", "2nd", "3rd", "4th", "5th", "6th", "7th", "8th", "9th", "10th", "11th", "12th"]);
        $sections = collect(["A", "B", "C", "D", "E"]);
        $subjects = DailyTeachingWork::select('subject')->distinct()->pluck('subject');
        
        return view('admin.daily-teaching-work.create', compact('teachers', 'classes', 'sections', 'subjects'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'class_name' => 'required|string|max:50',
            'section' => 'required|string|max:10',
            'subject' => 'required|string|max:100',
            'period_number' => 'nullable|integer|min:1|max:10',
            'teacher_id' => 'required|exists:teachers,id',
            'topic_covered' => 'required|string|max:255',
            'teaching_summary' => 'nullable|string',
            'attachments.*' => 'nullable|file|max:10240', // 10MB max per file
            'homework_description' => 'nullable|string|max:500',
            'homework_due_date' => 'nullable|date',
            'syllabus_mapping' => 'nullable|array',
        ]);
        
        $data = $request->except(['attachments', 'homework_description', 'homework_due_date']);
        $data['created_by'] = Auth::id();
        $data['academic_session'] = $request->academic_session ?? config('app.academic_session', date('Y') . '-' . (date('Y') + 1));
        
        // Handle file uploads
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $filename = time() . '_' . Str::random(10) . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('daily-teaching-work', $filename, 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'extension' => $file->getClientOriginalExtension(),
                ];
            }
        }
        $data['attachments'] = $attachments;
        
        // Handle homework data
        if ($request->filled('homework_description')) {
            $data['homework'] = [
                'description' => $request->homework_description,
                'due_date' => $request->homework_due_date,
                'created_at' => now(),
            ];
        }
        
        $dailyWork = DailyTeachingWork::create($data);
        
        return redirect()->route('admin.daily-teaching-work.index')->with('success', 'Daily teaching work created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(DailyTeachingWork $dailyTeachingWork)
    {
        $dailyTeachingWork->load(['teacher', 'createdBy']);
        return view('admin.daily-teaching-work.show', compact('dailyTeachingWork'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DailyTeachingWork $dailyTeachingWork)
    {
        $teachers = Teacher::all();
        $classes = collect(["Nursery", "LKG", "UKG", "1st", "2nd", "3rd", "4th", "5th", "6th", "7th", "8th", "9th", "10th", "11th", "12th"]);
        $sections = collect(["A", "B", "C", "D", "E"]);
        $subjects = DailyTeachingWork::select('subject')->distinct()->pluck('subject');
        
        return view('admin.daily-teaching-work.edit', compact('dailyTeachingWork', 'teachers', 'classes', 'sections', 'subjects'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DailyTeachingWork $dailyTeachingWork)
    {
        $request->validate([
            'date' => 'required|date',
            'class_name' => 'required|string|max:50',
            'section' => 'required|string|max:10',
            'subject' => 'required|string|max:100',
            'period_number' => 'nullable|integer|min:1|max:10',
            'teacher_id' => 'required|exists:teachers,id',
            'topic_covered' => 'required|string|max:255',
            'teaching_summary' => 'nullable|string',
            'attachments.*' => 'nullable|file|max:10240', // 10MB max per file
            'homework_description' => 'nullable|string|max:500',
            'homework_due_date' => 'nullable|date',
            'syllabus_mapping' => 'nullable|array',
        ]);
        
        $data = $request->except(['attachments', 'homework_description', 'homework_due_date']);
        $data['updated_by'] = Auth::id();
        
        // Handle file uploads
        $existingAttachments = $dailyTeachingWork->attachments ?? [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $filename = time() . '_' . Str::random(10) . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('daily-teaching-work', $filename, 'public');
                $existingAttachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'extension' => $file->getClientOriginalExtension(),
                ];
            }
        }
        $data['attachments'] = $existingAttachments;
        
        // Handle homework data
        if ($request->filled('homework_description')) {
            $data['homework'] = [
                'description' => $request->homework_description,
                'due_date' => $request->homework_due_date,
                'updated_at' => now(),
            ];
        }
        
        $dailyTeachingWork->update($data);
        
        return redirect()->route('admin.daily-teaching-work.index')->with('success', 'Daily teaching work updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DailyTeachingWork $dailyTeachingWork)
    {
        // Delete associated files
        if ($dailyTeachingWork->attachments) {
            foreach ($dailyTeachingWork->attachments as $attachment) {
                if (Storage::disk('public')->exists($attachment['path'])) {
                    Storage::disk('public')->delete($attachment['path']);
                }
            }
        }
        
        $dailyTeachingWork->delete();
        
        return redirect()->route('admin.daily-teaching-work.index')->with('success', 'Daily teaching work deleted successfully.');
    }
    
    /**
     * Download an attachment
     */
    public function downloadAttachment(DailyTeachingWork $dailyTeachingWork, $index)
    {
        $attachment = $dailyTeachingWork->attachments[$index] ?? null;
        
        if (!$attachment) {
            abort(404, 'Attachment not found');
        }
        
        $filePath = storage_path('app/public/' . $attachment['path']);
        
        if (!file_exists($filePath)) {
            abort(404, 'File not found');
        }
        
        return response()->download($filePath, $attachment['name']);
    }
    
    /**
     * Get daily work for a specific date and class
     */
    public function getByDateClass(Request $request)
    {
        $date = $request->date;
        $className = $request->class_name;
        $section = $request->section;
        
        $works = DailyTeachingWork::where('date', $date)
                                 ->where('class_name', $className)
                                 ->where('section', $section)
                                 ->with(['teacher', 'createdBy'])
                                 ->get();
        
        return response()->json($works);
    }
}
