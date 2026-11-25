<?php

namespace Tests\Feature;

use App\Models\Curriculum;
use App\Models\CurriculumGradeImplementation;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Database\Seeders\DepEdCurriculumSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Module4StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private SchoolYear $sy;
    private GradeLevel $g7;
    private Curriculum $matatag;
    private Section $secA;
    private Section $secB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DepEdCurriculumSeeder::class);

        $this->admin = User::query()->where('email', 'admin@school.local')->firstOrFail();

        $this->sy = SchoolYear::query()->create([
            'name' => '2026-2027',
            'start_date' => '2026-06-15',
            'end_date' => '2027-03-31',
            'term_structure' => 'trimester',
            'is_active' => true,
        ]);

        $this->g7 = GradeLevel::query()->where('code', 'G7')->firstOrFail();
        $this->matatag = Curriculum::query()->where('code', 'MATATAG-K10-2024')->firstOrFail();

        CurriculumGradeImplementation::query()->create([
            'school_year_id' => $this->sy->id,
            'grade_level_id' => $this->g7->id,
            'curriculum_id' => $this->matatag->id,
            'implementation_status' => 'active',
        ]);

        $this->secA = Section::query()->create([
            'school_year_id' => $this->sy->id,
            'grade_level_id' => $this->g7->id,
            'name' => 'Narra',
            'capacity' => 45,
        ]);

        $this->secB = Section::query()->create([
            'school_year_id' => $this->sy->id,
            'grade_level_id' => $this->g7->id,
            'name' => 'Molave',
            'capacity' => 45,
        ]);
    }

    public function test_admin_can_add_enroll_transfer_and_archive_student_preserving_history(): void
    {
        // Create and enroll student in Section Narra
        $this->actingAs($this->admin)
            ->post(route('admin.students.store'), [
                'student_number' => '2026-0001',
                'lrn' => '109876543210',
                'first_name' => 'Juan',
                'middle_name' => 'Dela',
                'last_name' => 'Cruz',
                'gender' => 'Male',
                'date_of_birth' => '2013-04-12',
                'section_id' => $this->secA->id,
            ])
            ->assertRedirect();

        $student = Student::query()->where('student_number', '2026-0001')->firstOrFail();
        $firstEnrollment = $student->enrollments()->firstOrFail();

        $this->assertSame($this->matatag->id, $firstEnrollment->curriculum_id);
        $this->assertTrue($firstEnrollment->is_current);

        // Transfer student from Section Narra -> Section Molave
        $this->actingAs($this->admin)
            ->post(route('admin.students.transfer', [$student, $firstEnrollment]), [
                'target_section_id' => $this->secB->id,
                'transfer_reason' => 'Parent request for morning schedule',
            ])
            ->assertRedirect(route('admin.students.show', $student));

        // Original enrollment preserved as non-current transferred_section
        $firstEnrollment->refresh();
        $this->assertFalse($firstEnrollment->is_current);
        $this->assertSame(StudentEnrollment::STATUS_TRANSFERRED_SECTION, $firstEnrollment->enrollment_status);

        // New current enrollment linked to original enrollment
        $newEnrollment = $student->currentEnrollment()->firstOrFail();
        $this->assertSame($this->secB->id, $newEnrollment->section_id);
        $this->assertSame($firstEnrollment->id, $newEnrollment->transferred_from_enrollment_id);
        $this->assertSame(2, $student->enrollments()->count());

        // Audit log recorded
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'student.section_transferred',
            'student_id' => $student->id,
            'reason' => 'Parent request for morning schedule',
        ]);

        // Archive student preserves all enrollments
        $this->actingAs($this->admin)
            ->post(route('admin.students.archive', $student))
            ->assertRedirect(route('admin.students.index'));

        $this->assertSame(Student::STATUS_ARCHIVED, $student->fresh()->status);
        $this->assertSame(2, $student->enrollments()->count());
    }

    public function test_bulk_csv_import_handles_valid_and_invalid_rows_cleanly(): void
    {
        $csv = implode("\n", [
            'student_number,lrn,first_name,middle_name,last_name,suffix,gender,date_of_birth',
            '2026-1001,100000000001,Maria,Santos,Reyes,,Female,2013-02-10',
            '2026-1002,100000000002,Pedro,,Garcia,Jr.,Male,2013-08-21',
            '2026-1002,bad-lrn,Duplicate,,Student,,InvalidGender,not-a-date',
        ]);

        $file = UploadedFile::fake()->createWithContent('learners.csv', $csv);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.students.import'), [
                'import_file' => $file,
                'section_id' => $this->secA->id,
            ]);

        $response->assertRedirect(route('admin.students.index'));
        $response->assertSessionHas('import_errors');

        $this->assertDatabaseHas('students', ['student_number' => '2026-1001', 'lrn' => '100000000001']);
        $this->assertDatabaseHas('students', ['student_number' => '2026-1002', 'lrn' => '100000000002']);
        $this->assertSame(2, $this->secA->currentEnrollments()->count());
    }

    public function test_bulk_excel_xlsx_import_succeeds(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['student_number', 'lrn', 'first_name', 'last_name', 'gender', 'date_of_birth'],
            ['2026-2001', '200000000001', 'Sofia', 'Mendoza', 'Female', '2013-06-05'],
        ]);

        $tmpPath = storage_path('framework/testing_students.xlsx');
        (new Xlsx($spreadsheet))->save($tmpPath);

        $uploaded = new UploadedFile($tmpPath, 'students.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($this->admin)
            ->post(route('admin.students.import'), [
                'import_file' => $uploaded,
                'section_id' => $this->secB->id,
            ])
            ->assertRedirect(route('admin.students.index'));

        @unlink($tmpPath);

        $this->assertDatabaseHas('students', ['student_number' => '2026-2001', 'last_name' => 'Mendoza']);
        $this->assertSame(1, $this->secB->currentEnrollments()->count());
    }

    public function test_non_admin_cannot_access_student_management_routes(): void
    {
        $teacherRole = Role::query()->where('name', Role::SUBJECT_TEACHER)->firstOrFail();
        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->roles()->attach($teacherRole->id);

        $this->actingAs($teacher)->get(route('admin.students.index'))->assertForbidden();
        $this->actingAs($teacher)->post(route('admin.students.store'), [])->assertForbidden();
    }
}
