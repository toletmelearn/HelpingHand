<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookIssue;
use App\Models\Student;
use App\Models\LibrarySetting;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $statusFilter = $request->get('status');
        $search = $request->get('search');

        $books = Book::all();
        $students = Student::all();
        $settings = LibrarySetting::getSetting();

        // Calculate metrics
        $totalBooks = Book::sum('total_quantity');
        $totalIssued = BookIssue::where('status', 'issued')->count();
        $totalOverdue = BookIssue::where('status', 'issued')
            ->where('due_date', '<', Carbon::today())
            ->count();

        // Query issues
        $issuesQuery = BookIssue::with(['book', 'student', 'issuer']);

        if ($statusFilter) {
            if ($statusFilter === 'overdue') {
                $issuesQuery->where('status', 'issued')->where('due_date', '<', Carbon::today());
            } else {
                $issuesQuery->where('status', $statusFilter);
            }
        }

        if ($search) {
            $issuesQuery->where(function ($q) use ($search) {
                $q->whereHas('student', function ($sq) use ($search) {
                    $sq->where('first_name', 'like', "%{$search}%")
                       ->orWhere('last_name', 'like', "%{$search}%");
                })->orWhereHas('book', function ($bq) use ($search) {
                    $bq->where('book_name', 'like', "%{$search}%")
                       ->orWhere('isbn', 'like', "%{$search}%");
                });
            });
        }

        $issues = $issuesQuery->orderBy('issue_date', 'desc')->get();

        return view('admin.library.index', compact(
            'books',
            'students',
            'settings',
            'totalBooks',
            'totalIssued',
            'totalOverdue',
            'issues',
            'statusFilter',
            'search'
        ));
    }

    public function issueBook(Request $request)
    {
        $validated = $request->validate([
            'book_id' => 'required|exists:books,id',
            'student_id' => 'required|exists:students,id',
            'due_date' => 'required|date|after_or_equal:today',
        ]);

        $book = Book::findOrFail($validated['book_id']);
        if ($book->available_copies <= 0) {
            return back()->with('error', 'Book is currently out of stock.');
        }

        $settings = LibrarySetting::getSetting();
        $activeIssuesCount = BookIssue::where('student_id', $validated['student_id'])
            ->where('status', 'issued')
            ->count();

        if ($activeIssuesCount >= $settings->default_issue_days) { // default_issue_days maps to max books allowed in basic table config
            return back()->with('error', 'Student has reached the maximum allowed issued books limit.');
        }

        BookIssue::create([
            'book_id' => $validated['book_id'],
            'student_id' => $validated['student_id'],
            'issued_by' => auth()->id() ?: 1, // Fallback to 1 for tests/seeding
            'issue_date' => Carbon::today(),
            'due_date' => Carbon::parse($validated['due_date']),
            'status' => 'issued',
            'fine_amount' => 0.00,
            'delay_days' => 0,
        ]);

        return back()->with('success', 'Book issued successfully.');
    }

    public function returnBook(Request $request, $id)
    {
        $issue = BookIssue::findOrFail($id);
        if ($issue->status === 'returned') {
            return back()->with('error', 'Book is already returned.');
        }

        $today = Carbon::today();
        $delayDays = 0;
        $fineAmount = 0.00;

        if ($today->greaterThan($issue->due_date)) {
            $delayDays = $today->diffInDays($issue->due_date, true);
            $settings = LibrarySetting::getSetting();
            $fineAmount = $delayDays * $settings->fine_per_day;
        }

        $issue->update([
            'return_date' => $today,
            'delay_days' => $delayDays,
            'fine_amount' => $fineAmount,
            'status' => 'returned',
        ]);

        return back()->with('success', 'Book returned successfully.' . ($fineAmount > 0 ? " Late fine charged: \${$fineAmount}" : ""));
    }

    public function opac(Request $request)
    {
        $search = $request->get('search');
        $subject = $request->get('subject');
        $grade = $request->get('class_grade');

        $query = Book::where('is_active', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('book_name', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%")
                  ->orWhere('isbn', 'like', "%{$search}%");
            });
        }

        if ($subject) {
            $query->where('subject', $subject);
        }

        if ($grade) {
            $query->where('class_grade', $grade);
        }

        $books = $query->orderBy('book_name')->get();
        $subjects = Book::select('subject')->distinct()->pluck('subject');
        $grades = Book::select('class_grade')->distinct()->pluck('class_grade');

        return view('admin.library.opac', compact('books', 'subjects', 'grades', 'search', 'subject', 'grade'));
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'default_issue_days' => 'required|integer|min:1',
            'fine_per_day' => 'required|numeric|min:0',
        ]);

        $settings = LibrarySetting::getSetting();
        $settings->update($validated);

        return back()->with('success', 'Library settings updated successfully.');
    }
}
