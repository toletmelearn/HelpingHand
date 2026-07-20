<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Every permission below is grouped under a 'module' key so the Manage
     * Permissions screen can render collapsible sections instead of one
     * unusable flat list of 200+ checkboxes. The original hand-picked
     * fine-grained permissions (fees, students, exam papers, etc.) are kept
     * verbatim -- renaming any of them would silently break the
     * hasPermission()/middleware checks already reading those exact
     * strings elsewhere in the app. Every module added after that section
     * is new: a simple view-{module} / manage-{module} pair per feature
     * area, covering every remaining sidebar section that had no
     * assignable permission at all before this.
     */
    private const MODULES = [
        // ---- Original fine-grained permissions (kept verbatim) ----
        'teacher-duties' => [
            'label' => 'Teacher Duties',
            'permissions' => [
                'can-be-class-teacher' => 'Can be assigned as a class teacher',
                'can-mark-attendance' => 'Can mark attendance',
                'can-edit-attendance' => 'Can edit attendance',
                'can-upload-marks' => 'Can upload marks',
                'can-edit-marks' => 'Can edit marks',
                'can-upload-syllabus' => 'Can upload syllabus',
                'can-approve-substitutions' => 'Can approve substitutions',
                'can-access-student-data' => 'Can access student data',
            ],
        ],
        'class-teacher-duties' => [
            'label' => 'Class Teacher Duties',
            'permissions' => [
                'can-edit-class-student-data' => 'Can edit class student data',
                'can-request-correction' => 'Can request correction',
                'can-view-full-attendance-history' => 'Can view full attendance history',
                'can-view-fee-status' => 'Can view fee status',
            ],
        ],
        'accountant-duties' => [
            'label' => 'Accountant Duties',
            'permissions' => [
                'can-manage-fees' => 'Can manage fees',
                'can-generate-fee-reports' => 'Can generate fee reports',
                'can-view-salary-data' => 'Can view salary data',
                'no-academic-access' => 'No academic access',
            ],
        ],
        'admin-duties' => [
            'label' => 'Admin Duties',
            'permissions' => [
                'full-access' => 'Full access',
                'assign-permissions' => 'Can assign permissions',
                'suspend-users' => 'Can suspend users',
                'access-audit-logs' => 'Can access audit logs',
            ],
        ],
        'fees' => [
            'label' => 'Fees',
            'permissions' => [
                'view-fees' => 'View fees', 'create-fees' => 'Create fees',
                'edit-fees' => 'Edit fees', 'delete-fees' => 'Delete fees',
            ],
        ],
        'fee-structures' => [
            'label' => 'Fee Structures',
            'permissions' => [
                'view-fee-structures' => 'View fee structures', 'create-fee-structures' => 'Create fee structures',
                'edit-fee-structures' => 'Edit fee structures', 'delete-fee-structures' => 'Delete fee structures',
            ],
        ],
        'students' => [
            'label' => 'Students',
            'permissions' => [
                'view-students' => 'View students', 'create-students' => 'Create students',
                'edit-students' => 'Edit students', 'delete-students' => 'Delete students',
            ],
        ],
        'teachers' => [
            'label' => 'Teachers',
            'permissions' => [
                'view-teachers' => 'View teachers', 'create-teachers' => 'Create teachers',
                'edit-teachers' => 'Edit teachers', 'delete-teachers' => 'Delete teachers',
            ],
        ],
        'exams' => [
            'label' => 'Exams',
            'permissions' => [
                'view-exams' => 'View exams', 'create-exams' => 'Create exams',
                'edit-exams' => 'Edit exams', 'delete-exams' => 'Delete exams',
            ],
        ],
        'results' => [
            'label' => 'Results',
            'permissions' => [
                'view-results' => 'View results', 'create-results' => 'Create results',
                'edit-results' => 'Edit results', 'delete-results' => 'Delete results',
            ],
        ],
        'attendance' => [
            'label' => 'Attendance',
            'permissions' => [
                'view-attendance' => 'View attendance', 'create-attendance' => 'Create attendance',
                'edit-attendance' => 'Edit attendance', 'delete-attendance' => 'Delete attendance',
            ],
        ],
        'admit-cards' => [
            'label' => 'Admit Cards',
            'permissions' => [
                'view-admit-cards' => 'View admit cards', 'create-admit-cards' => 'Create admit cards',
                'edit-admit-cards' => 'Edit admit cards', 'delete-admit-cards' => 'Delete admit cards',
                'view-admit-card-formats' => 'View admit card formats', 'create-admit-card-formats' => 'Create admit card formats',
                'edit-admit-card-formats' => 'Edit admit card formats', 'delete-admit-card-formats' => 'Delete admit card formats',
            ],
        ],
        'exam-papers' => [
            'label' => 'Exam Papers',
            'permissions' => [
                'view-exam-papers' => 'View exam papers', 'create-exam-papers' => 'Create exam papers',
                'edit-exam-papers' => 'Edit exam papers', 'delete-exam-papers' => 'Delete exam papers',
                'submit-exam-papers' => 'Submit exam papers', 'approve-exam-papers' => 'Approve exam papers',
                'lock-exam-papers' => 'Lock exam papers', 'clone-exam-papers' => 'Clone exam papers',
                'view-exam-paper-templates' => 'View exam paper templates', 'create-exam-paper-templates' => 'Create exam paper templates',
                'edit-exam-paper-templates' => 'Edit exam paper templates', 'delete-exam-paper-templates' => 'Delete exam paper templates',
                'toggle-template-status' => 'Toggle template status', 'preview-exam-paper-templates' => 'Preview exam paper templates',
            ],
        ],
        'daily-teaching-work' => [
            'label' => 'Daily Teaching Work',
            'permissions' => [
                'view-daily-teaching-work' => 'View daily teaching work', 'create-daily-teaching-work' => 'Create daily teaching work',
                'edit-daily-teaching-work' => 'Edit daily teaching work', 'delete-daily-teaching-work' => 'Delete daily teaching work',
                'download-daily-teaching-work-attachments' => 'Download daily teaching work attachments',
            ],
        ],
        'syllabus' => [
            'label' => 'Syllabus',
            'permissions' => [
                'view-syllabi' => 'View syllabi', 'create-syllabi' => 'Create syllabi',
                'edit-syllabi' => 'Edit syllabi', 'delete-syllabi' => 'Delete syllabi',
                'view-syllabus-progress-report' => 'View syllabus progress report',
            ],
        ],

        // ---- New modules: one view-{module} / manage-{module} pair each ----
        'academic-sessions' => 'Academic Sessions',
        'classes' => 'Classes',
        'sections' => 'Sections',
        'subjects' => 'Subjects',
        'grading-systems' => 'Grading Systems',
        'examination-patterns' => 'Examination Patterns',
        'bell-schedules' => 'Bell Schedules & Special Day Overrides',
        'class-teacher-assignment' => 'Class Teacher Control',
        'teacher-subject-assignment' => 'Teacher-Subject Assignment',
        'teacher-leaves' => 'Teacher Leaves',
        'teacher-substitutions' => 'Teacher Substitution',
        'teacher-biometric' => 'Teacher Biometric',
        'payroll' => 'Payroll & Salaries',
        'hostel' => 'Hostel Management',
        'imports' => 'Bulk Data Imports',
        'import-history' => 'Import History & Mapping Profiles',
        'backups' => 'Backup Management',
        'system-health' => 'System Health & Sync Monitor',
        'erp-config' => 'ERP Config Center',
        'gatekeeper' => 'Gatekeeper Terminal & Guard Duty Roster',
        'biometric-devices' => 'Biometric Devices',
        'fee-types' => 'Fee Head Master',
        'cashier-closing' => 'Cashier Closing',
        'defaulters' => 'Defaulter Dashboard & Registry',
        'year-closing' => 'Year-End Closing',
        'finance-reports' => 'Finance Reports',
        'reconciliation' => 'Reconciliation Center',
        'security-deposits' => 'Security Deposits',
        'upi-matching' => 'UPI Payment Matching',
        'payment-info' => 'Payment Info (QR & Bank)',
        'discount-rules' => 'Discount Rules',
        'advance-rebate-rules' => 'Advance Payment Rebates',
        'families' => 'Families & Sibling Linking',
        'budgets' => 'Budget & Budget Categories',
        'expenses' => 'Expenses',
        'library' => 'Library (Books & Issue/Return)',
        'library-settings' => 'Library Settings',
        'inventory' => 'Inventory & Assets',
        'certificates' => 'Certificates',
        'certificate-templates' => 'Certificate Templates & Document Formats',
        'system-settings' => 'System Settings',
        'notification-settings' => 'Notification Settings',
        'language-settings' => 'Language Settings',
        'field-permissions' => 'Field Permissions',
        'user-accounts' => 'User Management',
        'account-management' => 'Account Management',
        'reports' => 'Advanced & Analytics Reports',
        'front-office' => 'Front Office (Enquiries, Visitors, Gate Passes)',
        'admissions' => 'Student Admission Processing',
        'marks-moderation' => 'Marks Moderation',
        'report-promotion' => 'Report & Promotion Designer',
        'student-promotion' => 'Student Promotion',
    ];

    public function run(): void
    {
        foreach (self::MODULES as $moduleKey => $spec) {
            if (is_array($spec)) {
                // Original fine-grained module: explicit permission => label map.
                foreach ($spec['permissions'] as $name => $label) {
                    Permission::firstOrCreate(
                        ['name' => $name],
                        ['module' => $moduleKey, 'label' => $label]
                    )->fill(['module' => $moduleKey, 'label' => $label])->save();
                }
            } else {
                // New module: generate a view-{module} / manage-{module} pair.
                $moduleLabel = $spec;
                $pairs = [
                    "view-{$moduleKey}" => "View {$moduleLabel}",
                    "manage-{$moduleKey}" => "Manage {$moduleLabel}",
                ];
                foreach ($pairs as $name => $label) {
                    Permission::firstOrCreate(
                        ['name' => $name],
                        ['module' => $moduleKey, 'label' => $label]
                    )->fill(['module' => $moduleKey, 'label' => $label])->save();
                }
            }
        }

        // Assign permissions to roles
        $adminRole = Role::where('name', 'admin')->first();
        $teacherRole = Role::where('name', 'teacher')->first();
        $classTeacherRole = Role::where('name', 'class-teacher')->first();
        $accountantRole = Role::where('name', 'accountant')->first();
        $clerkRole = Role::where('name', 'clerk')->first();

        if ($adminRole) {
            foreach (Permission::all() as $permission) {
                $adminRole->grantPermission($permission->name);
            }
        }

        if ($teacherRole) {
            $teacherRole->grantPermission('can-upload-marks');
            $teacherRole->grantPermission('can-upload-syllabus');
            $teacherRole->grantPermission('can-mark-attendance');
            $teacherRole->grantPermission('view-students');
            $teacherRole->grantPermission('view-attendance');
            $teacherRole->grantPermission('view-daily-teaching-work');
            $teacherRole->grantPermission('create-daily-teaching-work');
            $teacherRole->grantPermission('download-daily-teaching-work-attachments');
        }

        if ($classTeacherRole) {
            $classTeacherRole->grantPermission('can-edit-class-student-data');
            $classTeacherRole->grantPermission('can-view-full-attendance-history');
            $classTeacherRole->grantPermission('can-view-fee-status');
            $classTeacherRole->grantPermission('view-students');
            $classTeacherRole->grantPermission('edit-students');
            $classTeacherRole->grantPermission('view-daily-teaching-work');
            $classTeacherRole->grantPermission('view-syllabi');
            $classTeacherRole->grantPermission('view-syllabus-progress-report');
        }

        if ($accountantRole) {
            $accountantRole->grantPermission('view-students');
            $accountantRole->grantPermission('can-manage-fees');
            $accountantRole->grantPermission('can-generate-fee-reports');
            $accountantRole->grantPermission('view-fees');
            $accountantRole->grantPermission('create-fees');
            $accountantRole->grantPermission('edit-fees');
            $accountantRole->grantPermission('delete-fees');
            $accountantRole->grantPermission('view-fee-structures');
            $accountantRole->grantPermission('create-fee-structures');
            $accountantRole->grantPermission('edit-fee-structures');
            $accountantRole->grantPermission('delete-fee-structures');
            $accountantRole->grantPermission('view-admit-cards');
            $accountantRole->grantPermission('view-admit-card-formats');
            $accountantRole->grantPermission('view-daily-teaching-work');
            // New fee-adjacent modules an accountant should already have.
            $accountantRole->grantPermission('view-fee-types');
            $accountantRole->grantPermission('manage-fee-types');
            $accountantRole->grantPermission('view-cashier-closing');
            $accountantRole->grantPermission('manage-cashier-closing');
            $accountantRole->grantPermission('view-defaulters');
            $accountantRole->grantPermission('manage-defaulters');
            $accountantRole->grantPermission('view-finance-reports');
            $accountantRole->grantPermission('view-payment-info');
            $accountantRole->grantPermission('manage-payment-info');
            $accountantRole->grantPermission('view-reconciliation');
            $accountantRole->grantPermission('manage-reconciliation');
            $accountantRole->grantPermission('view-security-deposits');
            $accountantRole->grantPermission('manage-security-deposits');
            $accountantRole->grantPermission('view-upi-matching');
            $accountantRole->grantPermission('manage-upi-matching');
            $accountantRole->grantPermission('view-year-closing');
            $accountantRole->grantPermission('manage-year-closing');
            $accountantRole->grantPermission('view-families');
            $accountantRole->grantPermission('manage-families');
            $accountantRole->grantPermission('view-budgets');
            $accountantRole->grantPermission('manage-budgets');
            $accountantRole->grantPermission('view-expenses');
            $accountantRole->grantPermission('manage-expenses');
        }

        if ($clerkRole) {
            // Clerk collects fees at the counter (FeeCollectionController has
            // explicit hasRole('clerk') branches, e.g. in reverseCollection(),
            // implying the role was always meant to reach this controller --
            // it just was never actually granted the permissions its own
            // constructor middleware requires).
            $clerkRole->grantPermission('view-students');
            $clerkRole->grantPermission('view-fees');
            $clerkRole->grantPermission('can-manage-fees');
            $clerkRole->grantPermission('create-fees');
            // A clerk who collects cash all day needs to submit their own
            // end-of-day cashier closing -- without this they'd get a 403 on
            // the one workflow step that directly follows from collecting.
            $clerkRole->grantPermission('view-cashier-closing');
            $clerkRole->grantPermission('manage-cashier-closing');
            // The Accountant Dashboard (which clerk already reaches via
            // view-fees) has clickable summary cards that drill into the
            // Refund/Discount/Outstanding/Late Fee/Demand registers -- those
            // live behind view-finance-reports. View-only, not manage.
            $clerkRole->grantPermission('view-finance-reports');
        }
    }
}
