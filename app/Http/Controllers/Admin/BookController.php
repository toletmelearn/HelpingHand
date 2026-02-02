<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Book;
use App\Models\BookIssue;
use App\Models\LibrarySetting;

class BookController extends Controller
{
    public function index()
    {
        $books = Book::where('is_active', true)->withCount('bookIssues as issued_copies')
            ->get()
            ->map(function ($book) {
                $book->available_copies = $book->total_quantity - $book->issued_copies;
                return $book;
            });
        
        return view('admin.books.index', compact('books'));
    }

    public function create()
    {
        return view('admin.books.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'book_name' => 'required|string|max:255',
            'isbn' => 'nullable|string|max:20',
            'author' => 'required|string|max:255',
            'publisher' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'class_grade' => 'required|string|max:50',
            'total_quantity' => 'required|integer|min:1',
            'rack_shelf_number' => 'required|string|max:50',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->except('cover_image');
        
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('book_covers', 'public');
        }

        Book::create($data);

        return redirect()->route('books.index')
            ->with('success', 'Book added successfully!');
    }

    public function show(Book $book)
    {
        $book->load('bookIssues.student', 'bookIssues.issuer');
        return view('admin.books.show', compact('book'));
    }

    public function edit(Book $book)
    {
        return view('admin.books.edit', compact('book'));
    }

    public function update(Request $request, Book $book)
    {
        $request->validate([
            'book_name' => 'required|string|max:255',
            'isbn' => 'nullable|string|max:20',
            'author' => 'required|string|max:255',
            'publisher' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'class_grade' => 'required|string|max:50',
            'total_quantity' => 'required|integer|min:1',
            'rack_shelf_number' => 'required|string|max:50',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->except('cover_image');
        
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('book_covers', 'public');
        }

        $book->update($data);

        return redirect()->route('books.index')
            ->with('success', 'Book updated successfully!');
    }

    public function destroy(Book $book)
    {
        $book->update(['is_active' => false]);
        return redirect()->route('books.index')
            ->with('success', 'Book deactivated successfully!');
    }

    public function dashboard()
    {
        $totalBooks = Book::where('is_active', true)->count();
        $totalCopies = Book::where('is_active', true)->sum('total_quantity');
        $issuedBooks = BookIssue::where('status', 'issued')->count();
        $overdueBooks = BookIssue::overdue()->count();
        $todayReturns = BookIssue::where('status', 'issued')
            ->where('due_date', today())
            ->count();
        
        $lowStockBooks = Book::where('is_active', true)
            ->withCount('bookIssues as issued_copies')
            ->get()
            ->filter(function ($book) {
                $available = $book->total_quantity - $book->issued_copies;
                $threshold = LibrarySetting::getSetting()->low_stock_threshold;
                return $available <= $threshold;
            });

        return view('admin.books.dashboard', compact(
            'totalBooks',
            'totalCopies',
            'issuedBooks',
            'overdueBooks',
            'todayReturns',
            'lowStockBooks'
        ));
    }
}
