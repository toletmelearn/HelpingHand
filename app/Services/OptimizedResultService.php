<?php

namespace App\Services;

use App\Models\Result;
use App\Models\Student;
use App\Models\Exam;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OptimizedResultService
{
    protected $cacheTtl = 3600; // 1 hour cache
    
    /**
     * Get optimized student results with caching
     */
    public function getStudentResults($studentId, $examId = null, $withCache = true)
    {
        $cacheKey = "student_results_{$studentId}" . ($examId ? "_{$examId}" : '');
        
        if ($withCache) {
            return Cache::remember($cacheKey, $this->cacheTtl, function() use ($studentId, $examId) {
                return $this->fetchStudentResults($studentId, $examId);
            });
        }
        
        return $this->fetchStudentResults($studentId, $examId);
    }
    
    /**
     * Fetch student results from database with optimized queries
     */
    private function fetchStudentResults($studentId, $examId = null)
    {
        $query = Result::with([
            'exam:id,name,subject,total_marks,passing_marks',
            'subject:id,name',
            'schoolClass:id,class_name,section'
        ])
        ->where('student_id', $studentId);
        
        if ($examId) {
            $query->where('exam_id', $examId);
        }
        
        return $query->orderBy('exam_id', 'desc')
                    ->orderBy('subject_id', 'asc')
                    ->get();
    }
    
    /**
     * Calculate overall result statistics with optimized queries
     */
    public function calculateOverallResult($studentId, $examId)
    {
        $cacheKey = "overall_result_{$studentId}_{$examId}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($studentId, $examId) {
            return $this->calculateOverallResultWithoutCache($studentId, $examId);
        });
    }
    
    /**
     * Calculate overall result without caching
     */
    private function calculateOverallResultWithoutCache($studentId, $examId)
    {
        $results = Result::where('student_id', $studentId)
                        ->where('exam_id', $examId)
                        ->select('marks_obtained', 'total_marks', 'percentage', 'result_status')
                        ->get();
        
        if ($results->isEmpty()) {
            return null;
        }
        
        $totalObtained = $results->sum('marks_obtained');
        $totalMarks = $results->sum('total_marks');
        $overallPercentage = $totalMarks > 0 ? round(($totalObtained / $totalMarks) * 100, 2) : 0;
        
        // Determine overall grade using optimized grading system
        $overallGrade = $this->calculateGrade($overallPercentage);
        
        // Check if student failed in any subject
        $hasFailedSubject = $results->contains('result_status', 'fail');
        $finalResult = $hasFailedSubject ? 'FAIL' : 'PASS';
        
        return [
            'total_obtained' => $totalObtained,
            'total_marks' => $totalMarks,
            'overall_percentage' => $overallPercentage,
            'overall_grade' => $overallGrade,
            'final_result' => $finalResult,
            'subjects_count' => $results->count()
        ];
    }
    
    /**
     * Optimized grade calculation
     */
    public function calculateGrade($percentage)
    {
        static $gradeMap = [
            [90, 'A1'],
            [80, 'A2'],
            [70, 'B1'],
            [60, 'B2'],
            [50, 'C1'],
            [40, 'C2'],
            [33, 'D'],
            [0, 'F']
        ];
        
        foreach ($gradeMap as $grade) {
            if ($percentage >= $grade[0]) {
                return $grade[1];
            }
        }
        
        return 'F';
    }
    
    /**
     * Generate class rankings with optimized queries
     */
    public function generateClassRankings($examId, $classId = null)
    {
        $query = Result::where('exam_id', $examId)
                      ->select('student_id', DB::raw('SUM(marks_obtained) as total_marks'))
                      ->groupBy('student_id');
        
        if ($classId) {
            $query->join('students', 'results.student_id', '=', 'students.id')
                  ->where('students.class_id', $classId);
        }
        
        $rankings = $query->orderBy('total_marks', 'desc')
                         ->get()
                         ->values();
        
        // Update rankings
        foreach ($rankings as $index => $ranking) {
            Result::where('exam_id', $examId)
                  ->where('student_id', $ranking->student_id)
                  ->update(['class_rank' => $index + 1]);
        }
        
        return $rankings;
    }
    
    /**
     * Get optimized exam statistics
     */
    public function getExamStatistics($examId)
    {
        $cacheKey = "exam_statistics_{$examId}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($examId) {
            return $this->calculateExamStatistics($examId);
        });
    }
    
    /**
     * Calculate exam statistics with optimized queries
     */
    private function calculateExamStatistics($examId)
    {
        $results = Result::where('exam_id', $examId)
                        ->select(
                            DB::raw('COUNT(*) as total_students'),
                            DB::raw('COUNT(CASE WHEN result_status = "pass" THEN 1 END) as passed'),
                            DB::raw('COUNT(CASE WHEN result_status = "fail" THEN 1 END) as failed'),
                            DB::raw('AVG(percentage) as average_percentage'),
                            DB::raw('MAX(percentage) as highest_percentage'),
                            DB::raw('MIN(percentage) as lowest_percentage')
                        )
                        ->first();
        
        $passPercentage = $results->total_students > 0 ? 
            round(($results->passed / $results->total_students) * 100, 2) : 0;
        
        return [
            'total_students' => $results->total_students,
            'passed' => $results->passed,
            'failed' => $results->failed,
            'pass_percentage' => $passPercentage,
            'average_percentage' => round($results->average_percentage, 2),
            'highest_percentage' => $results->highest_percentage,
            'lowest_percentage' => $results->lowest_percentage
        ];
    }
    
    /**
     * Get subject-wise performance with optimized queries
     */
    public function getSubjectPerformance($examId, $subjectId = null)
    {
        $cacheKey = "subject_performance_{$examId}" . ($subjectId ? "_{$subjectId}" : '');
        
        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($examId, $subjectId) {
            return $this->calculateSubjectPerformance($examId, $subjectId);
        });
    }
    
    /**
     * Calculate subject performance statistics
     */
    private function calculateSubjectPerformance($examId, $subjectId = null)
    {
        $query = Result::where('exam_id', $examId);
        
        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }
        
        return $query->select(
            'subject_id',
            DB::raw('COUNT(*) as total_students'),
            DB::raw('AVG(marks_obtained) as average_marks'),
            DB::raw('MAX(marks_obtained) as highest_marks'),
            DB::raw('MIN(marks_obtained) as lowest_marks'),
            DB::raw('COUNT(CASE WHEN result_status = "pass" THEN 1 END) as passed'),
            DB::raw('COUNT(CASE WHEN result_status = "fail" THEN 1 END) as failed')
        )
        ->groupBy('subject_id')
        ->with('subject:id,name')
        ->get();
    }
    
    /**
     * Clear result cache for a student
     */
    public function clearStudentCache($studentId)
    {
        Cache::forget("student_results_{$studentId}");
        // Clear any exam-specific caches
        $examIds = Result::where('student_id', $studentId)->pluck('exam_id')->unique();
        foreach ($examIds as $examId) {
            Cache::forget("overall_result_{$studentId}_{$examId}");
        }
    }
    
    /**
     * Clear exam cache
     */
    public function clearExamCache($examId)
    {
        Cache::forget("exam_statistics_{$examId}");
        Cache::forget("subject_performance_{$examId}");
        // Clear class ranking cache
        Cache::forget("class_rankings_{$examId}");
    }
    
    /**
     * Bulk update results with optimized database operations
     */
    public function bulkUpdateResults($resultsData)
    {
        DB::transaction(function() use ($resultsData) {
            foreach ($resultsData as $data) {
                Result::updateOrCreate(
                    [
                        'student_id' => $data['student_id'],
                        'exam_id' => $data['exam_id'],
                        'subject_id' => $data['subject_id']
                    ],
                    [
                        'marks_obtained' => $data['marks_obtained'],
                        'total_marks' => $data['total_marks'],
                        'percentage' => $data['percentage'],
                        'grade' => $data['grade'],
                        'result_status' => $data['result_status'],
                        'updated_at' => now()
                    ]
                );
            }
        });
        
        // Clear relevant caches
        $studentIds = collect($resultsData)->pluck('student_id')->unique();
        $examIds = collect($resultsData)->pluck('exam_id')->unique();
        
        foreach ($studentIds as $studentId) {
            $this->clearStudentCache($studentId);
        }
        
        foreach ($examIds as $examId) {
            $this->clearExamCache($examId);
        }
    }
    
    /**
     * Get paginated results with optimized loading
     */
    public function getPaginatedResults($examId, $perPage = 50, $page = 1)
    {
        return Result::with([
            'student:id,name,roll_number,class_id',
            'student.schoolClass:id,class_name,section',
            'subject:id,name',
            'exam:id,name'
        ])
        ->where('exam_id', $examId)
        ->orderBy('student_id')
        ->paginate($perPage, ['*'], 'page', $page);
    }
}