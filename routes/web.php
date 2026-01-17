<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentController;

// Home Page Route
Route::get('/', function () {
    return view('welcome'); // Ye ab aapka custom page hoga
});

// Students Routes
Route::resource('students', StudentController::class);

// Agar resource routes kaam nahi kar rahe, to manually define karo:
Route::get('/students', [StudentController::class, 'index'])->name('students.index');
Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
Route::post('/students', [StudentController::class, 'store'])->name('students.store');