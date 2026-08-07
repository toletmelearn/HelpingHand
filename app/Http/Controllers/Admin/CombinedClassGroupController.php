<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\CombinedClassGroup;
use App\Models\CombinedClassGroupMember;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CombinedClassGroupController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', CombinedClassGroup::class);

        $groups = CombinedClassGroup::with(['subject', 'academicSession', 'members.schoolClass', 'members.section'])
            ->orderBy('name')
            ->get();

        $teachers = Teacher::active()->orderBy('name')->get();
        $bellTimings = BellTiming::where('is_active', true)->orderBy('order_index')->get();

        return view('admin.timetable.combined-groups.index', compact('groups', 'teachers', 'bellTimings'));
    }

    public function create()
    {
        $this->authorize('create', CombinedClassGroup::class);

        $subjects = Subject::orderBy('name')->get();
        $sessions = AcademicSession::orderByDesc('id')->get();
        $classes = SchoolClass::active()->orderByOrder()->get();
        $sections = Section::orderBy('name')->get();

        return view('admin.timetable.combined-groups.create', compact('subjects', 'sessions', 'classes', 'sections'));
    }

    /**
     * A group needs at least 2 member class-sections -- a "combined
     * group" of one class isn't combined.
     */
    public function store(Request $request)
    {
        $this->authorize('create', CombinedClassGroup::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject_id' => 'required|exists:subjects,id',
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'members' => 'required|array|min:2',
            'members.*.school_class_id' => 'required|exists:school_classes,id',
            'members.*.section_id' => 'nullable|exists:sections,id',
        ]);

        $group = DB::transaction(function () use ($validated) {
            $group = CombinedClassGroup::create([
                'name' => $validated['name'],
                'subject_id' => $validated['subject_id'],
                'academic_session_id' => $validated['academic_session_id'],
            ]);

            foreach ($validated['members'] as $member) {
                CombinedClassGroupMember::create([
                    'combined_class_group_id' => $group->id,
                    'school_class_id' => $member['school_class_id'],
                    'section_id' => $member['section_id'] ?? null,
                ]);
            }

            return $group;
        });

        return redirect()->route('combined-class-groups.index')
            ->with('success', "Combined group \"{$group->name}\" created with " . count($validated['members']) . ' member classes.');
    }

    public function destroy(CombinedClassGroup $combinedClassGroup)
    {
        $this->authorize('delete', $combinedClassGroup);

        $combinedClassGroup->delete();

        return redirect()->route('combined-class-groups.index')
            ->with('success', 'Combined group removed. Any timetable slots already placed for it were kept but detached from the group.');
    }
}
