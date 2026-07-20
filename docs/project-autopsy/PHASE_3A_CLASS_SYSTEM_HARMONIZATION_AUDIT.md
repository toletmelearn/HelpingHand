# Phase 3A - Class / Section / Subject System Harmonization Audit

## 1. Files Inspected

### Models

- `app/Models/ClassManagement.php`
- `app/Models/SchoolClass.php`
- `app/Models/Section.php`
- `app/Models/Subject.php`
- `app/Models/Student.php`
- `app/Models/Teacher.php`
- `app/Models/TeacherClassSubjectAssignment.php`
- `app/Models/TeacherClassAssignment.php`
- `app/Models/ClassTeacherAssignment.php`
- `app/Models/Attendance.php`
- `app/Models/Result.php`
- `app/Models/Exam.php`
- `app/Models/ExamPaper.php`
- `app/Models/LessonPlan.php`
- `app/Models/BellTiming.php`
- `app/Models/FeeStructure.php`
- `app/Models/StudentFeeAssignment.php`

### Controllers / Services

- `app/Http/Controllers/Admin/ClassController.php`
- `app/Http/Controllers/Admin/SchoolClassController.php`
- `app/Http/Controllers/Admin/AdminStudentController.php`
- `app/Http/Controllers/Admin/StudentPromotionController.php`
- `app/Http/Controllers/Admin/TeacherSubjectAssignmentController.php`
- `app/Http/Controllers/Admin/TeacherClassSubjectAssignmentController.php`
- `app/Http/Controllers/Admin/TeacherClassAssignmentController.php`
- `app/Http/Controllers/Admin/ResultMonitorController.php`
- `app/Http/Controllers/Admin/FeeStructureController.php`
- `app/Http/Controllers/API/TeacherController.php`
- `app/Http/Controllers/SectionController.php`
- `app/Http/Controllers/SubjectController.php`
- `app/Http/Controllers/Teacher/TeacherClassController.php`
- `app/Http/Controllers/Teacher/TeacherMarksController.php`
- `app/Services/TeacherAcademicService.php`
- class/section/subject references across `app/Http/Controllers` and `app/Services`

### Routes / Views

- `routes/web.php`
- `routes/api.php`
- `resources/views/layouts/sidebar.blade.php`
- `resources/views/admin/classes`
- `resources/views/admin/sections`
- `resources/views/admin/subjects`
- `resources/views/admin/class-assignments`
- `resources/views/admin/class-teacher-control`
- `resources/views/admin/students`
- `resources/views/admin/teachers`
- `resources/views/admin/attendance`
- `resources/views/admin/exams`
- `resources/views/admin/results`
- `resources/views/admin/fee-structures`
- `resources/views/admin/lesson-plans`
- related admin/teacher/student/parent views matched by search

### Migrations

- `2026_01_17_070802_create_students_table.php`
- `2026_01_22_045805_create_class_management_table.php`
- `2026_01_22_072546_add_foreign_keys_to_students_table.php`
- `2026_01_23_090000_create_sections_table.php`
- `2026_01_23_091000_create_subjects_table.php`
- `2026_01_24_052805_create_school_classes_table.php`
- `2026_01_24_052834_create_class_subject_assignments_table.php`
- `2026_01_24_055848_create_class_sections_table.php`
- `2026_01_31_072706_create_classes_table.php`
- `2026_01_31_072740_add_class_id_to_students_table.php`
- `2026_01_31_073023_add_foreign_key_to_class_id_in_students_table.php`
- `2026_01_31_073352_populate_class_id_in_students_table.php`
- `2026_01_31_080000_add_school_class_id_to_students_table.php`
- `2026_01_31_080001_backfill_school_class_id_in_students_table.php`
- `2026_02_09_123709_fix_sections_table_remove_class_id.php`
- `2026_02_09_130259_fix_sections_table_make_class_id_nullable.php`
- `2026_02_12_100001_create_fee_structures_table.php`
- `2026_02_12_100003_create_student_fee_assignments_table.php`
- `2026_02_13_100001_create_teacher_class_subject_assignments_table.php`
- `2026_02_18_120158_create_student_attendance_system.php`
- relevant exam/result/lesson-plan/exam-paper migrations found by search

## 2. Commands Run

```powershell
Get-ChildItem app/Models | Where-Object { $_.Name -match 'Class|Section|Subject|Student|Teacher|Attendance|Result|ExamPaper|LessonPlan|BellTiming|FeeStructure|StudentFeeAssignment' } | Select-Object -ExpandProperty FullName
Get-ChildItem database/migrations | Where-Object { $_.Name -match 'classes|school_classes|class_management|sections|subjects|class_sections|class_subject|teacher_class_subject|students|attendances|exam_papers|lesson_plans|fee_structures|student_fee_assignments' } | Select-Object -ExpandProperty Name
rg -n "ClassManagement|SchoolClass|class_management|school_classes|\bclasses\b|class_id|school_class_id|class_section|section_id|subject_id|classSubjectAssignments|assignedClasses|classAssignments|subjectAssignments" app routes resources database tests -g "*.php" -g "*.blade.php" -g "*.js" -g "*.md"
php artisan route | Select-String "class"
php artisan route | Select-String "section"
php artisan route | Select-String "subject"
Get-Content app/Models/ClassManagement.php
Get-Content app/Models/SchoolClass.php
Get-Content app/Models/Section.php
Get-Content app/Models/Subject.php
Get-Content app/Models/TeacherClassSubjectAssignment.php
Get-Content app/Models/Student.php
Get-Content app/Models/Teacher.php
Get-Content app/Models/Attendance.php
Get-Content app/Models/Result.php
Get-Content app/Models/ExamPaper.php
Get-Content app/Models/LessonPlan.php
Get-Content app/Models/FeeStructure.php
Get-Content app/Models/StudentFeeAssignment.php
Get-Content app/Models/BellTiming.php
rg -n "use App\\Models\\(ClassManagement|SchoolClass|Section|Subject)|ClassManagement::|SchoolClass::|Section::|Subject::" app/Http/Controllers app/Services -g "*.php"
rg -n "Route::.*(classes|school-classes|sections|subjects|class-teacher|teacher-subject|assign-teacher|subject)" routes/web.php routes/api.php -g "*.php"
Get-ChildItem resources/views/admin -Directory | Where-Object { $_.Name -match 'class|student|teacher|attendance|exam|result|fee|lesson|subject|section' } | Select-Object -ExpandProperty FullName
rg -n "class_id|school_class_id|class_section|section_id|subject_id|school-classes|admin\.classes|admin\.school-classes|sections|subjects" resources/views/admin resources/views/layouts routes -g "*.blade.php" -g "*.php"
rg -n "class_id|school_class_id|class_section|class_name|section_id|subject_id" app/Http/Controllers app/Services -g "*.php"
Get-Content selected class/section/subject/student/fee/attendance migrations
Get-Content app/Http/Controllers/Admin/ClassController.php
Get-Content app/Http/Controllers/Admin/SchoolClassController.php
Get-Content app/Http/Controllers/Admin/AdminStudentController.php
Get-Content app/Http/Controllers/Admin/TeacherClassAssignmentController.php
Get-Content app/Http/Controllers/API/TeacherController.php
Get-Content app/Http/Controllers/Admin/ResultMonitorController.php
Get-Content app/Services/TeacherAcademicService.php
Get-Content app/Models/Exam.php
Get-Content app/Models/TeacherClassAssignment.php
Get-Content app/Models/ClassTeacherAssignment.php
Get-Content app/Http/Controllers/Admin/FeeStructureController.php
Get-Content app/Http/Controllers/Admin/StudentPromotionController.php
```

Notes:

- `php artisan migrate` was explicitly not run because this phase forbids migrations/database-changing commands.
- The requested `php artisan route | Select-String ...` form is not valid in this Laravel app. Laravel listed valid `route:*` commands and no state was changed.
- No optional `tinker` schema checks were run to avoid touching the real/local database connection.

## 3. Class / Section / Subject Table Map

| Table | Model | Primary Purpose Today | Important Columns / Keys | Relationships / Usage | Risk |
| --- | --- | --- | --- | --- | --- |
| `school_classes` | `SchoolClass` | Strongest canonical candidate for grade/class records. Used by many newer flows. | `id`, `name`, `section_id`, `academic_session_id`, `teacher_id`, `class_order`, `is_active`, soft deletes | Used by `Student::schoolClass()`, `LessonPlan`, `ExamPaper`, `TeacherClassSubjectAssignment`, homework, fees, promotions, API teacher data. | Medium: model missing declared relationships used by controller; naming assumptions vary. |
| `class_management` | `ClassManagement` | Legacy admin class management and old class/teacher assignment surface. | `id`, `name`, `section`, `stream`, `capacity`, `description`, `is_active`, `order` added later | Used by `Admin\ClassController`, `ClassManagementController`, legacy `TeacherClassAssignment`, class assignment views. | High: students relation uses `class_name` field that is not in current `Student` fillable/schema contract. |
| `classes` | No clear active model found | Stray/parallel class table. Used directly by SQL in result monitor. | `id`, `name`, `class_order`, `description`, `is_active` | `Admin\ResultMonitorController` joins `classes.id = teacher_class_subject_assignments.class_id`. | Critical: teacher assignments actually constrain to `school_classes`, so joins to `classes` can produce wrong or missing data. |
| `sections` | `Section` | Canonical section table candidate, but relationship contract drifted. | `id`, `name`, `capacity`, `description`, originally `class_id`, later removed/nullable, soft deletes later | Used by assignments, lesson plans, reports, substitutions, student forms. | High: `Section::classes()` says hasMany `SchoolClass`, while migrations alternately tie sections to `class_management`, remove `class_id`, and `school_classes` has its own `section_id`. |
| `subjects` | `Subject` | Canonical subject table candidate. | `id`, `name`, `code`, old `type/max_marks/pass_marks`, later `subject_type/is_active/sort_order` | Used by teacher assignments, lesson plans, results, substitutions, homework, exams. | Medium: some controllers query `status`, but model/migrations show `is_active` and `subject_type`. |
| `teacher_class_subject_assignments` | `TeacherClassSubjectAssignment` | Best canonical teacher-to-class-section-subject mapping. | `teacher_id`, `class_id -> school_classes`, `section_id -> sections`, `subject_id -> subjects`, `academic_year`, flags | Used by teacher dashboard/classes, API teacher routes, teacher homework/exam access, teacher academic service. | Medium: schema lacks `is_primary_subject_teacher` in inspected migration but model/service use it; report-only risk. |
| `class_sections` | No direct model; used by `ClassManagement::sections()` | Legacy pivot from `class_management` to `sections`. | `class_management_id`, `section_id`, `assigned_at` | Used by admin class assignment views through `ClassManagement`. | High: conflicts with newer section usage and `school_classes.section_id`. |
| `class_subject_assignments` | No direct model found | Legacy/duplicate class-subject-teacher pivot. | `class_id -> school_classes`, `subject_id`, `teacher_id` | Used by `ClassManagement::subjects()` as if `class_id` points to `class_management`, but migration points to `school_classes`. | Critical: model relationship and migration disagree about what `class_id` references. |

## 4. Model Map

| Model | Current Class Contract | Status |
| --- | --- | --- |
| `Student` | Has string `class`, string `section`, integer `class_id`, relation `schoolClass()` and `class()` both use `class_id -> SchoolClass`; `section()` uses `section_id`. `school_class_id` exists in migrations but is not fillable/cast here. | Partially canonical; still dual string/id model. |
| `SchoolClass` | `students()` uses `class_id`; scopes `active()` and `orderByOrder()`. | Canonical candidate; missing `section()`, `academicSession()`, and `teacher()` methods despite `SchoolClassController` using them. |
| `ClassManagement` | Table `class_management`; `students()` uses `class_name -> name`; `sections()` through `class_sections`; `subjects()` through `class_subject_assignments`. | Legacy and internally inconsistent. |
| `Section` | Fillable has only `name`, `capacity`, `description`; `classes()` hasMany `SchoolClass`. | Canonical candidate but needs relationship repair. |
| `Subject` | Has `name`, `code`, `subject_type`, `is_active`, `sort_order`; result relation points to `CBSEResult`. | Canonical candidate; controllers must stop querying nonexistent `status`. |
| `Teacher` | Legacy `classes()` uses `ClassManagement`; newer `classSubjectAssignments()`, `assignedClasses()`, `assignedSubjects()` use `teacher_class_subject_assignments`. | Split but repairable; newer mapping should win. |
| `LessonPlan` | `class_id -> SchoolClass`, `section_id -> Section`, `subject_id -> Subject`. | Good canonical alignment. |
| `ExamPaper` | Has both `class_section` string and `class_id -> SchoolClass`. | Mixed; public/search routes still string-oriented. |
| `Exam` | Has `class_id`, `subject_id`, `class_name`, `subject`; `schoolClass()` uses string `class_name -> SchoolClass.name`. | Mixed and fragile. |
| `Result` | Has relationship `schoolClass()` via `class_id`, but `class_id`/`subject_id` missing from fillable. | Partial canonical alignment; write paths may skip new IDs. |
| `Attendance` | Uses string `class` and `subject`; no `class_id` relationship. Separate `student_attendance` migration exists with `class_id`. | Split attendance systems. |
| `BellTiming` | Uses string `class_section`. | String-based, acceptable short term as timetable display, but not normalized. |
| `FeeStructure` | Uses string `class_name`; controller maps to `SchoolClass::where('name', ...)` for assignment. | Active string bridge; canonical fees still not class-id based. |
| `StudentFeeAssignment` | Links student to fee structure only, no direct class. | Safe if student/fee structure contracts are repaired. |

## 5. Controller Usage Map

| Area | Uses `SchoolClass` | Uses `ClassManagement` | Uses `classes` table | Uses strings | Notes |
| --- | --- | --- | --- | --- | --- |
| Admin class CRUD | No | Yes | No | Some section/name fields | `admin.classes.*` manages `class_management`. |
| Admin school-class CRUD | Yes | No | No | No | Route exists, but views folder was not listed in admin view directory output; controller eager-loads missing relationships. |
| Admin students | Yes via `class_id` | No | No | Yes via `class`/`section` create/update/list | Index filters by `class_id`, create/update validate string `class`. |
| Promotions | Mostly yes | Legacy index loads `ClassManagement` | No | Updates string `class` along with `class_id` | Comments state `SchoolClass` is the real data source. |
| Teacher academic service | Yes | No | No | No | Strong canonical candidate service. |
| Teacher class/subject assignment | Yes | Legacy separate assignment uses `ClassManagement` | No | No | `TeacherClassSubjectAssignment` is stronger than `TeacherClassAssignment`. |
| Result monitor | No | No | Yes | No | Joins `classes`, likely wrong if assignment `class_id` references `school_classes`. |
| Exams | Mixed | Admin exam controller uses `ClassManagement` | No | Yes `class_name`/`subject` | Teacher exam code maps IDs to names. |
| Exam papers | Yes in teacher flow | No | No | Yes `class_section` | Admin API/web exam-paper flows still string-based. |
| Lesson plans/homework | Yes | No | No | Some display names | Mostly canonical. |
| Attendance | Yes in service/teacher attendance | No | No | Core `Attendance` model uses string class | `student_attendance` migration is separate normalized path. |
| Fees | Uses `SchoolClass` to populate dropdowns | No | No | `FeeStructure.class_name` active | Needs class-id bridge later. |

## 6. View / Route Usage Map

| Surface | Route / View Evidence | Class System |
| --- | --- | --- |
| Sidebar | `admin.classes.index`, `admin.sections.index`, `admin.subjects.index` | Shows legacy `admin.classes` for `ClassManagement`; no visible `school-classes` sidebar entry found in inspected sidebar lines. |
| Admin class CRUD | `Route::resource('classes', Admin\ClassController::class)` appears more than once | `ClassManagement`. Duplicate route registration risk. |
| Admin school-class CRUD | `Route::resource('school-classes', Admin\SchoolClassController::class)` | `SchoolClass`, but less visible in navigation. |
| Admin sections/subjects | `Route::resource('sections')`, `Route::resource('subjects')` | Canonical-looking, but model/migration drift. |
| Teacher class routes | `/my-classes`, `/my-classes/{classId}/students` | Newer `teacher_class_subject_assignments` + `school_classes`. |
| API teacher routes | `/api/v1/teachers/{id}/classes`, `/subject-classes` | Mixed: `classes()` still returns legacy `Teacher::classes`; subjectClasses uses canonical assignment. |
| Admin fee structures | `resources/views/admin/fee-structures/*` selects `$class->name` into `class_name` | `SchoolClass` dropdown but string storage. |
| Exam-paper views | `class_section` inputs/text | String-based. |
| Lesson-plan views | `class_id`, `section_id`, `subject_id` selects | Mostly `SchoolClass`/`Section`/`Subject`. |

## 7. Usage Classification

| Usage Pattern | Classification | Examples |
| --- | --- | --- |
| `TeacherClassSubjectAssignment.class_id -> school_classes.id` | Active canonical usage | teacher academic service, teacher dashboard/classes, lesson/homework ownership, API teacher relationship fixes. |
| `Student.class_id -> school_classes.id` | Active canonical candidate | admin student index, promotions, fee auto-assign, many reports. |
| `Student.school_class_id` | Duplicate/transition usage | parent homework/lesson flows, teacher marks, installment fees. Not represented in `Student::$fillable`. |
| `Student.class` / `Student.section` | Legacy string usage | create/update, stats, fee collection, exams/admit cards. |
| `ClassManagement` / `class_management` | Legacy usage | admin classes, class-teacher assignment, old section assignment. |
| `classes` table | Likely broken usage | result monitor direct joins. |
| `class_section` | String-based operational usage | bell timings, exam papers, exam-paper templates. |
| `class_name` | Legacy bridge usage | exams, fee structures, syllabi, daily teaching work. |
| `class_subject_assignments` | Duplicate/broken pivot | migration references `school_classes`, model relationship in `ClassManagement` treats it as `class_management`. |

## 8. Domain Impact Map

| Domain | Current Class System |
| --- | --- |
| Students | Mixed: string `class`, string `section`, `class_id`, migration-added `school_class_id`; `schoolClass()` uses `class_id`. |
| Teachers | Mixed: old `Teacher::classes()` via `ClassManagement`; newer `classSubjectAssignments()` and `assignedClasses()` via `SchoolClass`. |
| Sections | Standalone `sections` plus old `class_id` migration history plus `class_sections` pivot plus `school_classes.section_id`. |
| Subjects | Mostly canonical `subjects`, but column contract drift (`type` vs `subject_type`, `is_active`; controllers use `status` in places). |
| Attendance | Core `Attendance` model string `class`; service/teacher attendance often use `class_id`; separate `student_attendance` migration uses `school_classes`. |
| Exams | Mixed ID/name: `Exam` has `class_id`/`subject_id` but relationship uses `class_name`/`subject`; admin exam uses `ClassManagement`; teacher exam maps IDs to names. |
| Results | Partial `class_id`/`subject_id`; result monitor joins `classes`; reports often read `student.class_name` which is not the `Student` model's main field. |
| Exam Papers | `class_id` plus `class_section`; admin/public views mostly use `class_section`, teacher flows use `class_id`. |
| Lesson Plans | Good alignment to `class_id -> SchoolClass`, `section_id`, `subject_id`. |
| Bell Timing | String `class_section`; acceptable as short-term display key but not normalized. |
| Fees | `FeeStructure.class_name`, with `SchoolClass` dropdowns and name-to-id bridge for student assignment. |
| Reports / Promotions | Promotions mostly use `SchoolClass`; reports are mixed (`class_id`, `class_name`, direct `classes` table joins). |

## 9. Relationship Truth Table

| Relationship | Current Status | Verdict |
| --- | --- | --- |
| `Student -> Class` | `Student::schoolClass()` and `Student::class()` both use `class_id -> SchoolClass`; `school_class_id` also exists in migrations and some controllers. | Partially works; choose one FK. |
| `Student -> Section` | `Student::section()` uses `section_id`, but create/update still use string `section`; original student migration has no `section_id`. | Partially works; needs field contract check. |
| `Teacher -> Classes` | `Teacher::classes()` legacy `ClassManagement`; `assignedClasses()` canonical `SchoolClass`. | Mixed; deprecate `classes()` or rename legacy explicitly. |
| `Teacher -> Subjects` | `assignedSubjects()` via canonical pivot; old subject specialization strings still exist. | Mostly works with newer pivot. |
| `Teacher -> Sections` | Available through `TeacherClassSubjectAssignment.section`. | Works where pivot exists. |
| `LessonPlan -> Class/Section/Subject` | Belongs to `SchoolClass`, `Section`, `Subject`. | Currently the cleanest model contract. |
| `ExamPaper -> Class/Subject` | `class_id -> SchoolClass`; `subject` string; `class_section` string. | Partially works; class is mixed, subject still string. |
| `Attendance -> Student/Class/Teacher` | `Attendance` belongs to student/teacher but stores class string; newer `student_attendance` migration has class_id. | Split system; needs consolidation. |
| `FeeStructure -> Class/Section` | String `class_name`; no section relation. | Legacy bridge; needs class_id later. |
| `Result -> Student/Class/Exam` | `student`, `exam`, `schoolClass(class_id)`, but fillable/write paths are inconsistent. | Partially works; relationship should be preserved after class_id audit. |

## 10. Canonical Structure Recommendation

Choose **Option A**, with one clarification:

Canonical future structure should be:

- `school_classes` as the canonical class/grade table.
- `sections` as the canonical section table.
- `subjects` as the canonical subject table.
- `teacher_class_subject_assignments` as the canonical teacher-class-section-subject mapping.
- `students.class_id` as the canonical student class FK, after verifying/backfilling that it points to `school_classes.id`.

Recommended canonical relationship shape:

- `SchoolClass hasMany Student` through `students.class_id`.
- `Student belongsTo SchoolClass` through `class_id`.
- `Student belongsTo Section` through `section_id`.
- `Teacher hasMany TeacherClassSubjectAssignment`.
- `Teacher belongsToMany SchoolClass` through `teacher_class_subject_assignments`.
- `Teacher belongsToMany Subject` through `teacher_class_subject_assignments`.
- `LessonPlan belongsTo SchoolClass`, `Section`, `Subject`.
- `ExamPaper belongsTo SchoolClass` and eventually `Subject`.
- `Result belongsTo SchoolClass`, `Subject`, `Student`, `Exam`.
- `FeeStructure` should eventually reference `school_class_id` or `class_id`, not only `class_name`.

Do not immediately delete or migrate data. The current code is too mixed.

## 11. Legacy / Deprecated Structure Recommendation

Mark these as legacy/deprecated for new development:

- `classes` table.
- `class_management` table and `ClassManagement` model.
- `class_sections` pivot.
- `class_subject_assignments` pivot, unless repaired to be explicitly canonical.
- `TeacherClassAssignment` model and controller paths using `class_management`.
- `ClassTeacherAssignment.assigned_class` string system.
- Student string `class` and string `section` as source-of-truth fields.
- `school_class_id` on `students`, if the project commits to `class_id -> school_classes.id`.

Keep these temporarily as compatibility fields until mapping is proven:

- `students.class`
- `students.section`
- `fee_structures.class_name`
- `exams.class_name`
- `exam_papers.class_section`
- `bell_timings.class_section`
- syllabi/daily-teaching-work `class_name`

## 12. Migration / Fresh Install Risk

Fresh install is risky.

Key migration risks:

1. `sections` is created with `class_id -> class_management`, then later `class_id` is dropped, then later another migration tries to make `class_id` nullable. If the column was dropped, the nullable migration can fail.
2. `students.class_id` foreign key to `classes` was intentionally skipped, but many current code paths treat `class_id` as `school_classes.id`.
3. `students.school_class_id` was added and backfilled, while most newer models use `students.class_id`.
4. `classes` table exists independently, but newer assignments use `school_classes`; direct joins to `classes` are probably wrong.
5. `class_subject_assignments` migration points `class_id` to `school_classes`, while `ClassManagement::subjects()` treats `class_id` as `class_management`.
6. `SchoolClassController` assumes relationships and columns that are not defined in the model.
7. `Subject` schema evolved from `type/max_marks/pass_marks` to `subject_type/is_active/sort_order`; controllers query `status` in at least professional homework/lesson code.
8. `FeeStructure` exists in both earlier and canonical fee migrations; class contract remains string-based.
9. Full database migration state has already been documented as inconsistent in earlier phases; do not run fresh migrations until this class contract is repaired.

Required mapping before deprecation:

- Map each `class_management.name` to `school_classes.name`.
- Map each `classes.name` to `school_classes.name`.
- Decide whether `students.class_id` or `students.school_class_id` is final; recommended final is `students.class_id`.
- Backfill `students.section_id` from string `section`, if the column exists.
- Map `fee_structures.class_name`, `exams.class_name`, `syllabi.class_name`, `daily_teaching_works.class_name`, and `bell_timings.class_section` to canonical class/section identifiers or compatibility views.

## 13. Top 20 Class-System Risks

1. Three active class tables exist: `school_classes`, `class_management`, and `classes`.
2. `students.class_id` and `students.school_class_id` both exist in migration history and are both used by controllers.
3. Student create/update still validates string `class` and string `section`, so new records may not receive canonical FKs.
4. `SchoolClassController` eager-loads `section`, `academicSession`, and `teacher`, but `SchoolClass` model does not define those relationships.
5. `Section` migration history drops `class_id`, then later tries to alter it.
6. `Section::classes()` is vague and likely wrong for canonical use.
7. `ClassManagement::students()` points to `students.class_name`, not `students.class`.
8. `ClassManagement::subjects()` conflicts with `class_subject_assignments` migration target.
9. `Teacher::classes()` returns legacy `ClassManagement`, while `Teacher::assignedClasses()` returns `SchoolClass`.
10. API `TeacherController::classes()` still uses the legacy `classes` relationship.
11. `ResultMonitorController` joins `classes`, while teacher assignments point to `school_classes`.
12. `TeacherClassAssignmentController` validates `exists:class_managements,id`, but table name is `class_management`.
13. `FeeStructure` stores `class_name` only and auto-assigns by matching class names.
14. `FeeCollectionController` still looks up fee structures by `$student->class`.
15. `Exam` stores both IDs and names, but relationships use names.
16. `ExamPaper` stores both `class_id` and string `class_section`.
17. `Attendance` model uses string `class`; newer attendance migration uses `student_attendance.class_id`.
18. Many views display `student->class_name` or `schoolClass->class_name`, but `SchoolClass` model uses `name`.
19. Professional controllers query `Subject::where('status', 'active')` although model uses `is_active`.
20. Route registration includes duplicate `admin.classes` blocks, raising route/helper ambiguity.

## 14. Safe Phase 3B Plan

Phase 3B should not merge tables yet.

Recommended first task:

1. Create a read-only compatibility/mapping report or diagnostic command that compares:
   - `school_classes`
   - `class_management`
   - `classes`
   - distinct `students.class`
   - distinct `students.class_id`
   - distinct `students.school_class_id`
   - distinct `fee_structures.class_name`
   - distinct `exams.class_name`
   - distinct `bell_timings.class_section`
2. Do not write data.
3. Confirm whether `students.class_id` already points to `school_classes.id` in the actual database.
4. Add missing model relationships on `SchoolClass` in a later code phase only after schema verification.
5. Update one low-risk domain first, likely admin student create/update, to populate `class_id` and `section_id` while preserving string compatibility fields.
6. Then update API/teacher routes to consistently use `assignedClasses()` / `TeacherClassSubjectAssignment`.
7. Leave `class_management` routes active but label them legacy until all references are migrated.
8. Add isolated tests around model relationships before touching migrations.
9. Only plan data migration after the mapping report proves one-to-one class name/id mapping.

## 15. No Code / Database Modification Confirmation

- No application code was modified.
- No routes were modified.
- No models were modified.
- No migrations were modified.
- No tests were run.
- `php artisan migrate` was not run.
- No database-changing command was run.
- Real/local MySQL data was not touched.
- Only this report file was created.
