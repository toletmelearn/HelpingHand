# Teacher Panel Implementation Plan

## Overview
Create a complete Teacher Panel with authentication system, dashboard, marks management, and exam head features. All changes will be isolated to avoid breaking existing admin and parent panel functionality.

## Phase 1: Database Setup

### 1.1 Add Password Column to Teachers Table
**File:** Create new migration `database/migrations/YYYY_MM_DD_HHMMSS_add_password_and_mobile_to_teachers_table.php`

Add:
- `password` (string, nullable, hashed)
- `mobile` (string, nullable, unique) - for login
- Update `employee_id` to be unique
- `remember_token` (already exists in original migration)

### 1.2 Create Teacher Assignments Table
**File:** Create migration `database/migrations/YYYY_MM_DD_HHMMSS_create_teacher_class_subject_assignments_table.php`

Schema:
```php
teacher_class_subject_assignments:
- id
- teacher_id (foreign key to teachers)
- class_id (foreign key to school_classes)
- section_id (nullable, foreign key)
- subject_id (foreign key to subjects)
- is_class_teacher (boolean, default false)
- academic_year
- timestamps
```

### 1.3 Create Exam Head Table
**File:** Create migration `database/migrations/YYYY_MM_DD_HHMMSS_create_exam_heads_table.php`

Schema:
```php
exam_heads:
- id
- teacher_id (foreign key to teachers, unique)
- assigned_by (foreign key to users)
- assigned_at (timestamp)
- status (enum: active, inactive)
- timestamps
```

### 1.4 Create/Update Exam Marks Table
Check if `exam_marks` or similar table exists. If not, create migration:

Schema:
```php
exam_marks:
- id
- student_id (foreign key)
- class_id (foreign key)
- subject_id (foreign key)
- exam_id (foreign key to exams)
- marks (decimal)
- uploaded_by_teacher_id (foreign key to teachers)
- status (enum: draft, submitted, approved, rejected)
- remarks (text, nullable)
- uploaded_at (timestamp)
- approved_by (foreign key to users, nullable)
- approved_at (timestamp, nullable)
- timestamps
```

Note: Existing system uses `results` table - we'll leverage this with additional columns if needed.

### 1.5 Update Teacher Model
**File:** `app/Models/Teacher.php`

Make Teacher model Authenticatable:
- Add `use Illuminate\Foundation\Auth\User as Authenticatable;`
- Change `extends Model` to `extends Authenticatable`
- Add password to `$hidden` array
- Add relationships for assignments, exam marks, exam head

## Phase 2: Authentication System

### 2.1 Configure Auth Guard
**File:** `config/auth.php`

Add teacher guard:
```php
'guards' => [
    // ... existing guards
    'teacher' => [
        'driver' => 'session',
        'provider' => 'teachers',
    ],
],

'providers' => [
    // ... existing providers
    'teachers' => [
        'driver' => 'eloquent',
        'model' => App\Models\Teacher::class,
    ],
],
```

### 2.2 Create Teacher Auth Middleware
**File:** `app/Http/Middleware/TeacherAuth.php`

Similar to `ParentAuth.php`:
- Check `Auth::guard('teacher')->check()`
- Redirect to `teacher.login` if not authenticated

### 2.3 Register Middleware
**File:** `app/Http/Kernel.php`

Add to `$routeMiddleware`:
```php
'teacher.auth' => \App\Http\Middleware\TeacherAuth::class,
```

### 2.4 Update RedirectIfAuthenticated Middleware
**File:** `app/Http/Middleware/RedirectIfAuthenticated.php`

Add teacher guard handling:
```php
if ($guard == 'teacher') {
    return redirect('/teacher/dashboard');
}
```

### 2.5 Create Teacher Auth Controller
**File:** `app/Http/Controllers/Teacher/TeacherAuthController.php`

Methods:
- `showLogin()` - display login form
- `login(Request $request)` - authenticate using mobile/employee_id + password
- `logout()` - logout teacher
- Login validates against mobile OR employee_id

## Phase 3: Teacher Routes

**File:** `routes/web.php`

Add teacher routes group:
```php
Route::prefix('teacher')->group(function () {
    
    // Public routes
    Route::get('/login', [TeacherAuthController::class, 'showLogin'])
        ->name('teacher.login');
    Route::post('/login', [TeacherAuthController::class, 'login'])
        ->name('teacher.login.post');
    
    // Protected routes
    Route::middleware('teacher.auth')->group(function () {
        Route::get('/dashboard', [TeacherDashboardController::class, 'index'])
            ->name('teacher.dashboard');
        
        Route::get('/my-classes', [TeacherClassController::class, 'index'])
            ->name('teacher.classes');
        Route::get('/my-classes/{classId}/students', [TeacherClassController::class, 'students'])
            ->name('teacher.classes.students');
        
        // Marks Management
        Route::get('/marks/upload', [TeacherMarksController::class, 'index'])
            ->name('teacher.marks.index');
        Route::get('/marks/upload/{examId}', [TeacherMarksController::class, 'show'])
            ->name('teacher.marks.show');
        Route::post('/marks/save', [TeacherMarksController::class, 'store'])
            ->name('teacher.marks.store');
        
        // Homework
        Route::resource('homework', TeacherHomeworkController::class);
        
        // Notices (view only)
        Route::get('/notices', [TeacherNoticeController::class, 'index'])
            ->name('teacher.notices');
        
        // Profile & Password
        Route::get('/profile', [TeacherProfileController::class, 'show'])
            ->name('teacher.profile');
        Route::put('/profile', [TeacherProfileController::class, 'update'])
            ->name('teacher.profile.update');
        Route::get('/change-password', [TeacherProfileController::class, 'changePasswordForm'])
            ->name('teacher.password.change');
        Route::post('/change-password', [TeacherProfileController::class, 'changePassword'])
            ->name('teacher.password.update');
        
        // Exam Head Routes (conditional middleware)
        Route::middleware('exam.head')->group(function () {
            Route::get('/exam-head/marks', [ExamHeadController::class, 'index'])
                ->name('teacher.examhead.marks');
            Route::post('/exam-head/marks/{markId}/approve', [ExamHeadController::class, 'approve'])
                ->name('teacher.examhead.approve');
            Route::put('/exam-head/marks/{markId}/edit', [ExamHeadController::class, 'edit'])
                ->name('teacher.examhead.edit');
        });
        
        Route::post('/logout', [TeacherAuthController::class, 'logout'])
            ->name('teacher.logout');
    });
});
```

## Phase 4: Teacher Controllers

### 4.1 Teacher Dashboard Controller
**File:** `app/Http/Controllers/Teacher/TeacherDashboardController.php`

Show:
- Welcome message with teacher name
- Assigned classes and subjects
- Class teacher status
- Exam head status (if applicable)
- Quick actions
- Today's schedule (optional)

### 4.2 Teacher Class Controller
**File:** `app/Http/Controllers/Teacher/TeacherClassController.php`

- List assigned classes
- Show students in a class (filtered by teacher's assignments)

### 4.3 Teacher Marks Controller
**File:** `app/Http/Controllers/Teacher/TeacherMarksController.php`

Methods:
- `index()` - list available exams for teacher's subjects
- `show($examId)` - show marks entry form for specific exam
- `store()` - save/update marks (status: submitted)
- Validate: teacher can only upload marks for assigned subjects
- Marks saved to `results` table with `uploaded_by_teacher` field

### 4.4 Teacher Homework Controller
**File:** `app/Http/Controllers/Teacher/TeacherHomeworkController.php`

Use existing `HomeworkNotice` model:
- Create homework for assigned classes
- View/edit/delete own homework
- Filter by class

### 4.5 Teacher Notice Controller
**File:** `app/Http/Controllers/Teacher/TeacherNoticeController.php`

- View notices from admin (read-only)
- Use existing notification system

### 4.6 Teacher Profile Controller
**File:** `app/Http/Controllers/Teacher/TeacherProfileController.php`

- View profile
- Update basic info (name, phone, email, address)
- Change password (hashed with bcrypt)

### 4.7 Exam Head Controller
**File:** `app/Http/Controllers/Teacher/ExamHeadController.php`

- View all submitted marks across school
- Approve marks (change status to approved)
- Edit marks
- Publish results to admin

## Phase 5: Teacher Views

Create layout: `resources/views/layouts/teacher.blade.php`
- Similar to admin layout but separate
- Bootstrap-based, clean professional design
- Sidebar navigation for teacher features

### 5.1 Login Page
**File:** `resources/views/teacher/auth/login.blade.php`

- Clean design matching parent login style
- Fields: Mobile/Employee ID, Password
- Default password hint: 123456

### 5.2 Dashboard
**File:** `resources/views/teacher/dashboard.blade.php`

Sections:
- Welcome card
- Stats cards (assigned classes, subjects)
- Assigned classes list
- Class teacher badge if applicable
- Exam head access button if applicable
- Quick actions

### 5.3 My Classes
**File:** `resources/views/teacher/classes/index.blade.php`
**File:** `resources/views/teacher/classes/students.blade.php`

- List assigned classes with subject
- View students in each class

### 5.4 Marks Upload
**File:** `resources/views/teacher/marks/index.blade.php`
**File:** `resources/views/teacher/marks/upload.blade.php`

- Select exam from assigned subjects
- Table with student list and marks input
- Save/Submit buttons
- Show status (draft/submitted)

### 5.5 Homework
**File:** `resources/views/teacher/homework/index.blade.php`
**File:** `resources/views/teacher/homework/create.blade.php`
**File:** `resources/views/teacher/homework/edit.blade.php`

- List homework
- Create new (title, description, class, subject, due date)
- Edit/delete

### 5.6 Notices
**File:** `resources/views/teacher/notices/index.blade.php`

- List notices from admin

### 5.7 Profile & Change Password
**File:** `resources/views/teacher/profile/show.blade.php`
**File:** `resources/views/teacher/profile/edit.blade.php`
**File:** `resources/views/teacher/profile/change-password.blade.php`

- View/edit profile
- Change password form

### 5.8 Exam Head Views
**File:** `resources/views/teacher/exam-head/marks.blade.php`

- View all submitted marks
- Approve/edit interface
- Filter by class, subject, exam

## Phase 6: Admin Panel Features

### 6.1 Teacher Management Controller
**File:** `app/Http/Controllers/Admin/TeacherManagementController.php`

Methods:
- Create teacher login (generate password = 123456)
- Reset password to 123456
- Activate/deactivate teacher
- Assign/remove exam head

### 6.2 Teacher Assignment Controller
**File:** Update existing `TeacherClassAssignmentController.php` or create new

- Assign class, section, subject to teacher
- Mark as class teacher (toggle)
- View all assignments

### 6.3 Admin Views
**Files:**
- `resources/views/admin/teachers/manage-logins.blade.php`
- `resources/views/admin/teachers/assignments.blade.php`
- `resources/views/admin/teachers/exam-head.blade.php`

Features:
- Create/manage teacher accounts
- Assign classes/subjects
- Assign exam head
- Reset passwords
- Activate/deactivate

## Phase 7: Middleware for Exam Head

**File:** `app/Http/Middleware/ExamHeadAuth.php`

Check if authenticated teacher is exam head:
- Query `exam_heads` table
- Check status = active
- Allow or deny access

Register in `Kernel.php`:
```php
'exam.head' => \App\Http\Middleware\ExamHeadAuth::class,
```

## Phase 8: Update Results/Marks Tables

If using existing `results` table:

Add columns via migration:
- `uploaded_by_teacher_id` (foreign key to teachers)
- `uploaded_at` (timestamp)
- `status` (enum: draft, submitted, approved)
- `approved_by` (foreign key to users)
- `approved_at` (timestamp)

Update `Result` model with relationships.

## Phase 9: Seeder for Testing

**File:** `database/seeders/TeacherLoginSeeder.php`

Create sample data:
- Add password to 3-5 existing teachers
- Create sample assignments
- Create one exam head
- Mobile numbers for testing

## Phase 10: Security & Testing

### 10.1 Route Protection
- All teacher routes behind `teacher.auth` middleware
- Teachers cannot access `/admin/*` or `/parent/*`
- Verify with middleware checks

### 10.2 Authorization Policies
Create policies:
- `TeacherMarksPolicy` - can only upload marks for assigned subjects
- `ExamHeadPolicy` - can only access exam head features if assigned

### 10.3 Testing Checklist
- Teacher login with mobile number
- Teacher login with employee_id
- Password change
- Marks upload (only assigned subjects)
- Homework creation
- Exam head access control
- Verify admin/parent login still works
- Verify no cross-panel access

## Implementation Order

1. Run database migrations (Phase 1)
2. Update Teacher model to Authenticatable
3. Setup auth config and middleware (Phase 2)
4. Create routes (Phase 3)
5. Create controllers (Phase 4)
6. Create views (Phase 5)
7. Add admin features (Phase 6)
8. Create exam head middleware (Phase 7)
9. Update results handling (Phase 8)
10. Create seeder and test (Phase 9-10)

## Files to Create (Summary)

**Migrations:** 4 new migrations
**Controllers:** 8 new controllers in `app/Http/Controllers/Teacher/`
**Middleware:** 2 new middleware files
**Views:** 15+ new blade files in `resources/views/teacher/`
**Models:** Update Teacher model, add relationships
**Routes:** Add teacher routes group
**Admin:** Update admin views for teacher management

## Safety Checklist

- ✓ No modifications to existing parent/admin auth
- ✓ Separate guard for teachers
- ✓ Isolated routes with prefix
- ✓ Separate middleware
- ✓ No changes to existing fee/student modules
- ✓ Teacher features in separate namespace
- ✓ Database changes additive only (no drops)
