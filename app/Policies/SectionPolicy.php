<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;

class SectionPolicy
{
    public function viewAdvisory(User $user, Section $section): bool
    {
        if (! $user->teacher || ! $user->teacher->is_active) {
            return false;
        }

        return (int) $section->adviser_id === (int) $user->teacher->id;
    }
}
