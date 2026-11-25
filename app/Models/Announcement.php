<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    public const CATEGORY_MEMO = 'memo';
    public const CATEGORY_DEADLINE = 'deadline';
    public const CATEGORY_EVENT = 'event';
    public const CATEGORY_GENERAL = 'general';

    public const AUDIENCE_ALL = 'all';
    public const AUDIENCE_FACULTY = 'faculty';
    public const AUDIENCE_ADVISERS = 'advisers';
    public const AUDIENCE_SUBJECT_TEACHERS = 'subject_teachers';

    protected $fillable = [
        'title',
        'body',
        'category',
        'audience',
        'is_pinned',
        'is_published',
        'starts_at',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_published' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_published', true)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');
    }

    public function scopeForPublic(Builder $query): Builder
    {
        return $query->active()->where('audience', self::AUDIENCE_ALL);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        $query->active();

        if ($user->isAdmin()) {
            return $query;
        }

        $allowed = [self::AUDIENCE_ALL, self::AUDIENCE_FACULTY];
        if ($user->isAdviser()) {
            $allowed[] = self::AUDIENCE_ADVISERS;
        }
        if ($user->hasRole(Role::SUBJECT_TEACHER) || $user->isAdviser()) {
            $allowed[] = self::AUDIENCE_SUBJECT_TEACHERS;
        }

        return $query->whereIn('audience', array_unique($allowed));
    }

    public static function audienceLabels(): array
    {
        return [
            self::AUDIENCE_ALL => 'Public & All Faculty',
            self::AUDIENCE_FACULTY => 'All Faculty (Internal)',
            self::AUDIENCE_ADVISERS => 'Section Advisers Only',
            self::AUDIENCE_SUBJECT_TEACHERS => 'Subject Teachers Only',
        ];
    }

    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_MEMO => 'Official Memo',
            self::CATEGORY_DEADLINE => 'Grading Deadline',
            self::CATEGORY_EVENT => 'School Event',
            self::CATEGORY_GENERAL => 'General Notice',
        ];
    }
}
