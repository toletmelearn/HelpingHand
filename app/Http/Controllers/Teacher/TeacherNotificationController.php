<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 2.6: the teacher layout had no notification UI at all (unlike the
 * admin layout's navbar bell). A simple list page rather than a
 * dropdown/bell widget, since this layout is sidebar-only with no header
 * bar to attach a dropdown to.
 */
class TeacherNotificationController extends Controller
{
    public function index()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $notifications = $teacherLogin->notifications()->latest()->paginate(20);

        return view('teacher.notifications.index', compact('notifications'));
    }

    public function markRead(string $id)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $notification = $teacherLogin->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return redirect()->back();
    }
}
