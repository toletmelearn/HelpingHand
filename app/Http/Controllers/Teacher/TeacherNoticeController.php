<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\HomeworkNotice;

class TeacherNoticeController extends Controller
{
    /**
     * Display a listing of notices from admin.
     */
    public function index()
    {
        $teacher = Auth::guard('teacher')->user();

        // Get all notices and announcements (not homework)
        $notices = HomeworkNotice::whereIn('type', ['notice', 'announcement'])
            ->where('status', 'active')
            ->latest('publish_date')
            ->paginate(20);

        return view('teacher.notices.index', compact('notices'));
    }
}
