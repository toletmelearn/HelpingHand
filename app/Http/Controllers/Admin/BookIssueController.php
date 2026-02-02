<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Book;
use App\Models\BookIssue;
use App\Models\Student;
use App\Models\LibrarySetting;

class BookIssueController extends Controller
{
    public function index()
    {
        $bookIssues = BookIssue::with('book', 'student', 'issuer')
            ->latest()
            ->paginate(20);
        
        return view('admin.book-issues.index', compact('bookIssues'));
    }

    public function create()
    {
        $books = Book::where('is_active', true)
            ->withCount('bookIssues as issued_copies')
            ->get()
            ->map(function ($book) {
                $book->available_copies = $book->total_quantity - $book->issued_copies;
                return $book;
            })
            ->filter(function ($book) {
                return $book->available_copies > 0;
            });
        
        $students = Student::all();
        
        return view('admin.book-issues.create', compact('books', 'students'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'book_id' => 'required|exists:books,id',
            'student_id' => 'required|exists:students,id',
            'issue_date' => 'required|date',
        ]);

        $book = Book::findOrFail($request->book_id);
        $issuedCopies = $book->bookIssues()->where('status', 'issued')->count();
        
        if ($issuedCopies >= $book->total_quantity) {
            return redirect()->back()
                ->with('error', 'No copies available for this book!')
                ->withInput();
        }

        $setting = LibrarySetting::getSetting();
        $dueDate = $request->issue_date ? 
            $request->issue_date : 
            now()->addDays($setting->default_issue_days);

        BookIssue::create([
            'book_id' => $request->book_id,
            'student_id' => $request->student_id,
            'issued_by' => request()->user()->id,
            'issue_date' => $request->issue_date,
            'due_date' => $dueDate,
            'status' => 'issued',
        ]);

        return redirect()->route('book-issues.index')
            ->with('success', 'Book issued successfully!');
    }

    public function show(BookIssue $bookIssue)
    {
        $bookIssue->load('book', 'student', 'issuer');
        return view('admin.book-issues.show', compact('bookIssue'));
    }

    public function edit(BookIssue $bookIssue)
    {
        $books = Book::where('is_active', true)->get();
        $students = Student::all();
        
        return view('admin.book-issues.edit', compact('bookIssue', 'books', 'students'));
    }

    public function update(Request $request, BookIssue $bookIssue)
    {
        $request->validate([
            'book_id' => 'required|exists:books,id',
            'student_id' => 'required|exists:students,id',
            'issue_date' => 'required|date',
            'due_date' => 'required|date',
        ]);

        $bookIssue->update($request->all());

        return redirect()->route('book-issues.index')
            ->with('success', 'Book issue updated successfully!');
    }

    public function destroy(BookIssue $bookIssue)
    {
        $bookIssue->delete();
        return redirect()->route('book-issues.index')
            ->with('success', 'Book issue deleted successfully!');
    }

    public function returnBook($id)
    {
        $bookIssue = BookIssue::findOrFail($id);
        
        if ($bookIssue->status !== 'issued') {
            return redirect()->back()
                ->with('error', 'This book is not currently issued!');
        }

        $returnDate = now();
        $delayDays = $returnDate->gt($bookIssue->due_date) ? 
            $returnDate->diffInDays($bookIssue->due_date) : 0;
        
        $setting = LibrarySetting::getSetting();
        $fineAmount = $delayDays * $setting->fine_per_day;

        $bookIssue->update([
            'return_date' => $returnDate,
            'delay_days' => $delayDays,
            'fine_amount' => $fineAmount,
            'status' => 'returned',
        ]);

        return redirect()->route('book-issues.index')
            ->with('success', 'Book returned successfully! Fine amount: $' . $fineAmount);
    }

    public function reports()
    {
        $totalIssued = BookIssue::issued()->count();
        $totalReturned = BookIssue::returned()->count();
        $totalOverdue = BookIssue::overdue()->count();
        
        $mostIssuedBooks = Book::withCount('bookIssues as total_issues')
            ->orderBy('total_issues', 'desc')
            ->limit(10)
            ->get();

        $fineCollection = BookIssue::where('status', 'returned')
            ->where('fine_amount', '>', 0)
            ->sum('fine_amount');

        return view('admin.book-issues.reports', compact(
            'totalIssued',
            'totalReturned',
            'totalOverdue',
            'mostIssuedBooks',
            'fineCollection'
        ));
    }

    public function exportReport($type = 'excel')
    {
        $bookIssues = BookIssue::with('book', 'student', 'issuer')->get();
        $librarySetting = LibrarySetting::getSetting();

        if ($type === 'pdf') {
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('admin.book-issues.export-pdf', compact('bookIssues', 'librarySetting'));
            return $pdf->download('library_report_' . now()->format('Y-m-d') . '.pdf');
        } else {
            // Export as Excel/CSV
            $headers = [
                'Content-Type' => 'text/csv',
            ];
            $callback = function() use ($bookIssues) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Book Name', 'Author', 'Student Name', 'Issue Date', 'Due Date', 'Return Date', 'Status', 'Fine Amount']);
                foreach ($bookIssues as $issue) {
                    fputcsv($file, [
                        $issue->book->book_name,
                        $issue->book->author,
                        $issue->student->first_name . ' ' . $issue->student->last_name,
                        $issue->issue_date->format('Y-m-d'),
                        $issue->due_date->format('Y-m-d'),
                        $issue->return_date ? $issue->return_date->format('Y-m-d') : '',
                        ucfirst($issue->status),
                        $issue->fine_amount
                    ]);
                }
                fclose($file);
            };

            return response()->streamDownload($callback, 'library_report_' . now()->format('Y-m-d') . '.csv', $headers);
        }
    }
}