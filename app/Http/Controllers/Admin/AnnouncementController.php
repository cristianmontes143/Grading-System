<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function index(): View
    {
        $announcements = Announcement::query()
            ->with('author')
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate(15);

        return view('admin.announcements.index', [
            'announcements' => $announcements,
            'audienceLabels' => Announcement::audienceLabels(),
            'categoryLabels' => Announcement::categoryLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:3000'],
            'category' => ['required', Rule::in(array_keys(Announcement::categoryLabels()))],
            'audience' => ['required', Rule::in(array_keys(Announcement::audienceLabels()))],
            'is_pinned' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $announcement = Announcement::query()->create([
            'title' => trim($validated['title']),
            'body' => trim($validated['body']),
            'category' => $validated['category'],
            'audience' => $validated['audience'],
            'is_pinned' => $request->boolean('is_pinned'),
            'is_published' => $request->has('is_published') ? $request->boolean('is_published') : true,
            'expires_at' => ! empty($validated['expires_at']) ? $validated['expires_at'] : null,
            'created_by' => $request->user()->id,
        ]);

        $this->auditLog->record(
            eventType: 'announcement.created',
            auditable: $announcement,
            newValues: $announcement->only(['title', 'category', 'audience', 'is_pinned', 'is_published']),
            actor: $request->user()
        );

        return redirect()
            ->route('admin.announcements.index')
            ->with('status', "Announcement \"{$announcement->title}\" published.");
    }

    public function togglePublish(Request $request, Announcement $announcement): RedirectResponse
    {
        $old = $announcement->is_published;
        $announcement->update(['is_published' => ! $old]);

        $this->auditLog->record(
            eventType: 'announcement.publish_toggled',
            auditable: $announcement,
            oldValues: ['is_published' => $old],
            newValues: ['is_published' => $announcement->is_published],
            actor: $request->user()
        );

        return back()->with('status', $announcement->is_published ? 'Announcement published.' : 'Announcement unpublished (draft).');
    }

    public function togglePin(Request $request, Announcement $announcement): RedirectResponse
    {
        $old = $announcement->is_pinned;
        $announcement->update(['is_pinned' => ! $old]);

        $this->auditLog->record(
            eventType: 'announcement.pin_toggled',
            auditable: $announcement,
            oldValues: ['is_pinned' => $old],
            newValues: ['is_pinned' => $announcement->is_pinned],
            actor: $request->user()
        );

        return back()->with('status', $announcement->is_pinned ? 'Announcement pinned to top.' : 'Announcement unpinned.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        $snapshot = $announcement->only(['id', 'title', 'audience']);
        $announcement->delete();

        $this->auditLog->record(
            eventType: 'announcement.deleted',
            oldValues: $snapshot,
            actor: $request->user()
        );

        return back()->with('status', 'Announcement removed.');
    }
}
