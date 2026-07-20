<?php

use Illuminate\Support\Facades\Route;

// Temporary route to test the fix
Route::get('/test-subject-fix', function () {
    try {
        // Test if withTrashed works
        $subjects = \App\Models\Subject::withTrashed()->count();
        return response()->json([
            'status' => 'success',
            'message' => 'Subject model withTrashed() works correctly',
            'count' => $subjects
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});