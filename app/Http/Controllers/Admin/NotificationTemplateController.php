<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    public function index()
    {
        $templates = NotificationTemplate::all();
        return view('admin.notifications.index', compact('templates'));
    }
    
    public function create()
    {
        return view('admin.notifications.create');
    }
    
    public function store(Request $request)
    {
        $template = NotificationTemplate::create($request->all());
        return redirect()->route('admin.notifications.index');
    }
    
    public function show(NotificationTemplate $notificationTemplate)
    {
        return view('admin.notifications.show', compact('notificationTemplate'));
    }
    
    public function edit(NotificationTemplate $notificationTemplate)
    {
        return view('admin.notifications.edit', compact('notificationTemplate'));
    }
    
    public function update(Request $request, NotificationTemplate $notificationTemplate)
    {
        $notificationTemplate->update($request->all());
        return redirect()->route('admin.notifications.index');
    }
    
    public function destroy(NotificationTemplate $notificationTemplate)
    {
        $notificationTemplate->delete();
        return redirect()->route('admin.notifications.index');
    }
    
    public function test(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'Test notification sent']);
    }
}
