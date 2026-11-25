<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnnouncementsAndExportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Teacher $adviser;
    private Teacher $subjectTeacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::query()->where('email', 'admin@school.local')->firstOrFail();
        $this->adviser = $this->makeTeacher('EMP-A10', 'Nora', 'Santos', 'nora.adv@school.local', Role::ADVISER);
        $this->subjectTeacher = $this->makeTeacher('EMP-S10', 'Joel', 'Ramos', 'joel.sub@school.local', Role::SUBJECT_TEACHER);
    }

    private function makeTeacher(string $empNo, string $first, string $last, string $email, string $roleName): Teacher
    {
        $user = User::query()->create([
            'name' => "{$first} {$last}",
            'email' => $email,
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail()->id);

        return Teacher::query()->create([
            'user_id' => $user->id,
            'employee_number' => $empNo,
            'first_name' => $first,
            'last_name' => $last,
            'is_active' => true,
        ]);
    }

    public function test_announcements_respect_public_and_role_audience_targeting(): void
    {
        // Admin creates 3 notices: Public, Advisers-Only, and Internal Faculty
        $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'Public Recognition Day Advisory',
                'body' => 'Parents and guardians are invited on Friday at 9:00 AM.',
                'category' => Announcement::CATEGORY_EVENT,
                'audience' => Announcement::AUDIENCE_ALL,
                'is_pinned' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'Advisers-Only SF9 Verification Memo',
                'body' => 'Check attendance totals before printing batch report cards.',
                'category' => Announcement::CATEGORY_MEMO,
                'audience' => Announcement::AUDIENCE_ADVISERS,
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'Internal T1 Grade Deadline',
                'body' => 'All subject teachers must submit T1 grades by 5 PM.',
                'category' => Announcement::CATEGORY_DEADLINE,
                'audience' => Announcement::AUDIENCE_FACULTY,
            ])
            ->assertRedirect();

        // Public homepage ONLY shows audience=all notices — NEVER internal faculty/adviser memos
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Public Recognition Day Advisory')
            ->assertDontSee('Advisers-Only SF9 Verification Memo')
            ->assertDontSee('Internal T1 Grade Deadline');

        // Adviser sees Public + Faculty + Advisers-Only
        $this->actingAs($this->adviser->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Public Recognition Day Advisory')
            ->assertSee('Advisers-Only SF9 Verification Memo')
            ->assertSee('Internal T1 Grade Deadline');

        // Subject Teacher sees Public + Faculty, NOT Advisers-Only
        $this->actingAs($this->subjectTeacher->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Public Recognition Day Advisory')
            ->assertSee('Internal T1 Grade Deadline')
            ->assertDontSee('Advisers-Only SF9 Verification Memo');

        // Subject Teacher cannot manage announcements
        $this->actingAs($this->subjectTeacher->user)
            ->get(route('admin.announcements.index'))
            ->assertForbidden();

        // Admin can toggle publish/pin and delete with audit logs
        $memo = Announcement::query()->where('title', 'Advisers-Only SF9 Verification Memo')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.announcements.pin', $memo))->assertRedirect();
        $this->assertTrue($memo->fresh()->is_pinned);

        $this->actingAs($this->admin)->post(route('admin.announcements.toggle', $memo))->assertRedirect();
        $this->assertFalse($memo->fresh()->is_published);

        $this->assertDatabaseHas('audit_logs', ['event_type' => 'announcement.created']);
    }

    public function test_admin_can_export_student_master_list_excel_and_audit_log_csv(): void
    {
        Student::query()->create([
            'student_number' => '2026-9001',
            'lrn' => '100000009001',
            'first_name' => 'Sofia',
            'last_name' => 'Gomez',
            'gender' => 'Female',
            'date_of_birth' => '2013-02-14',
            'status' => Student::STATUS_ACTIVE,
        ]);

        // Admin Excel Student Directory export succeeds
        $this->actingAs($this->admin)
            ->get(route('admin.students.export'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Admin CSV Audit Log export succeeds
        $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // Non-admin teachers are forbidden from both exports
        $this->actingAs($this->subjectTeacher->user)
            ->get(route('admin.students.export'))
            ->assertForbidden();

        $this->actingAs($this->subjectTeacher->user)
            ->get(route('admin.audit-logs.export'))
            ->assertForbidden();
    }
}
