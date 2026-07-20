# Phase 3B - Class Data Compatibility Map

Date: 2026-06-04  
Project: HelpingHand  
Mode: Read-only audit with report creation only

## Files Inspected

- `docs/project-autopsy/PHASE_3A_CLASS_SYSTEM_HARMONIZATION_AUDIT.md`
- `app/Models/SchoolClass.php`
- `app/Models/ClassManagement.php`
- `app/Models/Student.php`
- `app/Models/Section.php`
- `app/Models/Subject.php`
- `app/Models/TeacherClassSubjectAssignment.php`
- `app/Models/FeeStructure.php`
- `app/Models/Exam.php`
- `app/Models/ExamPaper.php`
- `app/Models/BellTiming.php`
- `database/migrations/*school_classes*.php`
- `database/migrations/*class_management*.php`
- `database/migrations/*classes*.php`
- `database/migrations/*students*.php`
- `database/migrations/*sections*.php`
- `database/migrations/*subjects*.php`
- `database/migrations/*teacher_class_subject_assignments*.php`
- `database/migrations/*fee_structures*.php`
- `database/migrations/*exams*.php`
- `database/migrations/*exam_papers*.php`
- `database/migrations/*bell_timings*.php`
- Route and usage references in `routes`, `app`, `resources`, and `database`

## Commands Run

Read-only file and search commands:

```powershell
Get-Content docs/project-autopsy/PHASE_3A_CLASS_SYSTEM_HARMONIZATION_AUDIT.md
Get-Content app/Models/SchoolClass.php
Get-Content app/Models/ClassManagement.php
Get-Content app/Models/Student.php
Get-Content app/Models/Section.php
Get-Content app/Models/Subject.php
Get-Content app/Models/TeacherClassSubjectAssignment.php
Get-Content app/Models/FeeStructure.php
Get-Content app/Models/Exam.php
Get-Content app/Models/ExamPaper.php
Get-Content app/Models/BellTiming.php
rg -n "Schema::create\('(school_classes|class_management|classes|students|sections|subjects|teacher_class_subject_assignments|fee_structures|exams|exam_papers|bell_timings)'|class_id|school_class_id|class_section|class_name|section_id|subject_id" database/migrations -g "*.php"
rg -n "ClassManagement|SchoolClass|class_management|school_classes|class_id|school_class_id|class_section|section_id|subject_id|classSubjectAssignments|assignedClasses|classAssignments|subjectAssignments" app routes resources database -g "*.php" -g "*.blade.php"
php artisan route | Select-String "class"
php artisan route | Select-String "section"
php artisan route | Select-String "subject"
php artisan route:list | Select-String "class"
php artisan route:list | Select-String "section"
php artisan route:list | Select-String "subject"
```

Notes:

- `php artisan route` is not valid in this Laravel 12 project and returned the Artisan route namespace help only. The real read-only route inspection used `php artisan route:list`.
- `php artisan migrate` was intentionally not run because it conflicts with the phase rule forbidding migrations and database-changing commands.

Read-only database inspection commands:

```powershell
php artisan tinker --% --execute="$tables=['school_classes','class_management','classes','sections','subjects','teacher_class_subject_assignments','class_sections','class_subject_assignments','students','fee_structures','exams','exam_papers','bell_timings']; foreach($tables as $t){ dump([$t=>['exists'=>Schema::hasTable($t),'columns'=>Schema::hasTable($t)?Schema::getColumnListing($t):[]]]); }"
php artisan tinker --% --execute="$q=function($label,$table,$cols){ if(!Schema::hasTable($table)){ dump([$label=>'missing']); return; } $rows=DB::table($table)->select($cols)->limit(100)->get(); dump([$label=>$rows]); }; $q('school_classes','school_classes',['id','name','section_id','class_order','is_active']); $q('class_management','class_management',['id','name','section','is_active']); $q('classes','classes',['id','name','class_order','is_active']); $q('sections','sections',['id','name','class_id','is_active']); $q('subjects','subjects',['id','name','code','is_active']);"
php artisan tinker --% --execute="$dump=function($label,$value){ dump([$label=>$value]); }; $dump('counts',[ 'school_classes'=>DB::table('school_classes')->count(), 'class_management'=>DB::table('class_management')->count(), 'classes'=>DB::table('classes')->count(), 'sections'=>DB::table('sections')->count(), 'subjects'=>DB::table('subjects')->count(), 'teacher_assignments'=>DB::table('teacher_class_subject_assignments')->count(), 'students'=>DB::table('students')->count(), 'fee_structures'=>DB::table('fee_structures')->count(), 'exams'=>DB::table('exams')->count(), 'exam_papers'=>DB::table('exam_papers')->count(), 'bell_timings'=>DB::table('bell_timings')->count() ]); $dump('distinct_students_class',DB::table('students')->select('class')->whereNotNull('class')->distinct()->orderBy('class')->pluck('class')); $dump('distinct_students_class_id',DB::table('students')->whereNotNull('class_id')->distinct()->orderBy('class_id')->pluck('class_id')); $dump('distinct_students_school_class_id',DB::table('students')->whereNotNull('school_class_id')->distinct()->orderBy('school_class_id')->pluck('school_class_id')); $dump('distinct_students_section',DB::table('students')->select('section')->whereNotNull('section')->distinct()->orderBy('section')->pluck('section')); $dump('distinct_students_section_id',DB::table('students')->whereNotNull('section_id')->distinct()->orderBy('section_id')->pluck('section_id')); $dump('distinct_fee_class_name',DB::table('fee_structures')->select('class_name')->whereNotNull('class_name')->distinct()->orderBy('class_name')->pluck('class_name')); $dump('distinct_exam_class_name',DB::table('exams')->select('class_name')->whereNotNull('class_name')->distinct()->orderBy('class_name')->pluck('class_name')); $dump('distinct_exam_paper_class_section',DB::table('exam_papers')->select('class_section')->whereNotNull('class_section')->distinct()->orderBy('class_section')->pluck('class_section')); $dump('distinct_bell_class_section',DB::table('bell_timings')->select('class_section')->whereNotNull('class_section')->distinct()->orderBy('class_section')->pluck('class_section'));"
php artisan tinker --% --execute="$sc=DB::table('school_classes')->pluck('id')->all(); $sec=DB::table('sections')->pluck('id')->all(); $sub=DB::table('subjects')->pluck('id')->all(); dump(['orphan_counts'=>[ 'students_class_id_not_in_school_classes'=>DB::table('students')->whereNotNull('class_id')->whereNotIn('class_id',$sc)->count(), 'students_school_class_id_not_in_school_classes'=>DB::table('students')->whereNotNull('school_class_id')->whereNotIn('school_class_id',$sc)->count(), 'students_class_id_ne_school_class_id'=>DB::table('students')->whereNotNull('class_id')->whereNotNull('school_class_id')->whereColumn('class_id','!=','school_class_id')->count(), 'students_null_class_id'=>DB::table('students')->whereNull('class_id')->count(), 'students_null_school_class_id'=>DB::table('students')->whereNull('school_class_id')->count(), 'students_string_class_no_class_id'=>DB::table('students')->whereNotNull('class')->whereNull('class_id')->count(), 'students_null_section_id'=>DB::table('students')->whereNull('section_id')->count(), 'teacher_assign_invalid_class'=>DB::table('teacher_class_subject_assignments')->whereNotNull('class_id')->whereNotIn('class_id',$sc)->count(), 'teacher_assign_invalid_section'=>DB::table('teacher_class_subject_assignments')->whereNotNull('section_id')->whereNotIn('section_id',$sec)->count(), 'teacher_assign_invalid_subject'=>DB::table('teacher_class_subject_assignments')->whereNotNull('subject_id')->whereNotIn('subject_id',$sub)->count() ]]);"
php artisan tinker --% --execute="$school=DB::table('school_classes')->pluck('name')->map(fn($v)=>trim($v))->values(); $schoolNorm=$school->map(fn($v)=>strtolower($v))->all(); $knownClassSections=[]; foreach($school as $c){ $knownClassSections[]=strtolower($c); foreach(DB::table('sections')->pluck('name') as $s){ $knownClassSections[]=strtolower(trim($c.' '.$s)); $knownClassSections[]=strtolower(trim($c.'-'.$s)); } } $mismatch=function($table,$col,$known) { if(!Schema::hasTable($table) || !Schema::hasColumn($table,$col)) return null; return DB::table($table)->whereNotNull($col)->whereNotIn(DB::raw('LOWER(TRIM('.$col.'))'),$known)->count(); }; dump(['string_mismatch_counts'=>[ 'fee_structures_class_name_not_school_classes'=>$mismatch('fee_structures','class_name',$schoolNorm), 'exams_class_name_not_school_classes'=>$mismatch('exams','class_name',$schoolNorm), 'exam_papers_class_section_not_known_patterns'=>$mismatch('exam_papers','class_section',$knownClassSections), 'bell_timings_class_section_not_known_patterns'=>$mismatch('bell_timings','class_section',$knownClassSections) ]]); dump(['student_fk_disagreements'=>DB::table('students')->select('id','name','class','class_id','school_class_id','section','section_id')->whereColumn('class_id','!=','school_class_id')->get()]);"
php artisan tinker --% --execute="$schoolNames=DB::table('school_classes')->pluck('name')->map(fn($v)=>trim($v))->all(); $normalize=function($v){return strtolower(trim((string)$v));}; $schoolNorm=collect($schoolNames)->map($normalize)->all(); $check=function($label,$values) use ($schoolNames,$schoolNorm,$normalize){ $vals=collect($values)->map(fn($v)=>trim((string)$v))->filter(fn($v)=>$v!=='')->unique()->values(); $exact=$vals->filter(fn($v)=>in_array($v,$schoolNames,true))->values(); $case=$vals->filter(fn($v)=>!in_array($v,$schoolNames,true)&&in_array($normalize($v),$schoolNorm,true))->values(); $missing=$vals->filter(fn($v)=>!in_array($normalize($v),$schoolNorm,true))->values(); dump([$label=>['count'=>$vals->count(),'exact_count'=>$exact->count(),'case_only_count'=>$case->count(),'missing_count'=>$missing->count(),'missing'=>$missing]]); }; $check('class_management.name',DB::table('class_management')->pluck('name')); $check('class_management.combined_name',DB::table('class_management')->get()->map(fn($r)=>trim($r->name.($r->section ? ' '.$r->section : '')))); $check('classes.name',DB::table('classes')->pluck('name')); $check('students.class',DB::table('students')->whereNotNull('class')->pluck('class')); $check('fee_structures.class_name',DB::table('fee_structures')->whereNotNull('class_name')->pluck('class_name')); $check('exams.class_name',DB::table('exams')->whereNotNull('class_name')->pluck('class_name'));"
php artisan tinker --% --execute="dump(['students_sample'=>DB::table('students')->select('id','name','class','section','class_id','school_class_id','section_id')->limit(20)->get()]); dump(['teacher_assignments_sample'=>DB::table('teacher_class_subject_assignments')->select('id','teacher_id','class_id','section_id','subject_id','academic_year')->limit(20)->get()]);"
```

No insert, update, delete, truncate, seed, migration, or schema mutation command was run.

## Tables / Columns Existence Map

| Table | Exists | Key columns found | Compatibility note |
|---|---:|---|---|
| `school_classes` | Yes | `id`, `name`, `class_order`, `is_active`, `section_id`, `academic_session_id`, `teacher_id`, `description`, timestamps, `deleted_at` | Best canonical class table. Contains 19 active rows. `section_id` exists but all inspected rows have `section_id = null`. |
| `class_management` | Yes | `id`, `name`, `order`, `section`, `stream`, `capacity`, `description`, `is_active`, timestamps | Legacy class admin table. Also contains 19 rows, but senior classes split `Class 11/12` plus `section` stream. |
| `classes` | Yes | `id`, `name`, `class_order`, `description`, `is_active`, timestamps | Empty table. Existing code still references it in result-monitor style joins, so it is a runtime risk even with no data. |
| `sections` | Yes | `id`, `name`, `description`, `capacity`, `class_id`, `is_active`, timestamps, `deleted_at` | Contains A-D. `class_id` exists but current rows have `class_id = null`. Treated as global section catalog. |
| `subjects` | Yes | `id`, `name`, `code`, `description`, `max_marks`, `pass_marks`, `type`, `is_active`, timestamps, `deleted_at` | Contains 16 active subjects. Model fillable still expects `subject_type` and `sort_order`, which do not match current DB columns. |
| `teacher_class_subject_assignments` | Yes | `id`, `teacher_id`, `school_id`, `class_id`, `section_id`, `subject_id`, flags, `academic_year`, timestamps | Canonical assignment table is present. Current assignment FK values map to canonical tables. Many section IDs are null. |
| `class_sections` | Yes | `id`, `class_management_id`, `section_id`, `assigned_at`, timestamps | Legacy bridge from `class_management` to `sections`. |
| `class_subject_assignments` | Yes | `id`, `class_id`, `subject_id`, `teacher_id`, `assigned_at`, timestamps | Legacy/non-canonical subject assignment bridge. Migration points to `school_classes`, but name conflicts with newer canonical assignment direction. |
| `students` | Yes | `id`, `user_id`, `guardian_id`, names, `class`, `class_id`, `school_class_id`, `section_id`, `section`, `roll_number`, timestamps, `deleted_at`, `is_verified` | Both `class_id` and `school_class_id` are populated for all 760 students, but one row disagrees. |
| `fee_structures` | Yes | `id`, `name`, `class_name`, fee fields, `academic_year`, `status`, `created_by`, timestamps, `deleted_at` | Still string keyed by `class_name`; current distinct value maps to `school_classes.name`. |
| `exams` | Yes | `id`, `name`, `exam_type`, `class_name`, `subject`, exam timing/marks fields, `status`, `created_by`, timestamps | Current DB lacks `class_id` and `subject_id` despite model fillable and later migration files referencing them. |
| `exam_papers` | Yes | `id`, `title`, `subject`, `paper_content`, `class_section`, publication/status fields, `exam_id`, `class_id`, `questions_data`, timestamps | Mixed model: has `class_section` string and `class_id`. Current distinct non-null `class_section` set is empty. |
| `bell_timings` | Yes | `id`, `day_of_week`, `period_name`, `start_time`, `end_time`, `class_section`, `is_active`, `is_break`, `order_index`, `academic_year`, `semester`, labels/colors, audit fields, timestamps | String keyed by `class_section`; current distinct value `Class 5` maps to a known school class name. |

## Class Value Map

### Row Counts

| Table | Count |
|---|---:|
| `school_classes` | 19 |
| `class_management` | 19 |
| `classes` | 0 |
| `sections` | 4 |
| `subjects` | 16 |
| `teacher_class_subject_assignments` | 23 |
| `students` | 760 |
| `fee_structures` | 1 |
| `exams` | 1 |
| `exam_papers` | 1 |
| `bell_timings` | 11 |

### Canonical School Classes

`school_classes` contains 19 active classes:

- Nursery
- LKG
- UKG
- Class 1 through Class 10
- Class 11 Science
- Class 11 Commerce
- Class 11 Arts
- Class 12 Science
- Class 12 Commerce
- Class 12 Arts

All inspected `school_classes.section_id` values are `null`.

### Legacy Class Management

`class_management` also contains 19 active rows:

- Nursery, LKG, UKG
- Class 1 through Class 10
- Class 11 with `section` values Science, Commerce, Arts
- Class 12 with `section` values Science, Commerce, Arts

Raw `class_management.name` is not a one-to-one match for senior streams. The safe mapping is:

```text
trim(class_management.name + " " + class_management.section) -> school_classes.name
```

Using that combined value, all 19 rows map exactly to `school_classes.name`.

### Empty `classes` Table

`classes` exists but has zero rows. This lowers data-migration risk but does not remove runtime risk from code that still joins or queries it.

### Sections

`sections` contains 4 active rows:

- `id=1`, `name=A`
- `id=2`, `name=B`
- `id=3`, `name=C`
- `id=4`, `name=D`

All inspected `sections.class_id` values are `null`.

Student string sections are not A-D. Distinct `students.section` values are:

- `"1"`
- `"2"`
- `"3"`
- `"4"`

These correspond numerically to `sections.id`, not to `sections.name`.

### Student Class Values

Distinct `students.class` contains the same 19 names as `school_classes.name`:

- Nursery
- LKG
- UKG
- Class 1 through Class 10
- Class 11 Science / Commerce / Arts
- Class 12 Science / Commerce / Arts

Distinct `students.class_id` values:

```text
1, 2, 3, ..., 19
```

Distinct `students.school_class_id` values:

```text
1, 2, 3, ..., 19
```

Distinct `students.section_id` values:

```text
1, 2, 3, 4
```

### String-Based Domain Values

| Source | Distinct values | Mapping status |
|---|---|---|
| `fee_structures.class_name` | `Class 2` | Exact match to `school_classes.name`. |
| `exams.class_name` | `Class 5` | Exact match to `school_classes.name`. |
| `exam_papers.class_section` | No non-null distinct values found | No live mismatch, but field remains string-based. |
| `bell_timings.class_section` | `Class 5` | Matches known `school_classes.name`. |

## FK Compatibility Findings

| Check | Result | Status |
|---|---:|---|
| Students with `class_id` not found in `school_classes.id` | 0 | Safe by existence |
| Students with `school_class_id` not found in `school_classes.id` | 0 | Safe by existence |
| Students where `class_id != school_class_id` | 1 | Not safe for blind canonical switch |
| Students with `class` but no `class_id` | 0 | Safe |
| Students with `class_id = null` | 0 | Safe |
| Students with `school_class_id = null` | 0 | Safe |
| Students with `section_id = null` | 0 | Safe |
| Teacher assignments with invalid `class_id` | 0 | Safe |
| Teacher assignments with invalid `section_id` | 0 | Safe, allowing nullable sections |
| Teacher assignments with invalid `subject_id` | 0 | Safe |

The one student FK disagreement found:

| Student ID | Name | `class` string | `class_id` | `school_class_id` | `section` | `section_id` |
|---:|---|---|---:|---:|---|---:|
| 301 | Demo Student 831 | Class 8 | 11 | 8 | 3 | 3 |

Important interpretation:

- `school_classes.id = 11` is Class 8.
- `school_classes.id = 8` is Class 5.
- For this row, `students.class` agrees with `students.class_id`, not `students.school_class_id`.

## Orphan / Mismatch Counts

| Mismatch | Count |
|---|---:|
| `students.class_id` not in `school_classes.id` | 0 |
| `students.school_class_id` not in `school_classes.id` | 0 |
| `students.class_id != students.school_class_id` | 1 |
| `students.class_id` null | 0 |
| `students.school_class_id` null | 0 |
| `students.class` present but `class_id` null | 0 |
| `students.section_id` null | 0 |
| `teacher_class_subject_assignments.class_id` invalid | 0 |
| `teacher_class_subject_assignments.section_id` invalid | 0 |
| `teacher_class_subject_assignments.subject_id` invalid | 0 |
| `fee_structures.class_name` not matching `school_classes.name` | 0 |
| `exams.class_name` not matching `school_classes.name` | 0 |
| `exam_papers.class_section` not matching known class/section patterns | 0 |
| `bell_timings.class_section` not matching known class/section patterns | 0 |

## One-to-One Name Mapping Findings

| Mapping | Exact matches | Missing | Ambiguous / format notes |
|---|---:|---:|---|
| `class_management.name -> school_classes.name` | 13 of 15 distinct raw names | 2 | Missing raw names: `Class 11`, `Class 12`. Senior streams require `name + section`. |
| `class_management.name + section -> school_classes.name` | 19 of 19 | 0 | Safe mapping for current data. |
| `classes.name -> school_classes.name` | 0 | 0 | No rows in `classes`. |
| `students.class -> school_classes.name` | 19 of 19 | 0 | Safe by name for current distinct values. |
| `fee_structures.class_name -> school_classes.name` | 1 of 1 | 0 | Safe for current data. |
| `exams.class_name -> school_classes.name` | 1 of 1 | 0 | Safe for current data, but DB lacks `exams.class_id`. |

Duplicate and ambiguity notes:

- `class_management.name` is ambiguous for Class 11 and Class 12 because streams are stored in `section`, not in `name`.
- `students.section` is misleading: it stores numeric-looking strings `"1".."4"`, while `sections.name` stores A-D. Treat `students.section` as a legacy display/string field, not a canonical section label.
- No spelling/case mismatches were found among current `school_classes`, `students.class`, `fee_structures.class_name`, and `exams.class_name`.

## Canonical FK Recommendation

Recommendation: **D. Use a compatibility accessor temporarily, with `students.class_id` as the preferred canonical source after repairing the one disagreement.**

Why not choose `students.class_id` immediately?

- `students.class_id` maps cleanly to `school_classes.id` for all rows.
- `students.class_id` agrees with `students.class` for the known disagreement.
- Existing major code paths already use `students.class_id`.
- However, there is one row where `students.class_id != students.school_class_id`; a blind switch without documenting/repairing that row could hide an existing data defect.

Why not choose `students.school_class_id` temporarily?

- It also maps to existing `school_classes.id` for all rows.
- But for the one disagreement, it conflicts with the human-readable `students.class` value.
- It is not included in `Student::$fillable`, while `class_id` is.
- Many active controllers/services query `students.class_id`.

Practical decision:

1. Treat `school_classes` as canonical class table.
2. Treat `students.class_id` as the preferred canonical student FK after a targeted data review of student `id=301`.
3. Keep a temporary compatibility accessor/check that can detect `class_id` vs `school_class_id` disagreement.
4. Do not remove or ignore `school_class_id` until all writes normalize both columns or the column is formally deprecated in a later migration phase.

## Legacy Compatibility Recommendation

| Legacy piece | Recommendation |
|---|---|
| `class_management` | Keep active until admin route/view references are migrated. It still powers `admin/classes` and class assignment screens. Map senior rows by `name + section`. |
| `classes` | Mark as legacy/deprecated. Empty table, but code references remain and can break reports. Do not drop yet. |
| `class_sections` | Legacy bridge. Keep until `sections` usage is normalized and class-management screens are migrated. |
| `class_subject_assignments` | Legacy/non-canonical bridge. Keep until all subject assignment flows use `teacher_class_subject_assignments` or a clarified canonical class-subject table. |
| `students.class` | Legacy display/search field. Current values match `school_classes.name`, but writes should derive it from the canonical class FK. |
| `students.section` | Legacy display/string field. Current values are numeric strings matching `section_id`, not A-D labels. Do not use as section display without translation. |
| `fee_structures.class_name` | String-based compatibility field. Current data maps, but future canonical design should add/use a class FK only after fee-domain migration planning. |
| `exams.class_name` | String-based compatibility field. Current DB lacks `class_id`; do not assume Exam model FK fields exist. |
| `exam_papers.class_section` | String-based compatibility field. Current row has no non-null value; controller logic must tolerate null. |
| `bell_timings.class_section` | String-based public schedule selector. Current value maps to a known class name. Keep as string until a dedicated timetable migration phase. |

## Top 20 Actual Data Risks

1. One student row has `class_id != school_class_id`: student `id=301`.
2. For student `id=301`, `class` and `class_id` say Class 8, while `school_class_id` says Class 5.
3. `students.section` stores `"1".."4"` while `sections.name` stores `A..D`; direct display or matching by string will be wrong.
4. `school_classes.section_id` exists but current rows have it null, so class-section pairing does not live there.
5. `sections.class_id` exists but current rows have it null, so sections behave as global sections despite older migrations tying them to `class_management`.
6. `class_management.name` alone cannot represent Class 11/12 streams; it needs `name + section`.
7. `classes` table is empty but still referenced by report/result code.
8. `exams` model fillable includes `class_id`/`subject_id`, but the current DB table does not have those columns.
9. `Subject` model fillable/casts reference `subject_type` and `sort_order`, but current DB columns are `type`, `max_marks`, and `pass_marks`.
10. `SchoolClass::$fillable` omits existing columns such as `section_id`, `academic_session_id`, and `teacher_id`.
11. `Student::$fillable` includes `class_id` but omits `school_class_id` and `section_id`, despite both columns being populated.
12. `SchoolClass::students()` uses `class_id`, while some report code uses `school_class_id`.
13. `FeeStructure` remains string keyed by `class_name`; current data maps, but future writes can drift.
14. `Exam` remains string keyed by `class_name`; current data maps, but model/controller assumptions may drift from DB.
15. `ExamPaper` has both `class_section` and `class_id`, with current live `class_section` empty; public/search APIs need null-safe behavior.
16. `BellTiming` uses `class_section` string and has no FK path to `school_classes` or `sections`.
17. `teacher_class_subject_assignments.section_id` is often null, so teacher assignment section-level ownership may be incomplete.
18. Legacy `admin/classes` and canonical-looking `admin/school-classes` routes are both active.
19. Multiple migration eras target different class tables (`class_management`, `school_classes`, `classes`), making fresh install and schema replay risky.
20. Route/model naming can mislead future work: `ClassController` manages `ClassManagement`, while `SchoolClassController` manages `SchoolClass`.

## Safe Phase 3C Plan

1. **Do not write migrations yet.**
2. **Create a read-only compatibility helper/design first**:
   - Preferred class ID: `students.class_id`.
   - Cross-check: `students.school_class_id`.
   - Human fallback: `students.class -> school_classes.name`.
   - Flag disagreements rather than silently choosing when values conflict.
3. **Repair model relationship contracts in a narrow code phase only after review**:
   - Add/confirm `SchoolClass` relationships for `sections`, `teacherClassSubjectAssignments`, `lessonPlans`, and related domains only where current columns support them.
   - Consider adding a separate `Student::schoolClassBySchoolClassId()` relationship for the transitional column if needed.
4. **First recommended code task for Phase 3C**:
   - Add a small, non-mutating class compatibility layer or model accessors that expose `canonical_class_id` / `canonicalSchoolClass` while detecting the one disagreement.
   - Do not normalize data in code yet.
5. **Second task after compatibility layer**:
   - Audit and update `AdminStudentController` create/update flows so future writes keep `class`, `class_id`, `school_class_id`, `section`, and `section_id` consistent.
6. **Keep `class_management` live temporarily**:
   - Do not deprecate admin class-management routes until route/view references are mapped and a replacement screen exists.
7. **Do not drop `classes`, `class_management`, `class_sections`, or `class_subject_assignments`.**
8. **Add isolated tests before changing behavior**:
   - Use minimal schema tests for compatibility logic.
   - Avoid full migrations until class-related migration drift is repaired.

## Confirmation

- No application code was modified.
- No routes, models, controllers, migrations, seeders, or config files were modified.
- No database data was created, updated, deleted, truncated, seeded, or migrated.
- The only file created by this phase is this report.
