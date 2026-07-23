# PHASE C3 — Certificate PDF Export + Branch Merge
## Save at: docs/plans/phase-c3-certificate-pdf.md · Branch: academic-module-rebuild

Final item of the Academic module plan. Two tasks: build the PDF export, then close out the branch. Scope fence: do not touch the Fee module, Exam remnants, attendance, or anything else. Unrelated problems get listed, not fixed.

Pre-flight: branch is `academic-module-rebuild`, `git status` clean, all prior work pushed.

---

## TASK 1 — Certificate PDF Export

**Background:** The Certificate feature is fully built (draft → generated → published → locked/revoked state machine, serial numbering, TC-hold blocking, billing stop on TC issue) but outputs only an HTML preview. The codebase already contains a working DomPDF usage pattern inside `CBSEReportCardController` (unrouted, but the PDF code is valid). Reuse that exact library and pattern — do NOT introduce a new PDF package.

**Build:**

1. Read `CBSEReportCardController`'s PDF generation first and note the pattern (facade vs injection, paper setup, view rendering, response type).
2. Add a `downloadPdf($id)` action to `Admin\CertificateController`:
   - Only for certificates in `generated`, `published`, or `locked` states — `draft` and `revoked` return back with an error.
   - Renders the existing certificate HTML template to A4 PDF. If the preview template needs a print variant (no nav/buttons), create `certificate-pdf.blade.php` derived from it rather than polluting the preview.
   - Filename: `{certificate_serial}_{student_name}.pdf` (sanitize the name for filesystem safety).
   - TC-type certificates must show serial number and issue date prominently — verify they render in the PDF output, not just the HTML.
3. Authorization: gate `downloadPdf` with the SAME policy check the preview action uses — no weaker.
4. Route: add to the existing certificate route group with consistent naming (`admin.certificates.download-pdf`).
5. UI: add a "Download PDF" button on the certificate show view, visible only for the allowed states.
6. Tests (feature):
   - Admin downloads a generated certificate → response is a PDF (assert content-type and %PDF magic bytes)
   - Draft certificate → redirected with error, no PDF
   - Unauthorized role → 403
7. Run certificate + related suites. Commit: `C3: certificate PDF export via DomPDF`.

---

## TASK 2 — Close the branch

1. Write a final summary to `docs/plans/academic-module-completion-report.md`: one section per completed phase (A closure, B items done, C1, C2, C3) with commit hashes; the current known-failing-test ledger (list the 71 by suite: 7 API, 34 FeeFinance, 30 Admin — as recorded, so the ledger is version-controlled); and a short "deferred / out of scope" list (Fee class_name→FK migration, Exam module, BellSchedule deprecation removal, legacy class/class_id column drop, zz_retired_class_management final drop, PromotionRule engine decision).
2. Commit the report. Tag: `academic-phase-C-complete`. Push with tags.
3. Merge to main: `git checkout main && git pull && git merge academic-module-rebuild --no-ff -m "Academic module rebuild: Phases A, B, C complete"`. If ANY merge conflict appears, STOP and show me the conflicting files — do not resolve conflicts yourself.
4. Push main. Run the full test suite once on main; confirm failure counts match the ledger exactly (71, same distribution). Any NEW failure on main → report immediately.
5. Report final state: main's HEAD hash, tag list, test summary. Stop.
