<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CurriculumGradeImplementation;
use App\Models\DatabaseBackup;
use App\Models\GradeLevel;
use App\Models\GradingPeriod;
use App\Models\PolicyReference;
use App\Models\SchoolSetting;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Services\Curriculum\CurriculumResolverService;
use App\Services\Grading\GradingConfigurationResolver;
use Illuminate\View\View;

class ReadinessController extends Controller
{
    public function __construct(
        private readonly CurriculumResolverService $curriculumResolver,
        private readonly GradingConfigurationResolver $configResolver
    ) {
    }

    public function index(): View
    {
        $school = SchoolSetting::current();
        $activeSy = SchoolYear::current();
        $checks = [];

        // 1. School Identity Configured
        $identityReady = ! empty($school->school_name) && ! empty($school->principal_name);
        $checks[] = [
            'title' => 'School Profile & Signatory Configured',
            'passed' => $identityReady,
            'detail' => $identityReady
                ? "{$school->school_name} • Signatory: {$school->principal_name}"
                : 'Set your official School Name, DepEd School ID, and Principal Name for SF9/SF10 headers.',
            'action_label' => 'School Settings',
            'action_url' => route('admin.settings.edit'),
        ];

        // 2. Active School Year
        $syReady = $activeSy !== null && ! $activeSy->is_closed;
        $checks[] = [
            'title' => 'Active School Year Set',
            'passed' => $syReady,
            'detail' => $syReady
                ? "Active School Year: SY {$activeSy->name} ({$activeSy->term_structure})"
                : 'Create and activate a current School Year to enable enrollment and grading.',
            'action_label' => 'School Years',
            'action_url' => route('admin.school-years.index'),
        ];

        // 3. Open Grading Period
        $openPeriods = $activeSy
            ? $activeSy->gradingPeriods()->where('status', GradingPeriod::STATUS_OPEN)->get()
            : collect();
        $checks[] = [
            'title' => 'Grading Period Open for Encoding',
            'passed' => $openPeriods->isNotEmpty(),
            'detail' => $openPeriods->isNotEmpty()
                ? 'Open period(s): ' . $openPeriods->pluck('code')->implode(', ')
                : 'No grading period is currently open. Open a period so teachers can encode scores.',
            'action_label' => 'Manage Periods',
            'action_url' => route('admin.school-years.index'),
        ];

        // 4. DepEd Policy References Confirmed
        $unconfirmedPolicies = PolicyReference::query()->where('is_confirmed_by_admin', false)->count();
        $checks[] = [
            'title' => 'DepEd Policy References Confirmed',
            'passed' => $unconfirmedPolicies === 0,
            'detail' => $unconfirmedPolicies === 0
                ? 'All DepEd Policy References have been verified and confirmed by Administrator.'
                : "{$unconfirmedPolicies} policy reference(s) still awaiting Administrator confirmation.",
            'action_label' => 'Policy Registry',
            'action_url' => route('admin.policies.index'),
        ];

        // 5. Curriculum Mapped to Active Sections' Grade Levels
        $sections = $activeSy
            ? Section::query()
                ->with(['gradeLevel', 'adviser', 'activeTeacherAssignments'])
                ->where('school_year_id', $activeSy->id)
                ->where('is_active', true)
                ->get()
            : collect();

        $activeGradeLevelIds = $sections->pluck('grade_level_id')->unique();
        $mappedGradeLevelIds = $activeSy
            ? CurriculumGradeImplementation::query()
                ->where('school_year_id', $activeSy->id)
                ->pluck('grade_level_id')
            : collect();
        $unmappedGradeLevels = $activeGradeLevelIds->diff($mappedGradeLevelIds);

        $checks[] = [
            'title' => 'Curriculum Versions Mapped to Sections',
            'passed' => $sections->isNotEmpty() && $unmappedGradeLevels->isEmpty(),
            'detail' => $sections->isEmpty()
                ? 'No active sections created yet for the active School Year.'
                : ($unmappedGradeLevels->isEmpty()
                    ? 'All active section grade levels are mapped to a curriculum version.'
                    : $unmappedGradeLevels->count() . ' active grade level(s) missing a curriculum mapping for SY ' . $activeSy->name . '.'),
            'action_label' => 'Curricula & MATATAG',
            'action_url' => route('admin.curricula.index'),
        ];

        // 6. All Active Sections Have Assigned Advisers
        $sectionsWithoutAdviser = $sections->filter(fn (Section $s) => $s->adviser_id === null);
        $checks[] = [
            'title' => 'Every Active Section Has a Class Adviser',
            'passed' => $sections->isNotEmpty() && $sectionsWithoutAdviser->isEmpty(),
            'detail' => $sections->isEmpty()
                ? 'Create sections first to assign class advisers.'
                : ($sectionsWithoutAdviser->isEmpty()
                    ? "All {$sections->count()} active section(s) have an assigned Class Adviser."
                    : $sectionsWithoutAdviser->count() . ' section(s) missing an adviser: ' . $sectionsWithoutAdviser->take(3)->map(fn ($s) => $s->gradeLevel->code . ' ' . $s->name)->implode(', ')),
            'action_label' => 'Teacher Assignments',
            'action_url' => route('admin.assignments.index'),
        ];

        // 7. Subject Teachers Assigned & Valid Grading Configurations
        $unassignedSubjectCount = 0;
        $unconfiguredSubjectCount = 0;
        if ($activeSy) {
            foreach ($sections as $sec) {
                $offerings = $this->curriculumResolver->getOfferingsForSection($sec)->filter(fn ($s) => $s->is_graded);
                $assignedIds = $sec->activeTeacherAssignments->pluck('subject_id')->all();

                foreach ($offerings as $subject) {
                    if (! in_array($subject->id, $assignedIds, true)) {
                        $unassignedSubjectCount++;
                    }
                    $cfg = $this->configResolver->resolve($activeSy->id, $subject->curriculum_id, $sec->grade_level_id, $subject);
                    if (! $cfg) {
                        $unconfiguredSubjectCount++;
                    }
                }
            }
        }

        $checks[] = [
            'title' => 'All Graded Subjects Assigned to Teachers',
            'passed' => $sections->isNotEmpty() && $unassignedSubjectCount === 0,
            'detail' => $sections->isEmpty()
                ? 'No active sections to inspect.'
                : ($unassignedSubjectCount === 0
                    ? '100% of graded curriculum offerings have an active assigned Subject Teacher.'
                    : "{$unassignedSubjectCount} subject offering(s) across active sections still need a Subject Teacher."),
            'action_label' => 'Assign Teachers',
            'action_url' => route('admin.assignments.index'),
        ];

        $checks[] = [
            'title' => 'Grading Policies & Recent Backup Verified',
            'passed' => $unconfiguredSubjectCount === 0 && DatabaseBackup::query()->where('created_at', '>=', now()->subDays(7))->exists(),
            'detail' => $unconfiguredSubjectCount > 0
                ? "{$unconfiguredSubjectCount} subject offering(s) do not match an active Grading Configuration."
                : (DatabaseBackup::query()->where('created_at', '>=', now()->subDays(7))->exists()
                    ? 'All subjects resolve to active grading policies and a database backup exists within the last 7 days.'
                    : 'All subjects resolve to grading policies, but no database backup was recorded in the past 7 days.'),
            'action_label' => $unconfiguredSubjectCount > 0 ? 'Grading Policies' : 'Database Backups',
            'action_url' => $unconfiguredSubjectCount > 0 ? route('admin.grading-policies.index') : route('admin.backups.index'),
        ];

        $passedCount = collect($checks)->where('passed', true)->count();
        $totalChecks = count($checks);
        $scorePercent = $totalChecks > 0 ? (int) round(($passedCount / $totalChecks) * 100) : 0;

        return view('admin.readiness.index', [
            'activeSy' => $activeSy,
            'checks' => $checks,
            'passedCount' => $passedCount,
            'totalChecks' => $totalChecks,
            'scorePercent' => $scorePercent,
        ]);
    }
}
