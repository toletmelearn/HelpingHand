<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Book;
use App\Models\BookIssue;
use App\Models\Student;
use App\Models\LibrarySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class LibraryOPACTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Book $book;
    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->book = Book::create([
            'book_name' => 'Introduction to PHP',
            'isbn' => '978-3-16-148410-0',
            'author' => 'Rasmus Lerdorf',
            'publisher' => 'O Reilly Media',
            'subject' => 'Computer Science',
            'class_grade' => 'Grade 10',
            'total_quantity' => 5,
            'rack_shelf_number' => 'CS-R4-S2',
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'name' => 'Jane Smith',
            'father_name' => 'John Smith',
            'mother_name' => 'Mary Smith',
            'date_of_birth' => '2016-05-15',
            'aadhar_number' => '123456789012',
            'address' => '123 School Lane',
            'phone' => '9876543210',
            'gender' => 'female',
            'admission_no' => 'ADM-0092',
        ]);
    }

    public function test_library_dashboard_loads()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('library.index'));

        $response->assertStatus(200);
        $response->assertSee('Library Catalog');
    }

    public function test_can_issue_book_successfully()
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('library.issue'), [
                'book_id' => $this->book->id,
                'student_id' => $this->student->id,
                'due_date' => Carbon::tomorrow()->format('Y-m-d'),
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('book_issues', [
            'book_id' => $this->book->id,
            'student_id' => $this->student->id,
            'status' => 'issued',
        ]);

        // Assert quantity logic
        $this->assertEquals(4, $this->book->fresh()->available_copies);
    }

    public function test_cannot_issue_out_of_stock_book()
    {
        $this->book->update(['total_quantity' => 0]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('library.issue'), [
                'book_id' => $this->book->id,
                'student_id' => $this->student->id,
                'due_date' => Carbon::tomorrow()->format('Y-m-d'),
            ]);

        $response->assertSessionHas('error');
    }

    public function test_can_return_issued_book()
    {
        $issue = BookIssue::create([
            'book_id' => $this->book->id,
            'student_id' => $this->student->id,
            'issued_by' => $this->adminUser->id,
            'issue_date' => Carbon::yesterday(),
            'due_date' => Carbon::tomorrow(),
            'status' => 'issued',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('library.return-book', $issue->id));

        $response->assertRedirect();
        $this->assertEquals('returned', $issue->fresh()->status);
        $this->assertEquals(Carbon::today()->format('Y-m-d'), $issue->fresh()->return_date->format('Y-m-d'));
        $this->assertEquals(0.00, $issue->fresh()->fine_amount);
    }

    public function test_public_opac_page_loads_and_filters()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('library.opac', [
                'search' => 'Introduction',
                'subject' => 'Computer Science',
            ]));

        $response->assertStatus(200);
        $response->assertSee('Introduction to PHP');
    }

    public function test_library_settings_can_be_updated()
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('library.settings'), [
                'default_issue_days' => 20,
                'fine_per_day' => 2.50,
            ]);

        $response->assertRedirect();
        $settings = LibrarySetting::getSetting();
        $this->assertEquals(20, $settings->default_issue_days);
        $this->assertEquals(2.50, $settings->fine_per_day);
    }

    public function test_fine_scheduler_calculations()
    {
        // Seeding an overdue issue from 5 days ago
        $issue = BookIssue::create([
            'book_id' => $this->book->id,
            'student_id' => $this->student->id,
            'issued_by' => $this->adminUser->id,
            'issue_date' => Carbon::today()->subDays(10),
            'due_date' => Carbon::today()->subDays(5),
            'status' => 'issued',
            'fine_amount' => 0.00,
            'delay_days' => 0,
        ]);

        // Run the command
        Artisan::call('library:calculate-fines');

        $issue->refresh();
        $this->assertEquals(5, $issue->delay_days);

        $settings = LibrarySetting::getSetting();
        $expectedFine = 5 * $settings->fine_per_day;
        $this->assertEquals($expectedFine, $issue->fine_amount);
    }
}
