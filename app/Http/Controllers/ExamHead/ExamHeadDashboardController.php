<?php

namespace App\Http\Controllers\ExamHead;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExamHeadDashboardController extends Controller
{
    public function index()
    {
        return view('admin.exam-head.dashboard');
    }
}