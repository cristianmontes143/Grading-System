<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\Admin\DatabaseBackupController;
use App\Http\Controllers\Admin\GradeLevelController;
use App\Http\Controllers\Admin\GradeSubmissionController;
use App\Http\Controllers\Admin\GradingConfigurationController;
use App\Http\Controllers\Admin\GradingPeriodController;
use App\Http\Controllers\Admin\InterventionController;
use App\Http\Controllers\Admin\MpsSardoController;
use App\Http\Controllers\Admin\PolicyReferenceController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\QuickSearchController;
use App\Http\Controllers\Admin\ReadinessController;
use App\Http\Controllers\Admin\RegistrarCertificateController;
use App\Http\Controllers\Admin\RemedialClassController;
use App\Http\Controllers\Admin\ReportCardTemplateController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SchoolSettingController;
use App\Http\Controllers\Admin\SchoolYearController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TeacherAssignmentController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\TransmutationTableController;
use App\Http\Controllers\Adviser\AdvisoryController;
use App\Http\Controllers\Adviser\AdvisoryRecordController;
use App\Http\Controllers\Adviser\ReportCardController;
use App\Http\Controllers\Adviser\StudentInterventionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GradeSimulatorController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParentPortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Teacher\ClassController;
use App\Http\Controllers\Teacher\GradebookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $school = \App\Models\SchoolSetting::current();
    $activeSchoolYear = \App\Models\SchoolYear::current();
    $periods = $activeSchoolYear ? $activeSchoolYear->gradingPeriods()->get() : collect();
    $openPeriod = $periods->firstWhere('status', \App\Models\GradingPeriod::STATUS_OPEN);

    $publicAnnouncements = \Illuminate\Support\Facades\Schema::hasTable('announcements')
        ? \App\Models\Announcement::query()->forPublic()->limit(3)->get()
        : collect();

    return view('welcome', [
        'school' => $school,
        'activeSchoolYear' => $activeSchoolYear,
        'openPeriod' => $openPeriod,
        'periods' => $periods,
        'publicAnnouncements' => $publicAnnouncements,
        'publicStats' => [
            'grade_levels' => \App\Models\GradeLevel::query()->active()->count(),
            'curricula' => \App\Models\Curriculum::query()->active()->count(),
            'sections' => $activeSchoolYear
                ? \App\Models\Section::query()->where('school_year_id', $activeSchoolYear->id)->active()->count()
                : \App\Models\Section::query()->active()->count(),
        ],
    ]);
})->name('home');

// Public Read-Only Parent & Learner Official Grade Portal (rate-limited & Admin-togglable)
Route::get('/portal/grades', [ParentPortalController::class, 'index'])->name('portal.grades.index');
Route::post('/portal/grades', [ParentPortalController::class, 'lookup'])
    ->middleware('throttle:portal')
    ->name('portal.grades.lookup');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Interactive DepEd Grade Calculator / What-If Simulator (All Authenticated Faculty)
    Route::get('/tools/grade-calculator', [GradeSimulatorController::class, 'index'])->name('tools.grade-simulator');
    Route::post('/tools/grade-calculator', [GradeSimulatorController::class, 'index'])->name('tools.grade-simulator.compute');

    // Administrator Routes
    Route::prefix('admin')
        ->name('admin.')
        ->middleware('role:admin')
        ->group(function () {
            // Module 1: Settings, RBAC, Audit Logs
            Route::get('/quick-search', QuickSearchController::class)
                ->name('quick-search');
            Route::get('/readiness', [ReadinessController::class, 'index'])
                ->name('readiness.index');
            Route::get('/settings', [SchoolSettingController::class, 'edit'])
                ->middleware('can:settings.manage')
                ->name('settings.edit');
            Route::put('/settings', [SchoolSettingController::class, 'update'])
                ->middleware('can:settings.manage')
                ->name('settings.update');

            Route::get('/roles', [RolePermissionController::class, 'index'])
                ->middleware('can:roles.manage')
                ->name('roles.index');
            Route::put('/roles/{role}', [RolePermissionController::class, 'update'])
                ->middleware('can:roles.manage')
                ->name('roles.update');

            Route::get('/audit-logs', [AuditLogController::class, 'index'])
                ->middleware('can:audit_logs.view')
                ->name('audit-logs.index');
            Route::get('/audit-logs/export', [AuditLogController::class, 'export'])
                ->middleware('can:audit_logs.view')
                ->name('audit-logs.export');

            // School Announcements & Memos
            Route::get('/announcements', [AnnouncementController::class, 'index'])
                ->middleware('can:settings.manage')
                ->name('announcements.index');
            Route::post('/announcements', [AnnouncementController::class, 'store'])
                ->middleware('can:settings.manage')
                ->name('announcements.store');
            Route::post('/announcements/{announcement}/toggle', [AnnouncementController::class, 'togglePublish'])
                ->middleware('can:settings.manage')
                ->name('announcements.toggle');
            Route::post('/announcements/{announcement}/pin', [AnnouncementController::class, 'togglePin'])
                ->middleware('can:settings.manage')
                ->name('announcements.pin');
            Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])
                ->middleware('can:settings.manage')
                ->name('announcements.destroy');

            // Module 2: School Years & Grading Periods
            Route::get('/school-years', [SchoolYearController::class, 'index'])
                ->middleware('can:academic.manage')
                ->name('school-years.index');
            Route::post('/school-years', [SchoolYearController::class, 'store'])
                ->middleware('can:academic.manage')
                ->name('school-years.store');
            Route::post('/school-years/{schoolYear}/activate', [SchoolYearController::class, 'activate'])
                ->middleware('can:academic.manage')
                ->name('school-years.activate');
            Route::post('/school-years/{schoolYear}/close', [SchoolYearController::class, 'close'])
                ->middleware('can:academic.manage')
                ->name('school-years.close');

            Route::post('/school-years/{schoolYear}/grading-periods', [GradingPeriodController::class, 'store'])
                ->middleware('can:academic.manage')
                ->name('grading-periods.store');
            Route::post('/grading-periods/{gradingPeriod}/open', [GradingPeriodController::class, 'open'])
                ->middleware('can:periods.lock')
                ->name('grading-periods.open');
            Route::post('/grading-periods/{gradingPeriod}/lock', [GradingPeriodController::class, 'lock'])
                ->middleware('can:periods.lock')
                ->name('grading-periods.lock');
            Route::post('/grading-periods/{gradingPeriod}/unlock', [GradingPeriodController::class, 'unlock'])
                ->middleware('can:periods.lock')
                ->name('grading-periods.unlock');

            // Module 2: DepEd Policy References & Curricula
            Route::get('/policies', [PolicyReferenceController::class, 'index'])
                ->middleware('can:curricula.manage')
                ->name('policies.index');
            Route::post('/policies', [PolicyReferenceController::class, 'store'])
                ->middleware('can:curricula.manage')
                ->name('policies.store');
            Route::post('/policies/{policyReference}/confirm', [PolicyReferenceController::class, 'confirm'])
                ->middleware('can:curricula.manage')
                ->name('policies.confirm');

            Route::get('/curricula', [CurriculumController::class, 'index'])
                ->middleware('can:curricula.manage')
                ->name('curricula.index');
            Route::post('/curricula', [CurriculumController::class, 'store'])
                ->middleware('can:curricula.manage')
                ->name('curricula.store');
            Route::post('/curricula/implementations', [CurriculumController::class, 'saveImplementations'])
                ->middleware('can:curricula.manage')
                ->name('curricula.implementations.save');
            Route::post('/curricula/{curriculum}/tracks', [CurriculumController::class, 'storeTrack'])
                ->middleware('can:curricula.manage')
                ->name('curricula.tracks.store');
            Route::post('/shs-tracks/{shsTrack}/strands', [CurriculumController::class, 'storeStrand'])
                ->middleware('can:curricula.manage')
                ->name('curricula.strands.store');

            // Module 2: Grade Levels, Subjects, Sections
            Route::get('/grade-levels', [GradeLevelController::class, 'index'])
                ->middleware('can:academic.manage')
                ->name('grade-levels.index');
            Route::post('/grade-levels/{gradeLevel}/toggle', [GradeLevelController::class, 'toggle'])
                ->middleware('can:academic.manage')
                ->name('grade-levels.toggle');

            Route::get('/subjects', [SubjectController::class, 'index'])
                ->middleware('can:curricula.manage')
                ->name('subjects.index');
            Route::post('/subjects', [SubjectController::class, 'store'])
                ->middleware('can:curricula.manage')
                ->name('subjects.store');
            Route::post('/subjects/{subject}/toggle', [SubjectController::class, 'toggle'])
                ->middleware('can:curricula.manage')
                ->name('subjects.toggle');

            Route::get('/sections', [SectionController::class, 'index'])
                ->middleware('can:academic.manage')
                ->name('sections.index');
            Route::post('/sections', [SectionController::class, 'store'])
                ->middleware('can:academic.manage')
                ->name('sections.store');
            Route::post('/sections/clone', [SectionController::class, 'cloneFromSchoolYear'])
                ->middleware('can:academic.manage')
                ->name('sections.clone');
            Route::put('/sections/{section}', [SectionController::class, 'update'])
                ->middleware('can:academic.manage')
                ->name('sections.update');

            // Module 3: Teachers & Flexible Assignments
            Route::get('/teachers', [TeacherController::class, 'index'])
                ->middleware('can:teachers.manage')
                ->name('teachers.index');
            Route::get('/teachers/export', [TeacherController::class, 'export'])
                ->middleware('can:teachers.manage')
                ->name('teachers.export');
            Route::post('/teachers', [TeacherController::class, 'store'])
                ->middleware('can:teachers.manage')
                ->name('teachers.store');
            Route::put('/teachers/{teacher}', [TeacherController::class, 'update'])
                ->middleware('can:teachers.manage')
                ->name('teachers.update');
            Route::post('/teachers/{teacher}/toggle', [TeacherController::class, 'toggle'])
                ->middleware('can:teachers.manage')
                ->name('teachers.toggle');

            Route::get('/assignments', [TeacherAssignmentController::class, 'index'])
                ->middleware('can:assignments.manage')
                ->name('assignments.index');
            Route::post('/assignments/sections/{section}/adviser', [TeacherAssignmentController::class, 'assignAdviser'])
                ->middleware('can:assignments.manage')
                ->name('assignments.adviser');
            Route::post('/assignments/sections/{section}/subject', [TeacherAssignmentController::class, 'assignSubject'])
                ->middleware('can:assignments.manage')
                ->name('assignments.subject');
            Route::post('/assignments/{teacherAssignment}/deactivate', [TeacherAssignmentController::class, 'deactivate'])
                ->middleware('can:assignments.manage')
                ->name('assignments.deactivate');
            Route::post('/assignments/clone', [TeacherAssignmentController::class, 'cloneFromSchoolYear'])
                ->middleware('can:assignments.manage')
                ->name('assignments.clone');

            // Module 4: Student Management, Bulk Import & Transfers
            Route::get('/students', [StudentController::class, 'index'])
                ->middleware('can:students.manage')
                ->name('students.index');
            Route::get('/students/export', [StudentController::class, 'export'])
                ->middleware('can:students.manage')
                ->name('students.export');
            Route::post('/students', [StudentController::class, 'store'])
                ->middleware('can:students.manage')
                ->name('students.store');
            Route::get('/students/import/template', [StudentController::class, 'downloadTemplate'])
                ->middleware('can:students.manage')
                ->name('students.import.template');
            Route::post('/students/import', [StudentController::class, 'import'])
                ->middleware('can:students.manage')
                ->name('students.import');
            Route::get('/students/{student}', [StudentController::class, 'show'])
                ->middleware('can:students.manage')
                ->name('students.show');
            Route::get('/students/{student}/sf10', [StudentController::class, 'sf10'])
                ->middleware('can:students.manage')
                ->name('students.sf10');
            Route::put('/students/{student}', [StudentController::class, 'update'])
                ->middleware('can:students.manage')
                ->name('students.update');
            Route::post('/students/{student}/archive', [StudentController::class, 'archive'])
                ->middleware('can:students.manage')
                ->name('students.archive');
            Route::post('/students/{student}/enroll', [StudentController::class, 'enroll'])
                ->middleware('can:students.manage')
                ->name('students.enroll');
            Route::post('/students/{student}/enrollments/{enrollment}/transfer', [StudentController::class, 'transfer'])
                ->middleware('can:students.manage')
                ->name('students.transfer');

            // Batch Year-End Promotion & Rollover
            Route::get('/promotions', [PromotionController::class, 'index'])
                ->middleware('can:students.manage')
                ->name('promotions.index');
            Route::post('/promotions/execute', [PromotionController::class, 'execute'])
                ->middleware('can:students.manage')
                ->name('promotions.execute');

            // Module 5: Configurable Grading Policies & Transmutation Tables
            Route::get('/grading-policies', [GradingConfigurationController::class, 'index'])
                ->middleware('can:grading_policies.manage')
                ->name('grading-policies.index');
            Route::post('/grading-policies', [GradingConfigurationController::class, 'store'])
                ->middleware('can:grading_policies.manage')
                ->name('grading-policies.store');
            Route::post('/grading-policies/{gradingConfiguration}/confirm', [GradingConfigurationController::class, 'confirm'])
                ->middleware('can:grading_policies.manage')
                ->name('grading-policies.confirm');
            Route::post('/grading-policies/{gradingConfiguration}/toggle', [GradingConfigurationController::class, 'toggle'])
                ->middleware('can:grading_policies.manage')
                ->name('grading-policies.toggle');

            Route::get('/transmutation-tables', [TransmutationTableController::class, 'index'])
                ->middleware('can:grading_policies.manage')
                ->name('transmutation-tables.index');
            Route::post('/transmutation-tables', [TransmutationTableController::class, 'store'])
                ->middleware('can:grading_policies.manage')
                ->name('transmutation-tables.store');
            Route::get('/transmutation-tables/{transmutationTable}', [TransmutationTableController::class, 'show'])
                ->middleware('can:grading_policies.manage')
                ->name('transmutation-tables.show');
            Route::put('/transmutation-tables/{transmutationTable}/rows', [TransmutationTableController::class, 'updateRows'])
                ->middleware('can:grading_policies.manage')
                ->name('transmutation-tables.rows.update');
            Route::post('/transmutation-tables/{transmutationTable}/confirm', [TransmutationTableController::class, 'confirm'])
                ->middleware('can:grading_policies.manage')
                ->name('transmutation-tables.confirm');

            // Module 7: School-Wide Grade Submissions Monitoring & Authorized Overrides
            Route::get('/submissions', [GradeSubmissionController::class, 'index'])
                ->middleware('can:grades.view_all')
                ->name('submissions.index');
            Route::get('/submissions/{gradeSubmission}', [GradeSubmissionController::class, 'show'])
                ->middleware('can:grades.view_all')
                ->name('submissions.show');
            Route::post('/submissions/{gradeSubmission}/approve', [GradeSubmissionController::class, 'approve'])
                ->middleware('can:grades.view_all')
                ->name('submissions.approve');
            Route::post('/submissions/approve-all/{gradingPeriod}', [GradeSubmissionController::class, 'approveAllSubmitted'])
                ->middleware('can:grades.view_all')
                ->name('submissions.approve-all');
            Route::post('/submissions/{gradeSubmission}/return', [GradeSubmissionController::class, 'returnSubmission'])
                ->middleware('can:grades.override')
                ->name('submissions.return');
            Route::post('/grades/{quarterlyGrade}/override', [GradeSubmissionController::class, 'overrideGrade'])
                ->middleware('can:grades.override')
                ->name('grades.override');
            Route::post('/submissions/remind/{teacherAssignment}/{gradingPeriod}', [GradeSubmissionController::class, 'remind'])
                ->middleware('can:grades.view_all')
                ->name('submissions.remind');
            Route::post('/submissions/remind-all/{gradingPeriod}', [GradeSubmissionController::class, 'remindAllPending'])
                ->middleware('can:grades.view_all')
                ->name('submissions.remind-all');

            // Academic Analytics & DepEd Honor Roll
            Route::get('/analytics', [AnalyticsController::class, 'index'])
                ->middleware('can:grades.view_all')
                ->name('analytics.index');
            Route::get('/analytics/print', [AnalyticsController::class, 'printReport'])
                ->middleware('can:grades.view_all')
                ->name('analytics.print');
            Route::get('/interventions', [InterventionController::class, 'index'])
                ->middleware('can:students.manage')
                ->name('interventions.index');

            // Remedial Classes & Recomputed Final Grades (DO 13, s. 2018 / DO 015, s. 2026)
            Route::get('/remedials', [RemedialClassController::class, 'index'])
                ->middleware('can:students.manage')
                ->name('remedials.index');
            Route::post('/remedials', [RemedialClassController::class, 'store'])
                ->middleware('can:students.manage')
                ->name('remedials.store');
            Route::delete('/remedials/{remedialClass}', [RemedialClassController::class, 'destroy'])
                ->middleware('can:students.manage')
                ->name('remedials.destroy');
            Route::get('/remedials/enrollments/{enrollment}/certificate', [RemedialClassController::class, 'certificatePdf'])
                ->middleware('can:students.manage')
                ->name('remedials.certificate');

            // Registrar Official Certificates Hub (Enrollment, Good Moral, GWA/Ranking)
            Route::get('/certificates', [RegistrarCertificateController::class, 'index'])
                ->middleware('can:students.manage')
                ->name('certificates.index');
            Route::get('/certificates/enrollments/{enrollment}/generate', [RegistrarCertificateController::class, 'generate'])
                ->middleware('can:students.manage')
                ->name('certificates.generate');

            // DepEd MPS (Mean Percentage Score) & SARDO Attendance Early-Warning Monitor
            Route::get('/mps-sardo', [MpsSardoController::class, 'index'])
                ->middleware('can:grades.view_all')
                ->name('mps-sardo.index');
            Route::get('/mps-sardo/print', [MpsSardoController::class, 'printReport'])
                ->middleware('can:grades.view_all')
                ->name('mps-sardo.print');
            Route::get('/mps-sardo/export', [MpsSardoController::class, 'exportExcel'])
                ->middleware('can:grades.view_all')
                ->name('mps-sardo.export');

            // Module 8: Report Card Templates
            Route::get('/report-card-templates', [ReportCardTemplateController::class, 'index'])
                ->middleware('can:report_cards.templates')
                ->name('report-card-templates.index');
            Route::post('/report-card-templates', [ReportCardTemplateController::class, 'store'])
                ->middleware('can:report_cards.templates')
                ->name('report-card-templates.store');
            Route::post('/report-card-templates/{reportCardTemplate}/default', [ReportCardTemplateController::class, 'setDefault'])
                ->middleware('can:report_cards.templates')
                ->name('report-card-templates.default');

            // Module 9: Database Backup & Restore
            Route::get('/backups', [DatabaseBackupController::class, 'index'])
                ->middleware('can:backups.manage')
                ->name('backups.index');
            Route::post('/backups', [DatabaseBackupController::class, 'store'])
                ->middleware('can:backups.manage')
                ->name('backups.store');
            Route::get('/backups/{databaseBackup}/download', [DatabaseBackupController::class, 'download'])
                ->middleware('can:backups.manage')
                ->name('backups.download');
            Route::post('/backups/{databaseBackup}/restore', [DatabaseBackupController::class, 'restore'])
                ->middleware('can:backups.manage')
                ->name('backups.restore');
            Route::delete('/backups/{databaseBackup}', [DatabaseBackupController::class, 'destroy'])
                ->middleware('can:backups.manage')
                ->name('backups.destroy');
        });

    // Teacher 1 (Adviser) Routes
    Route::prefix('adviser')
        ->name('adviser.')
        ->middleware('role:adviser,admin')
        ->group(function () {
            Route::get('/sections', [AdvisoryController::class, 'index'])->name('sections.index');
            Route::get('/sections/{section}', [AdvisoryController::class, 'show'])->name('sections.show');
            Route::get('/sections/{section}/master-sheet/export', [AdvisoryController::class, 'exportMasterSheet'])->name('sections.export-summary');
            Route::get('/sections/{section}/master-sheet/print', [AdvisoryController::class, 'printMasterSheet'])->name('sections.print-summary');
            Route::get('/sections/{section}/roster/print', [AdvisoryController::class, 'printRoster'])->name('sections.print-roster');
            Route::get('/sections/{section}/sf5/export', [AdvisoryController::class, 'exportSf5'])->name('sections.export-sf5');
            Route::get('/sections/{section}/certificates', [AdvisoryController::class, 'honorCertificates'])->name('sections.certificates');
            Route::get('/enrollments/{enrollment}/certificate', [AdvisoryController::class, 'studentHonorCertificate'])->name('students.certificate');

            // Module 8: Attendance, Observed Values & Individual/Batch PDF Report Cards
            Route::get('/sections/{section}/records', [AdvisoryRecordController::class, 'edit'])->name('records.edit');
            Route::get('/sections/{section}/records/attendance/export', [AdvisoryRecordController::class, 'exportAttendance'])->name('records.export-attendance');
            Route::post('/sections/{section}/attendance', [AdvisoryRecordController::class, 'saveAttendance'])->name('records.attendance.save');
            Route::post('/sections/{section}/observed-values', [AdvisoryRecordController::class, 'saveObservedValues'])->name('records.values.save');
            Route::get('/sections/{section}/report-cards/batch', [ReportCardController::class, 'sectionBatch'])->name('report-cards.batch');
            Route::get('/enrollments/{enrollment}/summary', [ReportCardController::class, 'summary'])->name('students.summary');
            Route::post('/enrollments/{enrollment}/interventions', [StudentInterventionController::class, 'store'])->name('students.interventions.store');
            Route::post('/enrollments/{enrollment}/interventions/{intervention}/toggle', [StudentInterventionController::class, 'toggleStatus'])->name('students.interventions.toggle');
            Route::get('/enrollments/{enrollment}/sf10', [ReportCardController::class, 'sf10'])->name('students.sf10');
            Route::get('/enrollments/{enrollment}/report-card', [ReportCardController::class, 'individual'])->name('report-cards.individual');
        });

    // Subject Teacher (and Adviser acting as Subject Teacher) Routes
    Route::prefix('teacher')
        ->name('teacher.')
        ->middleware('role:subject_teacher,adviser,admin')
        ->group(function () {
            Route::get('/classes', [ClassController::class, 'index'])->name('classes.index');
            Route::get('/classes/print-load', [ClassController::class, 'printLoad'])->name('classes.print-load');
            Route::get('/classes/{teacherAssignment}', [ClassController::class, 'show'])->name('classes.show');
            Route::get('/classes/{teacherAssignment}/summary', [ClassController::class, 'summary'])->name('classes.summary');
            Route::get('/classes/{teacherAssignment}/summary/export', [ClassController::class, 'exportSummary'])->name('classes.summary.export');
            Route::get('/classes/{teacherAssignment}/roster/print', [ClassController::class, 'printRoster'])->name('classes.print-roster');

            // Module 6: Teacher Gradebook, Bulk Score Entry, Excel Import & Grade Sheet Export
            Route::get('/classes/{teacherAssignment}/periods/{gradingPeriod}/gradebook', [GradebookController::class, 'show'])
                ->name('gradebook.show');
            Route::post('/classes/{teacherAssignment}/periods/{gradingPeriod}/assessments', [GradebookController::class, 'storeAssessment'])
                ->name('gradebook.assessments.store');
            Route::put('/classes/{teacherAssignment}/periods/{gradingPeriod}/assessments/{assessment}', [GradebookController::class, 'updateAssessment'])
                ->name('gradebook.assessments.update');
            Route::post('/classes/{teacherAssignment}/periods/{gradingPeriod}/assessments/clone', [GradebookController::class, 'cloneAssessments'])
                ->name('gradebook.assessments.clone');
            Route::delete('/classes/{teacherAssignment}/periods/{gradingPeriod}/assessments/{assessment}', [GradebookController::class, 'deleteAssessment'])
                ->name('gradebook.assessments.destroy');
            Route::post('/classes/{teacherAssignment}/periods/{gradingPeriod}/scores', [GradebookController::class, 'saveScores'])
                ->name('gradebook.scores.save');
            Route::post('/classes/{teacherAssignment}/periods/{gradingPeriod}/descriptive', [GradebookController::class, 'saveDescriptive'])
                ->name('gradebook.descriptive.save');
            Route::post('/classes/{teacherAssignment}/periods/{gradingPeriod}/submit', [GradebookController::class, 'submitGrades'])
                ->name('gradebook.submit');
            Route::get('/classes/{teacherAssignment}/periods/{gradingPeriod}/template', [GradebookController::class, 'downloadScoreTemplate'])
                ->name('gradebook.template');
            Route::post('/classes/{teacherAssignment}/periods/{gradingPeriod}/import', [GradebookController::class, 'importScores'])
                ->name('gradebook.import');
            Route::get('/classes/{teacherAssignment}/periods/{gradingPeriod}/print', [GradebookController::class, 'printGradeSheet'])
                ->name('gradebook.print');
            Route::get('/classes/{teacherAssignment}/periods/{gradingPeriod}/export', [GradebookController::class, 'exportGradeSheet'])
                ->name('gradebook.export');
        });
});
