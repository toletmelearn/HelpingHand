<?php

namespace App\Http\Controllers\API;

use App\Models\LessonPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class LessonPlanController extends BaseApiController
{
    /**
     * Get all lesson plans
     */
    public function index(Request $request)
    {
        $query = LessonPlan::with(['teacher:id,name', 'subject:id,name', 'class:id,name', 'section:id,name']);

        // Filter by class
        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        // Filter by section
        if ($request->has('section_id')) {
            $query->where('section_id', $request->section_id);
        }

        // Filter by subject
        if ($request->has('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            // Default: only show published
            $query->where('status', 'published');
        }

        $lessonPlans = $query->orderBy('date', 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->sendResponse($lessonPlans, 'Lesson plans retrieved successfully');
    }

    /**
     * Get single lesson plan
     */
    public function show($id)
    {
        $lessonPlan = LessonPlan::with([
            'teacher:id,name,designation',
            'subject:id,name,code',
            'class:id,name',
            'section:id,name'
        ])->find($id);

        if (!$lessonPlan) {
            return $this->sendError('Lesson plan not found', [], 404);
        }

        // Only show published unless user is the teacher
        if ($lessonPlan->status !== 'published') {
            return $this->sendError('Lesson plan not available', ['error' => 'This lesson plan is not published yet'], 403);
        }

        return $this->sendResponse($lessonPlan, 'Lesson plan retrieved successfully');
    }

    /**
     * Get today's lesson plans for a class
     */
    public function todayLessons(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:sections,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $lessonPlans = LessonPlan::with(['teacher:id,name', 'subject:id,name'])
            ->where('class_id', $request->class_id)
            ->where('section_id', $request->section_id)
            ->whereDate('date', Carbon::today())
            ->where('status', 'published')
            ->orderBy('period_number')
            ->get();

        return $this->sendResponse($lessonPlans, 'Today\'s lesson plans retrieved successfully');
    }

    /**
     * Get this week's lesson plans
     */
    public function weekLessons(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:sections,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $lessonPlans = LessonPlan::with(['teacher:id,name', 'subject:id,name'])
            ->where('class_id', $request->class_id)
            ->where('section_id', $request->section_id)
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->where('status', 'published')
            ->orderBy('date')
            ->orderBy('period_number')
            ->get()
            ->groupBy(function($item) {
                return Carbon::parse($item->date)->format('l'); // Day name
            });

        return $this->sendResponse($lessonPlans, 'This week\'s lesson plans retrieved successfully');
    }

    /**
     * Get lesson plans by teacher (for teacher app)
     */
    public function myLessonPlans(Request $request)
    {
        $user = $request->user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return $this->sendError('Teacher not found', ['error' => 'No teacher record associated with this account'], 404);
        }

        $query = LessonPlan::with(['subject:id,name', 'class:id,name', 'section:id,name'])
            ->where('teacher_id', $teacher->id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        } else {
            // Default: show current and future lesson plans
            $query->whereDate('date', '>=', Carbon::today());
        }

        $lessonPlans = $query->orderBy('date')
            ->orderBy('period_number')
            ->paginate($request->per_page ?? 20);

        return $this->sendResponse($lessonPlans, 'Lesson plans retrieved successfully');
    }

    /**
     * Create lesson plan (teacher only)
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return $this->sendError('Unauthorized', ['error' => 'Only teachers can create lesson plans'], 403);
        }

        $validator = Validator::make($request->all(), [
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'date' => 'required|date',
            'period_number' => 'required|integer|min:1|max:10',
            'topic' => 'required|string|max:255',
            'learning_objectives' => 'nullable|string',
            'teaching_methodology' => 'nullable|string',
            'resources_required' => 'nullable|string',
            'homework_assignment' => 'nullable|string',
            'status' => 'sometimes|in:draft,published',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $lessonPlan = LessonPlan::create([
            'teacher_id' => $teacher->id,
            'class_id' => $request->class_id,
            'section_id' => $request->section_id,
            'subject_id' => $request->subject_id,
            'date' => $request->date,
            'period_number' => $request->period_number,
            'topic' => $request->topic,
            'learning_objectives' => $request->learning_objectives,
            'teaching_methodology' => $request->teaching_methodology,
            'resources_required' => $request->resources_required,
            'homework_assignment' => $request->homework_assignment,
            'status' => $request->status ?? 'draft',
        ]);

        return $this->sendResponse($lessonPlan, 'Lesson plan created successfully', 201);
    }

    /**
     * Update lesson plan (teacher only)
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return $this->sendError('Unauthorized', ['error' => 'Only teachers can update lesson plans'], 403);
        }

        $lessonPlan = LessonPlan::find($id);

        if (!$lessonPlan) {
            return $this->sendError('Lesson plan not found', [], 404);
        }

        // Check if teacher owns this lesson plan
        if ($lessonPlan->teacher_id !== $teacher->id) {
            return $this->sendError('Unauthorized', ['error' => 'You can only update your own lesson plans'], 403);
        }

        $validator = Validator::make($request->all(), [
            'topic' => 'sometimes|string|max:255',
            'learning_objectives' => 'sometimes|string',
            'teaching_methodology' => 'sometimes|string',
            'resources_required' => 'sometimes|string',
            'homework_assignment' => 'sometimes|string',
            'status' => 'sometimes|in:draft,published',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $lessonPlan->update($request->only([
            'topic',
            'learning_objectives',
            'teaching_methodology',
            'resources_required',
            'homework_assignment',
            'status'
        ]));

        return $this->sendResponse($lessonPlan, 'Lesson plan updated successfully');
    }
}
