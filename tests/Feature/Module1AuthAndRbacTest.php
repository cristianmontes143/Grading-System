<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class Module1AuthAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertOk();
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_admin_can_sign_in_and_audit_log_is_recorded(): void
    {
        $admin = User::query()->where('email', 'admin@school.local')->firstOrFail();

        $response = $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'Admin@12345',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'auth.login',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_deactivated_user_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@school.local',
            'password' => Hash::make('Secret123!'),
            'is_active' => false,
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Secret123!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $email = 'teacher@school.local';

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), [
                'email' => $email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post(route('login.store'), [
            'email' => $email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }

    public function test_non_admin_teachers_are_blocked_from_admin_routes(): void
    {
        $adviserRole = Role::query()->where('name', Role::ADVISER)->firstOrFail();
        $subjectRole = Role::query()->where('name', Role::SUBJECT_TEACHER)->firstOrFail();

        $adviser = User::factory()->create(['is_active' => true]);
        $adviser->roles()->attach($adviserRole->id);

        $subjectTeacher = User::factory()->create(['is_active' => true]);
        $subjectTeacher->roles()->attach($subjectRole->id);

        $this->actingAs($adviser)->get(route('dashboard'))->assertOk();
        $this->actingAs($adviser)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($adviser)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($adviser)->get(route('admin.audit-logs.index'))->assertForbidden();

        $this->actingAs($subjectTeacher)->get(route('dashboard'))->assertOk();
        $this->actingAs($subjectTeacher)->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_admin_can_update_school_settings_and_role_permissions(): void
    {
        $admin = User::query()->where('email', 'admin@school.local')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'school_name' => 'Rizal National High School',
                'school_id_code' => '301456',
                'region' => 'Region IV-A',
                'division' => 'Division of Rizal',
                'principal_name' => 'Dr. Maria Clara Santos',
                'principal_title' => 'School Principal IV',
                'default_calendar_mode' => 'trimester',
                'attendance_enabled' => '1',
                'observed_values_enabled' => '1',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertDatabaseHas('school_settings', [
            'school_name' => 'Rizal National High School',
            'school_id_code' => '301456',
            'default_calendar_mode' => 'trimester',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'school_settings.updated',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_audit_logs_are_immutable(): void
    {
        $log = AuditLog::create([
            'event_type' => 'grade.updated',
            'previous_grade' => '85',
            'updated_grade' => '88',
            'reason' => 'Clerical correction',
            'created_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $log->update(['updated_grade' => '99']);
    }

    public function test_role_permissions_enforce_adviser_vs_subject_teacher_boundaries(): void
    {
        $adviserRole = Role::query()->where('name', Role::ADVISER)->firstOrFail();
        $subjectRole = Role::query()->where('name', Role::SUBJECT_TEACHER)->firstOrFail();

        $adviser = User::factory()->create(['is_active' => true]);
        $adviser->roles()->attach($adviserRole->id);

        $subjectTeacher = User::factory()->create(['is_active' => true]);
        $subjectTeacher->roles()->attach($subjectRole->id);

        $this->assertTrue($adviser->hasPermission('grades.view_advisory'));
        $this->assertTrue($adviser->hasPermission('report_cards.generate'));
        $this->assertTrue($adviser->hasPermission('grades.encode_own'));
        $this->assertFalse($adviser->hasPermission('grades.override'));
        $this->assertFalse($adviser->hasPermission('periods.lock'));

        $this->assertTrue($subjectTeacher->hasPermission('grades.encode_own'));
        $this->assertTrue($subjectTeacher->hasPermission('grades.submit_own'));
        $this->assertFalse($subjectTeacher->hasPermission('grades.view_advisory'));
        $this->assertFalse($subjectTeacher->hasPermission('report_cards.generate'));
    }
}
