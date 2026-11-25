<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Curriculum;
use App\Models\CurriculumGradeImplementation;
use App\Models\GradeLevel;
use App\Models\GradeSubmission;
use App\Models\GradingConfiguration;
use App\Models\GradingPeriod;
use App\Models\QuarterlyGrade;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Academic\TeacherAssignmentService;
use App\Services\Curriculum\CurriculumResolverService;
use App\Services\Grading\GradebookService;
use App\Services\Grading\GradeSubmissionService;
use App\Services\Students\EnrollmentService;
use Database\Seeders\DepEdCurriculumSeeder;
use Database\Seeders\DepEdGradingPolicySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Module6GradebookTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private GradingPeriod $t1;
    private Section $section;
    private Teacher $adviser;
    private Teacher $mathTeacher;
    private TeacherAssignment $mathAssignment;
    private Student $student;
    private int $enrollmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DepEdCurriculumSeeder::class);
        $this->seed(DepEdGradingPolicySeeder::class);

        $this->sy = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);

        app(CurriculumResolverService::class)->seedStandardPeriodsForSchoolYear($this->sy);
        $this->t1 = $this->sy->gradingPeriods()->where('code', 'T1')->firstOrFail();
        $this->t1->update(['status' => GradingPeriod::STATUS_OPEN]);

        $matatag = Curriculum::query()->where('code', 'MATATAG-K10-2024')->firstOrFail();
        $g7 = GradeLevel::query()->where('code', 'G7')->firstOrFail();

        CurriculumGradeImplementation::query()->create([
            'school_year_id' => $this->sy->id,
            'grade_level_id' => $g7->id,
            'curriculum_id' => $matatag->id,
        ]);

        $this->adviser = $this->makeTeacher('EMP-A1', 'Adviser', 'One', 'adviser@school.local', Role::ADVISER);
        $this->mathTeacher = $this->makeTeacher('EMP-M1', 'Math', 'Teacher', 'math@school.local', Role::SUBJECT_TEACHER);

        $this->section = Section::query()->create([
            'school_year_id' => $this->sy->id,
            'grade_level_id' => $g7->id,
            'adviser_id' => $this->adviser->id,
            'name' => 'Narra',
            'capacity' => 45,
        ]);

        $math7 = Subject::query()->where('curriculum_id', $matatag->id)->where('grade_level_id', $g7->id)->where('code', 'MATH7')->firstOrFail();

        $this->mathAssignment = TeacherAssignment::query()->create([
            'school_year_id' => $this->sy->id,
            'section_id' => $this->section->id,
            'subject_id' => $math7->id,
            'teacher_id' => $this->mathTeacher->id,
            'is_active' => true,
        ]);

        $this->student = Student::query()->create([
            'student_number' => '2026-0001',
            'lrn' => '100000000001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'gender' => 'Male',
            'date_of_birth' => '2013-05-01',
            'status' => Student::STATUS_ACTIVE,
        ]);

        $enrollment = app(EnrollmentService::class)->enrollStudent($this->student, $this->section);
        $this->enrollmentId = $enrollment->id;
    }

    private function makeTeacher(string $empNo, string $first, string $last, string $email, string $roleName): Teacher
    {
        $user = User::query()->create([
            'name' => "{$first} {$last}",
            'email' => $email,
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]);
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role->id);

        return Teacher::query()->create([
            'user_id' => $user->id,
            'employee_number' => $empNo,
            'first_name' => $first,
            'last_name' => $last,
            'is_active' => true,
        ]);
    }

    public function test_subject_teacher_can_create_assessments_encode_scores_auto_compute_and_audit(): void
    {
        $config = app(GradebookService::class)->resolveConfiguration($this->mathAssignment);
        $leaves = app(GradebookService::class)->getLeafComponents($config)->keyBy('code');

        // Create WW, PT, ST1, ST2, TE assessments (DO 015 MATATAG Math)
        foreach ([
            ['WW', 'quiz', 'Quiz 1', 20],
            ['PT', 'performance_task', 'Math Portfolio', 50],
            ['EX-ST1', 'summative_test', 'Summative 1', 50],
            ['EX-ST2', 'summative_test', 'Summative 2', 50],
            ['EX-TE', 'examination', 'Term Exam', 100],
        ] as [$code, $type, $title, $max]) {
            $this->actingAs($this->mathTeacher->user)
                ->post(route('teacher.gradebook.assessments.store', [$this->mathAssignment, $this->t1]), [
                    'grading_component_id' => $leaves[$code]['id'],
                    'assessment_type' => $type,
                    'title' => $title,
                    'max_score' => $max,
                ])
                ->assertRedirect();
        }

        $assessments = Assessment::query()->where('teacher_assignment_id', $this->mathAssignment->id)->get()->keyBy('title');

        // Reject score exceeding max_score (25 > 20)
        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.scores.save', [$this->mathAssignment, $this->t1]), [
                'scores' => [
                    $this->enrollmentId => [
                        $assessments['Quiz 1']->id => 25,
                    ],
                ],
            ])
            ->assertSessionHasErrors();

        // Save valid scores: WW 16/20 (80%*20=16), PT 45/50 (90%*50=45), EX: ST1 40/50 (80%*30=24) + ST2 45/50 (90%*30=27) + TE 85/100 (85%*40=34) => EX 85%*30=25.5 => IG=86.50
        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.scores.save', [$this->mathAssignment, $this->t1]), [
                'scores' => [
                    $this->enrollmentId => [
                        $assessments['Quiz 1']->id => 16,
                        $assessments['Math Portfolio']->id => 45,
                        $assessments['Summative 1']->id => 40,
                        $assessments['Summative 2']->id => 45,
                        $assessments['Term Exam']->id => 85,
                    ],
                ],
            ])
            ->assertRedirect();

        $qg = QuarterlyGrade::query()
            ->where('student_enrollment_id', $this->enrollmentId)
            ->where('grading_period_id', $this->t1->id)
            ->firstOrFail();

        $this->assertSame(86.5, $qg->initial_grade);
        $this->assertSame(0, $qg->missing_scores_count);

        // Update Quiz 1 from 16 -> 18 and confirm audit log captures previous & updated values
        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.scores.save', [$this->mathAssignment, $this->t1]), [
                'scores' => [
                    $this->enrollmentId => [
                        $assessments['Quiz 1']->id => 18,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'score.updated',
            'teacher_id' => $this->mathTeacher->id,
            'student_id' => $this->student->id,
            'subject_id' => $this->mathAssignment->subject_id,
            'previous_grade' => '16',
            'updated_grade' => '18',
        ]);
    }

    public function test_adviser_cannot_edit_another_subject_teachers_gradebook_and_locked_period_blocks_edits(): void
    {
        // Adviser of the same section CANNOT open or post to Math Teacher's gradebook -> 403
        $this->actingAs($this->adviser->user)
            ->get(route('teacher.gradebook.show', [$this->mathAssignment, $this->t1]))
            ->assertForbidden();

        $this->actingAs($this->adviser->user)
            ->post(route('teacher.gradebook.scores.save', [$this->mathAssignment, $this->t1]), ['scores' => []])
            ->assertForbidden();

        // Lock the grading period -> Math Teacher can no longer edit scores
        $this->t1->update(['status' => GradingPeriod::STATUS_LOCKED, 'locked_at' => now()]);

        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.scores.save', [$this->mathAssignment, $this->t1]), ['scores' => []])
            ->assertSessionHasErrors('grading_period_id');
    }

    public function test_excel_score_import_and_grade_sheet_export_work(): void
    {
        $config = app(GradebookService::class)->resolveConfiguration($this->mathAssignment);
        $wwCompId = app(GradebookService::class)->getLeafComponents($config)->firstWhere('code', 'WW')['id'];

        $assessment = Assessment::query()->create([
            'teacher_assignment_id' => $this->mathAssignment->id,
            'grading_period_id' => $this->t1->id,
            'grading_component_id' => $wwCompId,
            'title' => 'Quiz 1',
            'assessment_type' => 'quiz',
            'max_score' => 25,
        ]);

        // Build Excel file matching template format
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['student_number', 'learner_name', "A#{$assessment->id}: Quiz 1 (Max 25)"],
            ['2026-0001', 'Dela Cruz, Juan', 22],
        ]);
        $tmp = storage_path('framework/testing_scores.xlsx');
        (new Xlsx($spreadsheet))->save($tmp);

        $file = new UploadedFile($tmp, 'scores.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.import', [$this->mathAssignment, $this->t1]), [
                'score_file' => $file,
            ])
            ->assertRedirect();

        @unlink($tmp);

        $this->assertDatabaseHas('student_assessment_scores', [
            'assessment_id' => $assessment->id,
            'student_enrollment_id' => $this->enrollmentId,
            'raw_score' => 22,
        ]);

        // Printable & Excel export endpoints work for owner teacher
        $this->actingAs($this->mathTeacher->user)
            ->get(route('teacher.gradebook.print', [$this->mathAssignment, $this->t1]))
            ->assertOk();

        $this->actingAs($this->mathTeacher->user)
            ->get(route('teacher.gradebook.export', [$this->mathAssignment, $this->t1]))
            ->assertOk();
    }

    public function test_reassigned_subject_teacher_sees_prior_locked_t1_submission_and_can_compute_after_admin_return(): void
    {
        $config = app(GradebookService::class)->resolveConfiguration($this->mathAssignment);
        $wwCompId = app(GradebookService::class)->getLeafComponents($config)->firstWhere('code', 'WW')['id'];

        // Teacher 1 creates an assessment, enters score, and submits official T1 grades
        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.assessments.store', [$this->mathAssignment, $this->t1]), [
                'grading_component_id' => $wwCompId,
                'assessment_type' => 'quiz',
                'title' => 'T1 Quiz',
                'max_score' => 20,
            ])
            ->assertRedirect();

        $t1Quiz = Assessment::query()->where('teacher_assignment_id', $this->mathAssignment->id)->firstOrFail();

        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.scores.save', [$this->mathAssignment, $this->t1]), [
                'scores' => [$this->enrollmentId => [$t1Quiz->id => 18]],
            ])
            ->assertRedirect();

        $this->actingAs($this->mathTeacher->user)
            ->post(route('teacher.gradebook.submit', [$this->mathAssignment, $this->t1]))
            ->assertRedirect();

        $priorSubmission = GradeSubmission::query()
            ->where('teacher_assignment_id', $this->mathAssignment->id)
            ->where('grading_period_id', $this->t1->id)
            ->firstOrFail();

        // Admin reassigns the subject to a new Subject Teacher (Teacher 2) and opens T2
        $admin = User::query()->create([
            'name' => 'School Admin',
            'email' => 'admin-reassign@school.local',
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]);
        $admin->roles()->attach(Role::query()->where('name', Role::ADMIN)->firstOrFail()->id);

        $newTeacher = $this->makeTeacher('EMP-M2', 'New', 'MathTeacher', 'math2@school.local', Role::SUBJECT_TEACHER);
        $newAssignment = app(TeacherAssignmentService::class)->assignSubjectTeacher(
            $this->section,
            $this->mathAssignment->subject,
            $newTeacher
        );

        $t2 = $this->sy->gradingPeriods()->where('code', 'T2')->firstOrFail();
        $t2->update(['status' => GradingPeriod::STATUS_OPEN]);

        // 1. New teacher sees T1 as Submitted & Locked (Prior Teacher) — cannot silently add assessments/scores in T1
        $this->actingAs($newTeacher->user)
            ->get(route('teacher.gradebook.show', [$newAssignment, $this->t1]))
            ->assertOk()
            ->assertSee('Submitted &amp; Locked (Prior Teacher)', false)
            ->assertSee($this->mathTeacher->full_name);

        $this->actingAs($newTeacher->user)
            ->post(route('teacher.gradebook.assessments.store', [$newAssignment, $this->t1]), [
                'grading_component_id' => $wwCompId,
                'assessment_type' => 'quiz',
                'title' => 'Blocked Quiz',
                'max_score' => 10,
            ])
            ->assertSessionHasErrors('grading_period_id');

        // 2. New teacher CAN immediately add assessments and compute grades in T2
        $this->actingAs($newTeacher->user)
            ->post(route('teacher.gradebook.assessments.store', [$newAssignment, $t2]), [
                'grading_component_id' => $wwCompId,
                'assessment_type' => 'quiz',
                'title' => 'T2 Quiz',
                'max_score' => 10,
            ])
            ->assertRedirect();

        $t2Quiz = Assessment::query()->where('teacher_assignment_id', $newAssignment->id)->where('grading_period_id', $t2->id)->firstOrFail();
        $this->actingAs($newTeacher->user)
            ->post(route('teacher.gradebook.scores.save', [$newAssignment, $t2]), [
                'scores' => [$this->enrollmentId => [$t2Quiz->id => 10]],
            ])
            ->assertRedirect();

        $t2Grade = QuarterlyGrade::query()
            ->where('student_enrollment_id', $this->enrollmentId)
            ->where('subject_id', $newAssignment->subject_id)
            ->where('grading_period_id', $t2->id)
            ->firstOrFail();
        $this->assertNotNull($t2Grade->final_grade);

        // 3. Admin Submissions Monitor & Adviser Collection Matrix both recognize prior official T1 submission
        $this->actingAs($admin)
            ->get(route('admin.submissions.index', ['school_year_id' => $this->sy->id, 'grading_period_id' => $this->t1->id]))
            ->assertOk()
            ->assertSee('Submitted by prior teacher: ' . $this->mathTeacher->full_name);

        $matrix = app(GradeSubmissionService::class)->getSectionCollectionMatrix($this->section, $this->t1);
        $mathRow = $matrix['subject_rows']->firstWhere('subject.id', $newAssignment->subject_id);
        $this->assertTrue($mathRow['is_official']);
        $this->assertSame($this->mathTeacher->full_name, $mathRow['submitted_by_prior_teacher']);

        // 4. Admin returns prior T1 submission -> unlocks T1 so new teacher can now encode & compute T1!
        $this->actingAs($admin)
            ->post(route('admin.submissions.return', $priorSubmission), [
                'reason' => 'Reassigned subject teacher will re-encode T1 scores.',
            ])
            ->assertRedirect();

        $this->actingAs($newTeacher->user)
            ->post(route('teacher.gradebook.assessments.store', [$newAssignment, $this->t1]), [
                'grading_component_id' => $wwCompId,
                'assessment_type' => 'quiz',
                'title' => 'New Teacher T1 Quiz',
                'max_score' => 10,
            ])
            ->assertRedirect();

        $newT1Quiz = Assessment::query()
            ->where('teacher_assignment_id', $newAssignment->id)
            ->where('grading_period_id', $this->t1->id)
            ->firstOrFail();

        $this->actingAs($newTeacher->user)
            ->post(route('teacher.gradebook.scores.save', [$newAssignment, $this->t1]), [
                'scores' => [$this->enrollmentId => [$newT1Quiz->id => 10]],
            ])
            ->assertRedirect();

        $recomputedT1 = QuarterlyGrade::query()
            ->where('student_enrollment_id', $this->enrollmentId)
            ->where('subject_id', $newAssignment->subject_id)
            ->where('grading_period_id', $this->t1->id)
            ->firstOrFail();

        $this->assertSame($newAssignment->id, (int) $recomputedT1->teacher_assignment_id);
        $this->assertSame(20.0, $recomputedT1->initial_grade);
        $this->assertFalse($recomputedT1->is_locked);
    }
}
