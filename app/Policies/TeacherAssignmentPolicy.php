<?php

namespace App\Policies;

use App\Models\TeacherAssignment;
use App\Models\User;

class TeacherAssignmentPolicy
{
    /**
     * Subject teacher owns their assigned class; Advisers CANNOT edit another teacher's subject.
     */
    public function manage(User $user, TeacherAssignment $assignment): bool
    {
        if (! $user->teacher || ! $user->teacher->is_active) {
            return false;
        }

        return $assignment->is_active
            && (int) $assignment->teacher_id === (int) $user->teacher->id;
    }

    /**
     * Read-only viewing is permitted for the assigned subject teacher OR the section's adviser.
     */
    public function view(User $user, TeacherAssignment $assignment): bool
    {
        if (! $user->teacher || ! $user->teacher->is_active) {
            return false;
        }

        if ((int) $assignment->teacher_id === (int) $user->teacher->id) {
            return true;
        }

        $section = $assignment->relationLoaded('section')
            ? $assignment->section
            : $assignment->section()->first();

        return $section && (int) $section->adviser_id === (int) $user->teacher->id;
    }
}
