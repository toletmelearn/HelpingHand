# Phase 2E - Parent/Guardian API Ownership Audit and Rate-Limit Response Check

## 1. Files Inspected

- `app/Http/Middleware/ApiAccessControl.php`
- `routes/api.php`
- `app/Models/User.php`
- `app/Models/ParentModel.php`
- `app/Models/Guardian.php`
- `app/Models/Student.php`
- `app/Http/Controllers/API/DashboardController.php`
- `app/Http/Controllers/API/GuardianController.php`
- `config/auth.php`
- `docs/project-autopsy/PHASE_2C_API_AUTHORIZATION_MAP.md`
- `docs/project-autopsy/PHASE_2D_API_ACCESS_CONTROL_IMPLEMENTATION.md`
- `database/migrations/2026_02_13_100001_create_parents_table.php`
- `database/migrations/2026_02_13_100000_add_student_id_to_parents_table.php`
- `database/migrations/2026_02_18_134758_add_mobile_admission_to_parents_table.php`
- `database/migrations/2026_01_22_045905_create_guardians_table.php`
- `database/migrations/2026_01_22_045951_create_student_guardian_table.php`
- `database/migrations/2026_01_17_070802_create_students_table.php`
- `database/migrations/2026_01_21_120000_add_user_id_to_students_table.php`
- `database/migrations/2026_01_22_072546_add_foreign_keys_to_students_table.php`

## 2. Files Changed

- `app/Http/Middleware/ApiAccessControl.php`
- `docs/project-autopsy/PHASE_2E_PARENT_GUARDIAN_API_OWNERSHIP_AUDIT.md`

No routes, controllers, models, migrations, database schema, Sanctum config, token expiration, or token abilities were changed.

## 3. Rate-Limit Response Bug

The bug existed.

Before Phase 2E, `ApiAccessControl::handle()` called:

```php
$this->rateLimit($request);
```

But `rateLimit()` can return a JSON `429 Too Many Requests` response. Because `handle()` ignored the return value, the middleware could continue into logging, authorization, and the downstream controller even after the limit was exceeded.

## 4. Rate-Limit Fix Applied

Only the rate-limit response handoff was changed:

```php
$rateLimitResponse = $this->rateLimit($request);

if ($rateLimitResponse instanceof Response) {
    return $rateLimitResponse;
}
```

This preserves the existing rate-limit logic, API access logging, authorization checks, and deny-by-default behavior from Phase 2D.

## 5. ParentModel Schema and Relationship Findings

`ParentModel`:

- Uses table `parents`.
- Extends `Authenticatable`, indicating it is used by the parent session guard.
- Fillable fields include `name`, `email`, `phone`, `mobile`, `admission_number`, `password`, and `student_id`.
- Defines `student()` as `belongsTo(Student::class)`.
- Does not define `user_id`.
- Does not define a `user()` relationship.

Read-only current schema check for `parents` returned:

```text
id, name, email, phone, password, student_id, remember_token, created_at, updated_at
```

Important concern:

- `create_parents_table` already includes `student_id`.
- `add_student_id_to_parents_table` also attempts to add `student_id`.
- This reinforces the known inconsistent migration state and should not be repaired during API authorization work.

Conclusion:

- `ParentModel` cannot currently be mapped to the Sanctum API `User` model through a reliable `user_id`.
- It may authenticate through the `parent` guard, but the active API token flow uses `App\Models\User`.

## 6. Guardian Schema and Relationship Findings

`Guardian`:

- Uses the default `guardians` table.
- Fillable fields include `name`, `relationship`, `phone`, `email`, `occupation`, `address`, `aadhar_number`, `is_primary`, and `is_active`.
- Defines `students()` as a `belongsToMany(Student::class, 'student_guardian', 'guardian_id', 'student_id')`.
- Does not define `user_id`.
- Does not define a `user()` relationship.

Read-only current schema check for `guardians` returned:

```text
id, name, relationship, phone, email, occupation, address, aadhar_number, is_primary, is_active, created_at, updated_at
```

Read-only current schema check for `student_guardian` returned:

```text
id, student_id, guardian_id, is_emergency_contact, can_pickup, created_at, updated_at
```

Conclusion:

- Guardian-to-student ownership exists through `student_guardian`.
- Sanctum `User` to `Guardian` ownership does not exist in the current schema.
- `User::guardians()` exists in the model, but it is not backed by a `guardians.user_id` column and is therefore unsafe for API authorization.

## 7. Student Parent/Guardian Relationship Findings

`Student`:

- Fillable includes `user_id`.
- Defines `user()` as `belongsTo(User::class)`.
- Defines `guardian()` as `belongsToMany(Guardian::class, 'student_guardian', 'student_id', 'guardian_id')`.
- Defines `parent()` as `hasOne(ParentModel::class)`.

Read-only current schema check for `students` returned relevant columns:

```text
id, user_id, guardian_id, name, admission_no, class_id, section_id, deleted_at, is_verified
```

Important concern:

- `students.guardian_id` exists, but the model primarily uses the `student_guardian` pivot for guardians.
- This creates two possible guardian ownership paths and should be normalized or explicitly documented before parent API access is opened.

## 8. API User to Parent/Guardian Mapping Conclusion

Parent/guardian ownership is unsafe for non-admin API access.

Reasons:

- Sanctum API authentication resolves `App\Models\User`.
- `ParentModel` belongs to the `parent` guard provider, not the `users` provider.
- `parents` has no `user_id`.
- `guardians` has no `user_id`.
- `User::guardians()` exists but is not schema-backed.
- `DashboardController@parentDashboard()` currently checks `guardian_id = $user->id`, which assumes a `users.id` equals a `guardians.id`. That is not a reliable ownership rule.
- `GuardianController` can load guardian records and children by route ID, but controller methods do not prove the authenticated user owns that guardian record.

Do not open parent child routes yet.

## 9. Parent Routes That Remain Blocked

These remain blocked for non-admin users by `ApiAccessControl`:

- `api.dashboard.parent` - `GET /api/v1/dashboard/parent`
- `guardians.index` - `GET /api/v1/guardians`
- `guardians.store` - `POST /api/v1/guardians`
- `guardians.show` - `GET /api/v1/guardians/{guardian}`
- `guardians.update` - `PUT/PATCH /api/v1/guardians/{guardian}`
- `guardians.destroy` - `DELETE /api/v1/guardians/{guardian}`
- `api.guardians.children` - `GET /api/v1/guardians/{id}/children`
- `api.guardians.notifications` - `GET /api/v1/guardians/{id}/notifications`

Admin users remain broadly allowed under the Phase 2D admin rule.

## 10. Recommended Phase 2F Plan

Keep parent API routes blocked until a reliable ownership chain is repaired.

Recommended repair path:

1. Choose one canonical API parent identity model.
   - Prefer `Guardian` for school domain ownership if guardians can represent father, mother, or other authorized caretaker.
   - Prefer `ParentModel` only if parent guard/session behavior must remain separate from guardian records.

2. Add or confirm a schema-backed identity link in a migration phase.
   - Option A: add `guardians.user_id` nullable foreign key to `users.id`.
   - Option B: add `parents.user_id` nullable foreign key to `users.id`.
   - Do not rely on matching numeric IDs between unrelated tables.

3. Update models after schema repair.
   - If using `Guardian`, add `Guardian::user()` and make `User::guardians()` valid.
   - If using `ParentModel`, add `ParentModel::user()` and `User::parent()` or `User::parents()`.

4. Implement `canAccessParentChildRecord()` in `ApiAccessControl`.
   - Resolve child student ID from route parameters or controller context.
   - For guardian path:
     ```php
     Guardian::where('user_id', $user->id)
         ->whereHas('students', fn ($query) => $query->where('students.id', $studentId))
         ->exists();
     ```
   - For parent path:
     ```php
     ParentModel::where('user_id', $user->id)
         ->where('student_id', $studentId)
         ->exists();
     ```

5. Open routes incrementally.
   - First: `api.dashboard.parent` after controller ownership query is corrected.
   - Second: read-only children/notifications routes.
   - Later: guardian write routes only after validation, ownership, and audit logging are designed.

## 11. Commands Run

Safe read-only and syntax commands only:

```powershell
php artisan tinker --execute="dump(Schema::getColumnListing('parents'));"
php artisan tinker --execute="dump(Schema::getColumnListing('guardians'));"
php artisan tinker --execute="dump(Schema::getColumnListing('student_guardian'));"
php artisan tinker --execute="dump(Schema::getColumnListing('students'));"
php -l app/Http/Middleware/ApiAccessControl.php
php artisan route --path=api/v1 | Select-String guardian
php artisan route --path=api/v1 | Select-String parent
php artisan route:list --path=api/v1 | Select-String guardian
php artisan route:list --path=api/v1 | Select-String parent
rg -n "function .parent|ParentModel|Guardian|student_guardian|guardian_id|parent_id|user_id" app routes database config tests -g "*.php"
git diff -- app/Http/Middleware/ApiAccessControl.php
Get-Content docs/project-autopsy/PHASE_2D_API_ACCESS_CONTROL_IMPLEMENTATION.md -TotalCount 220
```

Notes:

- `php artisan route --path=api/v1` is not valid in this Laravel 12 project and printed the Artisan route namespace help.
- The useful equivalent `php artisan route:list --path=api/v1` was run and confirmed the guardian and parent routes are registered.

## 12. Verification Summary

- `php -l app/Http/Middleware/ApiAccessControl.php` passed.
- Guardian API routes are visible under `/api/v1`.
- Parent dashboard API route is visible under `/api/v1`.
- Parent/guardian routes remain blocked for non-admin users by the Phase 2D middleware blocklists.
- The rate-limit 429 response is now returned immediately when `rateLimit()` produces a response.

## 13. Confirmation

No migrations were run.
No migration files were changed.
No database schema was changed.
No database-changing tests were run.
No Sanctum configuration, token expiration, token abilities, API routes, controllers, or model fillable arrays were changed.
