<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\CurriculumGradeImplementation;
use App\Models\GradeLevel;
use App\Models\GradingPeriod;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Curriculum\CurriculumResolverService;
use Database\Seeders\DepEdCurriculumSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationsRemindersAndSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DepEdCurriculumSeeder::class);

        $this->admin = User::query()->where('email', 'admin@school.local')->firstOrFail();

        $user = User::query()->create([
            'name' => 'Grace Navarro',
            'email' => 'grace.teacher@school.local',
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::query()->where('name', Role::SUBJECT_TEACHER)->firstOrFail()->id);

        $this->teacher = Teacher::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'EMP-GR1',
            'first_name' => 'Grace',
            'last_name' => 'Navarro',
            'specialization' => 'Mathematics',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_send_individual_and_bulk_submission_reminders_and_teacher_reads_them_in_notifications_center(): void
    {
        $sy = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);
        app(CurriculumResolverService::class)->seedStandardPeriodsForSchoolYear($sy);
        $t1 = $sy->gradingPeriods()->where('code', 'T1')->firstOrFail();
        $t1->update(['status' => GradingPeriod::STATUS_OPEN]);

        $matatag = Curriculum::query()->where('code', 'MATATAG-K10-2024')->firstOrFail();
        $g7 = GradeLevel::query()->where('code', 'G7')->firstOrFail();
        CurriculumGradeImplementation::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g7->id,
            'curriculum_id' => $matatag->id,
        ]);

        $section = Section::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g7->id,
            'name' => 'Molave',
            'capacity' => 40,
        ]);

        $math = Subject::query()->where('curriculum_id', $matatag->id)->where('grade_level_id', $g7->id)->where('code', 'MATH7')->firstOrFail();

        $assignment = TeacherAssignment::query()->create([
            'school_year_id' => $sy->id,
            'section_id' => $section->id,
            'subject_id' => $math->id,
            'teacher_id' => $this->teacher->id,
            'is_active' => true,
        ]);

        // Individual reminder
        $this->actingAs($this->admin)
            ->post(route('admin.submissions.remind', [$assignment, $t1]))
            ->assertRedirect();

        $this->assertSame(1, $this->teacher->user->unreadNotifications()->count());

        // Bulk remind all pending
        $this->actingAs($this->admin)
            ->post(route('admin.submissions.remind-all', $t1))
            ->assertRedirect();

        $this->assertSame(2, $this->teacher->user->unreadNotifications()->count());
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'grade_submission.reminder_sent']);
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'grade_submission.bulk_reminder_sent']);

        // Teacher views Notifications Center and marks one read (with redirect to gradebook)
        $this->actingAs($this->teacher->user)
            ->get(route('notifications.index', ['filter' => 'unread']))
            ->assertOk()
            ->assertSee('Reminder: Submit T1 Grades');

        $firstNotif = $this->teacher->user->unreadNotifications()->firstOrFail();
        $this->actingAs($this->teacher->user)
            ->post(route('notifications.read', ['notification' => $firstNotif->id, 'redirect' => 1]))
            ->assertRedirect(route('teacher.gradebook.show', [$assignment, $t1]));

        $this->assertSame(1, $this->teacher->user->fresh()->unreadNotifications()->count());
    }

    public function test_admin_quick_search_returns_students_teachers_and_sections_while_blocking_non_admins(): void
    {
        $sy = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);
        $g7 = GradeLevel::query()->where('code', 'G7')->firstOrFail();
        Section::query()->create([
            'school_year_id' => $sy->id,
            'grade_level_id' => $g7->id,
            'name' => 'Acacia',
            'capacity' => 40,
        ]);

        Student::query()->create([
            'student_number' => '2026-7777',
            'lrn' => '100000007777',
            'first_name' => 'Miguel',
            'last_name' => 'Acacia-Luna',
            'gender' => 'Male',
            'date_of_birth' => '2013-04-10',
            'status' => Student::STATUS_ACTIVE,
        ]);

        // Admin searches by LRN / name / section
        $this->actingAs($this->admin)
            ->getJson(route('admin.quick-search', ['q' => 'Acacia']))
            ->assertOk()
            ->assertJsonFragment(['group' => 'Student'])
            ->assertJsonFragment(['group' => 'Section']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.quick-search', ['q' => 'Navarro']))
            ->assertOk()
            ->assertJsonFragment(['group' => 'Teacher', 'label' => 'Grace Navarro']);

        // Non-admin is blocked
        $this->actingAs($this->teacher->user)
            ->getJson(route('admin.quick-search', ['q' => 'Acacia']))
            ->assertForbidden();
    }
}
