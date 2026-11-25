<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $rolesData = [
            [
                'name' => Role::ADMIN,
                'label' => 'Administrator',
                'description' => 'Full system administration: school years, MATATAG curricula, teachers, students, grading policies, period locking, audit logs, and database backups.',
            ],
            [
                'name' => Role::ADVISER,
                'label' => 'Teacher 1 (Adviser & Subject Teacher)',
                'description' => 'Manages assigned advisory section, views all submitted subject grades, monitors pending submissions, generates report cards, and encodes grades for own assigned subjects.',
            ],
            [
                'name' => Role::SUBJECT_TEACHER,
                'label' => 'Teacher 2 (Subject Teacher)',
                'description' => 'Encodes assessments and scores for assigned subjects and sections, computes and submits quarterly/term grades, and prints own subject grade sheets.',
            ],
        ];

        $roles = [];
        foreach ($rolesData as $data) {
            $roles[$data['name']] = Role::query()->updateOrCreate(
                ['name' => $data['name']],
                ['label' => $data['label'], 'description' => $data['description']]
            );
        }

        $permissionsData = [
            // Administration
            ['name' => 'settings.manage', 'label' => 'Manage School Settings', 'module' => 'Administration'],
            ['name' => 'roles.manage', 'label' => 'Manage Roles & Permissions', 'module' => 'Administration'],
            ['name' => 'audit_logs.view', 'label' => 'View Activity & Grade Audit Logs', 'module' => 'Administration'],
            ['name' => 'backups.manage', 'label' => 'Backup & Restore Database', 'module' => 'Administration'],

            // Academic & Curriculum
            ['name' => 'academic.manage', 'label' => 'Manage School Years, Grade Levels & Sections', 'module' => 'Academic'],
            ['name' => 'curricula.manage', 'label' => 'Manage DepEd MATATAG & SHS Curricula', 'module' => 'Academic'],
            ['name' => 'periods.lock', 'label' => 'Lock & Unlock Grading Periods', 'module' => 'Academic'],

            // People & Assignments
            ['name' => 'teachers.manage', 'label' => 'Manage Teacher Accounts', 'module' => 'Users'],
            ['name' => 'assignments.manage', 'label' => 'Assign Advisers & Subject Teachers', 'module' => 'Users'],
            ['name' => 'students.manage', 'label' => 'Manage Students, Bulk Import & Transfers', 'module' => 'Students'],

            // Grading
            ['name' => 'grading_policies.manage', 'label' => 'Configure Grading Policies & Transmutation', 'module' => 'Grading'],
            ['name' => 'grades.encode_own', 'label' => 'Encode Assessments & Scores for Assigned Subjects', 'module' => 'Grading'],
            ['name' => 'grades.submit_own', 'label' => 'Submit Grades for Assigned Subjects', 'module' => 'Grading'],
            ['name' => 'grades.export_own', 'label' => 'Print & Export Own Subject Grade Sheets', 'module' => 'Grading'],
            ['name' => 'grades.view_advisory', 'label' => 'View All Submitted Grades in Advisory Section', 'module' => 'Grading'],
            ['name' => 'grades.view_all', 'label' => 'View All Grades Across School', 'module' => 'Grading'],
            ['name' => 'grades.override', 'label' => 'Authorize & Modify Locked/Submitted Grades', 'module' => 'Grading'],

            // Report Cards & Advisory
            ['name' => 'advisory.monitor_submissions', 'label' => 'Monitor Subject Grade Submission Status', 'module' => 'Advisory'],
            ['name' => 'advisory.attendance', 'label' => 'Manage Advisory Attendance & Observed Values', 'module' => 'Advisory'],
            ['name' => 'report_cards.generate', 'label' => 'Generate Individual & Batch Section Report Cards', 'module' => 'Report Cards'],
            ['name' => 'report_cards.templates', 'label' => 'Customize Report Card Templates', 'module' => 'Report Cards'],
        ];

        $permissionsByName = [];
        foreach ($permissionsData as $perm) {
            $permissionsByName[$perm['name']] = Permission::query()->updateOrCreate(
                ['name' => $perm['name']],
                ['label' => $perm['label'], 'module' => $perm['module']]
            );
        }

        // Admin receives all permissions
        $roles[Role::ADMIN]->permissions()->sync(
            collect($permissionsByName)->pluck('id')->all()
        );

        // Teacher 1 (Adviser & Subject Teacher)
        $adviserPerms = [
            'grades.encode_own',
            'grades.submit_own',
            'grades.export_own',
            'grades.view_advisory',
            'advisory.monitor_submissions',
            'advisory.attendance',
            'report_cards.generate',
        ];
        $roles[Role::ADVISER]->permissions()->sync(
            collect($adviserPerms)->map(fn (string $name) => $permissionsByName[$name]->id)->all()
        );

        // Teacher 2 (Subject Teacher)
        $subjectTeacherPerms = [
            'grades.encode_own',
            'grades.submit_own',
            'grades.export_own',
        ];
        $roles[Role::SUBJECT_TEACHER]->permissions()->sync(
            collect($subjectTeacherPerms)->map(fn (string $name) => $permissionsByName[$name]->id)->all()
        );

        // Ensure default School Settings row exists
        if (! SchoolSetting::query()->exists()) {
            SchoolSetting::query()->create([
                'school_name' => 'School Grading Management System',
                'principal_title' => 'School Principal',
                'attendance_enabled' => true,
                'observed_values_enabled' => true,
                'default_calendar_mode' => 'trimester',
            ]);
        }

        // Ensure initial Administrator account exists (no fake student/grade records)
        $adminEmail = env('INITIAL_ADMIN_EMAIL', 'admin@school.local');
        $adminPassword = env('INITIAL_ADMIN_PASSWORD', 'Admin@12345');

        $admin = User::query()->firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'System Administrator',
                'password' => Hash::make($adminPassword),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $admin->roles()->syncWithoutDetaching([$roles[Role::ADMIN]->id]);
    }
}
