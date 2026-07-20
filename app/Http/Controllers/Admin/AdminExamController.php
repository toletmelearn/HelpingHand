<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminExamController extends Controller
{
    public function resultMonitor()
    {
        return "Result monitor working";
    }

    public function classResults()
    {
        return "Class result working";
    }
}
