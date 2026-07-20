<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HomeworkNotice;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;

class HomeworkNoticeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $homeworkNotices = HomeworkNotice::with(['schoolClass', 'subject', 'assignedBy'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.homework-notices.index', compact('homeworkNotices'));
    }

    public function create()
    {
        $classes = SchoolClass::active()->orderByOrder()->get();
        $subjects = Subject::orderBy('name')->get();
        $teachers = User::role('teacher')->get();

        return view('admin.homework-notices.create', compact('classes', 'subjects', 'teachers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:homework,notice,announcement',
            'class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'assigned_by' => 'required|exists:users,id',
            'due_date' => 'nullable|date',
            'publish_date' => 'nullable|date',
            'status' => 'required|in:active,inactive,published,archived',
            'priority' => 'required|in:low,medium,high'
        ]);

        HomeworkNotice::create($request->all());

        return redirect()->route('admin.homework-notices.index')
            ->with('success', 'Homework/Notice created successfully!');
    }

    public function show(HomeworkNotice $homeworkNotice)
    {
        $homeworkNotice->load(['schoolClass', 'subject', 'assignedBy']);

        return view('admin.homework-notices.show', compact('homeworkNotice'));
    }

    public function edit(HomeworkNotice $homeworkNotice)
    {
        $classes = SchoolClass::active()->orderByOrder()->get();
        $subjects = Subject::orderBy('name')->get();
        $teachers = User::role('teacher')->get();

        return view('admin.homework-notices.edit', compact('homeworkNotice', 'classes', 'subjects', 'teachers'));
    }

    public function update(Request $request, HomeworkNotice $homeworkNotice)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:homework,notice,announcement',
            'class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'assigned_by' => 'required|exists:users,id',
            'due_date' => 'nullable|date',
            'publish_date' => 'nullable|date',
            'status' => 'required|in:active,inactive,published,archived',
            'priority' => 'required|in:low,medium,high'
        ]);

        $homeworkNotice->update($request->all());

        return redirect()->route('admin.homework-notices.index')
            ->with('success', 'Homework/Notice updated successfully!');
    }

    public function destroy(HomeworkNotice $homeworkNotice)
    {
        $homeworkNotice->delete();

        return redirect()->route('admin.homework-notices.index')
            ->with('success', 'Homework/Notice deleted successfully!');
    }

    public function upcoming()
    {
        $upcomingHomework = HomeworkNotice::with(['schoolClass', 'subject', 'assignedBy'])
            ->homework()
            ->upcomingDue()
            ->orderBy('due_date')
            ->get();

        return view('admin.homework-notices.upcoming', compact('upcomingHomework'));
    }
}