# Final Blind Spot Discovery

Project: HelpingHand School ERP  
Mode: read-only inspection, except this report file  
Date: 2026-06-03  

## 1. Executive Summary

This final pass found several high-risk blind spots that were not just implementation gaps, but wiring and operational gaps. The most serious issues are:

- The API surface exists in `routes/api.php`, but the application bootstrap does not appear to register API routes, so `/api/v1` functionality is likely inactive.
- If API routes are registered later, `ApiAccessControl` currently allows every request after logging/rate checks because `authorizeRequest()` returns `true`.
- Sanctum tokens are long-lived by default and are created without abilities/scopes.
- Teacher login is split into `TeacherLogin` and `Teacher`, but login only checks the login row status, not the related teacher status or soft-delete state.
- The migration state is inconsistent, with multiple pending migrations for features that the code already references.
- Notification, SMS, attendance, and webhook-style workflows are mostly synchronous; queue infrastructure exists, but critical flows are not consistently dispatched to jobs.

Finding counts:

| Level | Count |
| --- | ---: |
| RED critical | 7 |
| YELLOW medium | 11 |
| GREEN safe | 7 |

## 2. RED Critical Findings

### RED-01: API Routes Exist But Are Likely Not Registered

Evidence:

- `routes/api.php` defines `/v1` routes for login, logout, students, teachers, attendance, exam papers, bell timing, and guardians.
- Prior route inspection showed no `/v1` API routes in `php artisan route:list --path=v1`.
- `bootstrap/app.php` previously showed web route registration, commands, and health only, without explicit API route registration.

Impact:

- API controllers and Sanctum logic may be dead code in the current app.
- Any mobile or external integration expecting `/api/v1/*` will fail.

Extra risk:

- `API\AuthController` extends `BaseApiController`, but calls `$this->sendResponse()` and `$this->sendError()`.
- The inspected `BaseApiController` exposes `success()`, `error()`, and `validationError()`, not `sendResponse()` or `sendError()`.
- If API routes are enabled, authentication endpoints may fail at runtime.

### RED-02: `ApiAccessControl` Does Not Enforce Authorization

Evidence:

- `app/Http/Middleware/ApiAccessControl.php` logs API access and performs rate checks.
- `authorizeRequest()` contains comments for role, ownership, and school boundary logic, but returns `true`.

Impact:

- Once API routes are registered, authenticated users may be able to access records outside their role, school, or ownership boundary.
- This is a multi-tenant data exposure risk for school ERP data.

### RED-03: Sanctum Tokens Are Long-Lived And Unscoped

Evidence:

- `config/sanctum.php` has `expiration => null`.
- `API\AuthController` creates tokens with `$user->createToken($deviceName)->plainTextToken`.
- No token abilities/scopes are passed.

Impact:

- Lost or leaked mobile/API tokens remain valid until manually revoked.
- All issued tokens have broad access instead of role-specific or device-specific abilities.

### RED-04: Teacher Login Can Drift From Teacher Status

Evidence:

- `config/auth.php` uses a `teacher` guard backed by the `teachers` provider, whose model is `App\Models\TeacherLogin`.
- `TeacherAuthController` authenticates `TeacherLogin::where('username', $request->username)->active()->first()`.
- The login flow checks `teacher_logins.status`, but not the related `teachers.status`.
- `Teacher` uses `SoftDeletes`.

Impact:

- A disabled or soft-deleted teacher may still authenticate if the related `teacher_logins` row remains active.
- This is especially risky because soft deletes do not trigger the `ON DELETE CASCADE` behavior defined for hard deletes.

### RED-05: Migration State Is Inconsistent And Fresh Install Is Risky

Evidence:

- `php artisan migrate:status` shows many migrations as `Pending` while related later migrations are already `Ran`.
- Pending migrations include code-dependent areas such as teacher logins, teacher class-subject assignments, lesson plans, fee structures, student fee assignments, fee collections, exam papers, and professional fee management.
- Some tables/features appear to have duplicate or recreated migrations across different dates.

Impact:

- Existing database state and fresh-install database state may diverge.
- Running all pending migrations may fail due to duplicate tables/columns, conflicting constraints, or schema drift.
- Code may reference columns/tables that are not present in the active database.

### RED-06: Duplicate Console Command Signature Can Recurse

Evidence:

- `app/Console/Commands/AssignMissingTeachers.php` defines signature `app:assign-missing-teachers`.
- `routes/console.php` also defines an Artisan closure command with the same signature.
- The closure command calls `$this->call('app:assign-missing-teachers')`, which can resolve to the same command name.

Impact:

- The command registration is ambiguous.
- The closure command risks recursion or unexpected resolution behavior.

### RED-07: Critical Notification/SMS Flows Are Synchronous

Evidence:

- Queue config and queue tables exist.
- Custom notification services call `Mail::raw`, `Notification::send`, and Twilio sending directly.
- No `app/Jobs` directory was found.
- Webhook comments mention dispatching jobs, but dispatch calls are commented out.

Impact:

- Bulk notifications, attendance alerts, and SMS sends can timeout web requests.
- Failed external provider calls may affect user-facing request reliability.
- Operational retry behavior is weak for high-value flows.

## 3. YELLOW Medium Findings

### YELLOW-01: Stripe Payment Webhook Code Exists But Is Not Routed

Evidence:

- `PaymentController` contains Stripe webhook handling.
- The webhook verifies signatures with `\Stripe\Webhook::constructEvent(...)`.
- Route search did not find a Stripe webhook route pointing to `PaymentController@handleWebhook`.

Impact:

- Stripe payment success events may never update local records.
- Payment integration appears incomplete or inactive.

### YELLOW-02: Payment Success Handler Does Not Fully Settle Fees

Evidence:

- `handlePaymentSuccess()` updates transaction metadata and `payment_status`.
- It does not clearly update paid amount, due amount, fee status, or ledger-style fee settlement fields.

Impact:

- Even if the webhook is routed, fee state may remain inconsistent after payment.

### YELLOW-03: `.env.example` Has Configuration Gaps

Evidence:

- `.env.example` documents Stripe, Twilio, mail, queue, cache, and DB basics.
- It does not document all school profile, payment UPI, QR path, biometric/device, or some operational settings used by the code.

Impact:

- Fresh setup may silently miss required environment values.
- Production deploys may behave differently from local setup.

### YELLOW-04: Twilio Environment Variable Names Are Inconsistent

Evidence:

- `.env.example` and `config/services.php` use `TWILIO_TOKEN` and `TWILIO_FROM`.
- `Admin\NotificationSettingController` references names like `TWILIO_AUTH_TOKEN` and `TWILIO_PHONE_NUMBER`.

Impact:

- SMS settings tests or runtime SMS sending may fail depending on which naming convention is used.

### YELLOW-05: README Setup Guidance Is Risky/Stale

Evidence:

- README recommends `php artisan migrate:fresh --seed`.
- Composer requires PHP 8.2, while README references PHP 8.3+.
- `.env.example` defaults to SQLite, while README emphasizes MySQL.
- README includes default admin credentials.

Impact:

- Developers may destroy local data accidentally.
- Setup expectations are inconsistent.
- Default credentials must not survive into production.

### YELLOW-06: Composer Setup Script Runs Forced Migrations

Evidence:

- `composer.json` includes a `setup` script that runs `php artisan migrate --force`.

Impact:

- This is acceptable for controlled setup, but risky if run against the wrong environment.
- It is especially risky while migration status is inconsistent.

### YELLOW-07: No Scheduled Tasks Found

Evidence:

- No `Schedule::` usage was found.
- `routes/console.php` only defines simple commands.

Impact:

- Backups, reminders, attendance sync, fee due alerts, cleanup tasks, and notification retries are not currently scheduled.

### YELLOW-08: Public Test Artifact Exists

Evidence:

- `public/test-ajax.html` exists.

Impact:

- Test files in public web root increase accidental exposure and production noise.

### YELLOW-09: Public Storage Is Web-Exposed

Evidence:

- `public/storage` is present as a Laravel storage link/junction.

Impact:

- This is normal Laravel behavior, but uploaded files must be validated, named safely, and access-controlled where sensitive.

### YELLOW-10: `TeacherLogin` School Relationship Is Weak

Evidence:

- `teacher_logins.school_id` is nullable and has no foreign key in the inspected migration.
- `TeacherLogin::school()` calls `belongsTo(School::class)`, while the broader schema appears to use school-related tables inconsistently.

Impact:

- School scoping for teacher login may be unreliable.
- Multi-school isolation may depend on nullable, unenforced data.

### YELLOW-11: Schema Naming Drift Exists

Evidence:

- Student class handling uses both `class_id` and `school_class_id` patterns in the codebase/schema.
- Fee structure migrations show drift between status-style fields and active-style fields.

Impact:

- Controllers, models, and migrations may disagree about the canonical column names.
- This increases runtime error risk and complicates future fixes.

## 4. GREEN Safe Findings

### GREEN-01: No Secrets Were Copied Into This Report

Only file paths, config key names, and structural findings are documented.

### GREEN-02: Stripe Webhook Verification Code Is Present

If properly routed, the Stripe webhook handler already includes signature verification logic.

### GREEN-03: Sanctum Logout Paths Revoke Tokens

The API auth controller includes current-token logout and all-token logout behavior.

### GREEN-04: Queue Infrastructure Exists

`config/queue.php`, queue-related migrations, and failed job support are present.

### GREEN-05: No Obvious Sensitive Public Files Were Found

Public scan did not reveal obvious `.env`, `.sql`, `.log`, `.zip`, `.bak`, `.old`, dump, or backup files.

### GREEN-06: Teacher Login Migration Has Useful Constraints

The inspected teacher login migration enforces a unique `teacher_id` and cascades on hard teacher deletion.

### GREEN-07: `.env.example` Exists

The project has a setup template, even though it needs expansion and normalization.

## 5. Sanctum/API Token Findings

- `User` uses `Laravel\Sanctum\HasApiTokens`.
- Sanctum expiration is disabled with `expiration => null`.
- Token prefix defaults to an empty string.
- API login creates plain text bearer tokens without abilities.
- Logout deletes the current access token.
- Logout-all deletes all user tokens.
- Token refresh deletes the current token and creates a replacement.
- API register route exists, but register logic is intentionally disabled.
- API routes are likely inactive unless API route registration is restored in bootstrap.

## 6. `ApiAccessControl` Findings

- Performs access logging.
- Performs rate-limit style checks.
- Does not currently enforce role access, ownership, tenant/school scope, or record-level authorization.
- Comments imply planned authorization logic, but implementation returns `true`.
- This middleware must not be treated as a security boundary in its current form.

## 7. Queue/Notification Findings

- Queue default is environment-driven and defaults to database.
- `.env.example` sets `QUEUE_CONNECTION=database`.
- No custom jobs folder was found.
- Notification classes and services exist, but critical sends are mostly synchronous.
- Twilio bulk SMS loops are synchronous.
- Attendance notification loops are synchronous.
- Webhook processing comments mention dispatching jobs, but dispatch calls are commented out.

## 8. `TeacherLogin` Relationship Findings

- Teacher authentication uses `TeacherLogin`, not `Teacher`.
- `teacher_logins.teacher_id` is unique and constrained to `teachers.id`.
- Login status is stored separately on `teacher_logins.status`.
- The related teacher model also has status and soft-delete behavior.
- Current login flow does not verify related teacher status or `deleted_at`.
- `teacher_logins.school_id` is nullable and not enforced by a foreign key in the inspected migration.

## 9. Stripe/Payment Webhook Findings

- Stripe service configuration exists.
- `PaymentController` includes webhook handling and signature verification.
- No route was found for the Stripe webhook handler.
- Existing fee payment route points to `Admin\FeeController@payment`, not the Stripe webhook method.
- Webhook payment success logic appears partial and may not settle fee balances.

## 10. Migration/Fresh Install Risk Findings

High-risk pending migrations include:

- `create_teacher_logins_table`
- `create_teacher_class_subject_assignments_table`
- multiple lesson plan migrations
- professional fee management migration
- duplicate/recreated fee structure and student fee assignment migrations
- duplicate/recreated exam paper and certificate migrations
- parent/student linking migration
- result verification fields migration

Primary risks:

- Fresh install may fail.
- Existing install may not match code expectations.
- Some later migrations have run while earlier related migrations remain pending.
- Duplicate create-table migrations suggest migration history needs consolidation or careful repair.

## 11. Composer Script Findings

- `composer test` clears config and runs the Laravel test suite.
- `composer dev` starts server, queue listener, pail, and Vite together.
- `composer setup` installs dependencies, copies `.env`, generates key, runs forced migrations, installs frontend deps, and builds assets.
- No destructive command like `migrate:fresh` was found in Composer scripts.
- `migrate --force` is still risky while migrations are inconsistent.

## 12. `.env.example` And Setup Documentation Findings

- `.env.example` exists and covers many common Laravel settings.
- Missing or inconsistent areas include biometric settings, school profile fields, UPI/QR payment settings, and Twilio naming.
- README setup instructions are not aligned with Composer and `.env.example`.
- README should not encourage `migrate:fresh --seed` without strong local-only warnings.
- Default credentials should be treated as development-only and rotated/removed in production.

## 13. Scheduled Task Findings

- No Laravel scheduler configuration was found.
- No scheduled automation was found for:
  - attendance sync
  - fee reminders
  - notification retries
  - backups
  - stale token cleanup
  - payment reconciliation

## 14. Public Directory Exposure Findings

- `public/.htaccess`, `public/index.php`, `public/favicon.ico`, and `public/robots.txt` are expected.
- `public/storage` exists as a Laravel storage link/junction.
- `public/test-ajax.html` is a public test artifact.
- No obvious sensitive dump, backup, log, archive, or environment files were found in public.

## 15. Final Recommendation

Fix first:

1. Normalize migration state before running any fresh install or forced migration.
2. Register or intentionally remove the API surface, then fix `API\AuthController` response method mismatch.
3. Replace `ApiAccessControl::authorizeRequest()` with real role, tenant, ownership, and model-level authorization checks.
4. Lock down Sanctum tokens with expiration and abilities.
5. Ensure teacher login checks the related teacher status and soft-delete state.

Fix second:

1. Route and verify Stripe webhook behavior end-to-end.
2. Make payment success update the complete fee ledger/status.
3. Move SMS, email, attendance, and webhook processing into queued jobs.
4. Remove duplicate console command signature.
5. Normalize `.env.example`, `config/services.php`, and settings controller environment names.

Fix third:

1. Add scheduler entries for recurring ERP operations.
2. Remove public test artifacts.
3. Align README, Composer setup scripts, and actual environment defaults.
4. Resolve schema naming drift such as `class_id` vs `school_class_id`.
5. Add regression tests around API auth, teacher login, payments, migrations, and notification dispatch.

