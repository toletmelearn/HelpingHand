# Phase 6U - Web Attendance Destroy / Delete Behavior Audit

Date: 2026-06-06

Scope: Read-only audit of ordinary web attendance destroy/delete behavior before changing delete semantics.

## Files Inspected

- `app/Http/Controllers/AttendanceController.php`
- `app/Http/Controllers/API/AttendanceController.php`
- `app/Models/Attendance.php`
- `app/Policies/AttendancePolicy.php`
- `resources/views/attendance/index.blade.php`
- `resources/views/attendance/show.blade.php`
- `resources/views/attendance/edit.blade.php`
- `routes/web.php`
- `routes/api.php`
- `tests/Feature/Attendance/AttendanceWebUpdateIdentityGuardTest.php`
- `docs/project-autopsy/PHASE_6T_WEB_ATTENDANCE_UPDATE_IDENTITY_GUARD.md`
- `docs/project-autopsy/PHASE_5M_API_ATTENDANCE_DESTROY_GUARD.md`
- `docs/project-autopsy/PHASE_6S_WEB_ATTENDANCE_UPDATE_MUTATION_AUDIT.md`

## Commands Run

```powershell
Get-Content -Path app/Http/Controllers/AttendanceController.php
Get-Content -Path app/Http/Controllers/API/AttendanceController.php
Get-Content -Path app/Models/Attendance.php
Get-Content -Path app/Policies/AttendancePolicy.php
Get-Content -Path resources/views/attendance/index.blade.php
Get-Content -Path resources/views/attendance/show.blade.php
Get-Content -Path resources/views/attendance/edit.blade.php
Get-Content -Path routes/web.php
Get-Content -Path routes/api.php
Get-Content -Path tests/Feature/Attendance/AttendanceWebUpdateIdentityGuardTest.php
Get-Content -Path docs/project-autopsy/PHASE_6T_WEB_ATTENDANCE_UPDATE_IDENTITY_GUARD.md
Get-Content -Path docs/project-autopsy/PHASE_5M_API_ATTENDANCE_DESTROY_GUARD.md
Get-Content -Path docs/project-autopsy/PHASE_6S_WEB_ATTENDANCE_UPDATE_MUTATION_AUDIT.md
rg -n "function destroy|attendance\.destroy|@can|delete\(|Delete Record|Delete this attendance|Route::resource\('attendance'|admin.*attendance|apiResource\('attendance'" app/Http/Controllers/AttendanceController.php app/Http/Controllers/API/AttendanceController.php app/Models/Attendance.php app/Policies/AttendancePolicy.php resources/views/attendance/index.blade.php resources/views/attendance/show.blade.php resources/views/attendance/edit.blade.php routes/web.php routes/api.php
php -l app/Http/Controllers/AttendanceController.php
php -l app/Http/Controllers/API/AttendanceController.php
php -l app/Models/Attendance.php
php -l app/Policies/AttendancePolicy.php
php artisan route:list | Select-String "attendance"
```

Notes:

- No optional live database checks were run. Source inspection clearly shows `Attendance` does not use `SoftDeletes`, and avoiding local MySQL kept this phase tightly read-only.
- No attendance write, delete, export, biometric, migration, or full-suite command was run.

## Web Destroy Method Findings

`AttendanceController@destroy(Attendance $attendance)` exists.

Current behavior:

```php
public function destroy(Attendance $attendance)
{
    $this->authorize('delete', $attendance);
    $attendance->delete();
    
    return redirect()->route('attendance.index')
        ->with('success', 'Attendance record deleted successfully!');
}
```

Findings:

- The method calls `$this->authorize('delete', $attendance)` before deleting.
- The method calls `$attendance->delete()`.
- `Attendance` does not use the `SoftDeletes` trait.
- No `deleted_at` handling is present in the model.
- Therefore the current web destroy path is a hard delete path.
- No guard or `423 Locked` style disabled response exists.
- No delete reason is captured.
- No `deleted_by` or correction metadata is captured.
- No transaction is used.
- No audit log write was found in the destroy method.
- The behavior is not audit-preserving.

## Route Findings

Route inventory shows web attendance destroy routes through resource registration:

- `DELETE admin/attendance/{attendance}` -> `AttendanceController@destroy`, route name `admin.attendance.destroy`
- `DELETE attendance/{attendance}` -> `AttendanceController@destroy`, route name `attendance.destroy`

Additional API route:

- `DELETE api/v1/attendance/{attendance}` -> `API\AttendanceController@destroy`, route name `attendance.destroy`

Findings:

- Web destroy uses route model binding through `{attendance}` and `Attendance $attendance`.
- Both admin-prefixed and unprefixed web attendance destroy routes dispatch to the same controller method.
- Admin-prefixed and unprefixed web route names do not collide with each other.
- The API attendance resource also uses generic route names such as `attendance.destroy`, creating route-name ambiguity with the unprefixed web resource in route listings.
- The unprefixed web attendance resource is inside an authenticated route group.
- The admin-prefixed attendance resource is also available and uses the same controller method.
- Server-side delete authorization is enforced by `AttendancePolicy::delete()`.

## View / UI Delete Findings

### Index View

`resources/views/attendance/index.blade.php` contains a delete form for each attendance row:

```blade
<form action="{{ route('attendance.destroy', $attendance) }}" 
      method="POST" 
      style="display: inline;"
      onsubmit="return confirm('Delete this attendance record?');">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-outline-danger" title="Delete">
        <i class="bi bi-trash"></i>
    </button>
</form>
```

Findings:

- Delete UI exists on the index page.
- A browser confirmation prompt exists.
- No visible `@can('delete', $attendance)` gate was found around the delete form.
- Non-admin users who can see the row may see the delete control, but the server policy should reject the actual request.

### Show View

`resources/views/attendance/show.blade.php` contains a delete form:

```blade
<form action="{{ route('attendance.destroy', $attendance) }}" 
      method="POST"
      onsubmit="return confirm('Are you sure you want to delete this attendance record?');">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-danger w-100">
        <i class="bi bi-trash"></i> Delete Record
    </button>
</form>
```

Findings:

- Delete UI exists on the show page.
- A browser confirmation prompt exists.
- No visible `@can('delete', $attendance)` gate was found around the delete form.
- The button text promises direct deletion.

### Edit View

`resources/views/attendance/edit.blade.php` does not expose a delete button/form in the inspected source.

## Policy / Auth Findings

`AttendancePolicy::delete(User $user, Attendance $attendance)` exists:

```php
public function delete(User $user, Attendance $attendance): bool
{
    // Only admins can delete attendance
    return $user->hasRole('admin');
}
```

Findings:

- Admin users can delete according to policy.
- Teachers cannot delete according to policy.
- The web destroy controller calls the policy before delete.
- The delete policy is stricter than the update policy.
- `AttendancePolicy::update()` allows admins and may allow teachers to update their own recently marked attendance rows, while `delete()` is admin-only.
- Policy authorization reduces role exposure but does not solve audit-preservation risk for admin deletes.

## API Destroy Comparison

API destroy is already guarded from Phase 5M:

```php
// Phase 5M: hard delete disabled until audit-preserving attendance correction workflow exists.
return $this->error(
    'API attendance deletion is temporarily disabled. Use an audit-preserving correction workflow once enabled.',
    423
);
```

Comparison:

- API destroy returns HTTP `423 Locked`.
- API destroy does not call `findOrFail()` before the guard.
- API destroy does not call `$attendance->delete()`.
- Web destroy still calls `$attendance->delete()` after authorization.
- Web destroy therefore has a weaker safety posture than API destroy.

The same audit-preserving correction/void workflow concern applies to web attendance deletion.

## Risk Classification

RED:

- Web destroy hard-deletes attendance records.
- `Attendance` does not use `SoftDeletes`.
- No audit-preserving void/correction workflow exists in web destroy.
- No delete reason, deleted-by, transaction, or audit trail is captured.
- Delete UI exists on index and show pages.
- Web destroy safety differs from the already-guarded API destroy posture.

YELLOW:

- Delete UI is not visibly policy-gated with `@can`, so unauthorized users may see controls even if the server rejects the request.
- Admin-only policy prevents teacher deletes but still allows irreversible admin deletes.
- Admin-prefixed and unprefixed web routes both reach the same hard-delete method.
- API and unprefixed web resources both use generic route names such as `attendance.destroy`, which is confusing during route audits.
- Browser confirmation exists, but it is not an audit control.

GREEN:

- Web destroy calls `$this->authorize('delete', $attendance)` before deleting.
- `AttendancePolicy::delete()` is admin-only.
- API destroy is already guarded and returns `423`.
- Edit view does not expose delete controls.

## Safe Implementation Options

### Option A - Guard web destroy server-side

Keep the route registered, but make `AttendanceController@destroy()` return back with a clear warning and delete nothing.

Pros:

- Smallest server-side safety fix.
- Blocks crafted DELETE requests.
- Aligns web posture with API destroy.
- Does not require schema changes.

Cons:

- Delete buttons may still appear until a UI follow-up hides or disables them.

### Option B - Hide or disable delete buttons only

Remove or disable delete UI from index/show pages.

Pros:

- Reduces accidental clicks.
- Simple UI change.

Cons:

- Crafted DELETE requests would still work.
- Does not fix server-side hard-delete exposure.

### Option C - Add SoftDeletes

Introduce `SoftDeletes` to `Attendance`.

Pros:

- Prevents physical deletion.

Cons:

- Requires migration/schema policy.
- Does not automatically provide correction reason, approval, or audit semantics.
- Not a small immediate phase.

### Option D - Build audit-preserving correction/void workflow

Create a proper attendance correction, void, or reversal workflow.

Pros:

- Correct long-term domain model.

Cons:

- Larger design and implementation effort.
- Not appropriate as the immediate safety patch.

## Recommended Phase 6V First Code Task

Phase 6V should guard web `AttendanceController@destroy()` server-side first.

Recommended behavior:

- Keep the web destroy route registered.
- Keep authorization if desired, but return before any delete call.
- Do not call `$attendance->delete()`.
- Return back with a clear warning such as:
  - `Web attendance deletion is temporarily disabled. Use an audit-preserving correction workflow once enabled.`
- Do not add SoftDeletes or schema changes.
- Do not implement a correction workflow in Phase 6V.
- Add focused tests proving web destroy does not delete and that the route remains registered.

After server-side guarding, a later phase can hide or disable delete UI on index/show to reduce confusion.

## Confirmation

This phase was read-only except for creating this report.

No application code, controllers, routes, models, services, views, tests, migrations, schema, database data, attendance records, attendance deletes, attendance writes, export execution, imports, seeds, sync jobs, biometric device commands, full test suite, or real/local MySQL data were modified or run.
