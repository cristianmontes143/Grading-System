@extends('layouts.app')

@section('title', 'School Announcements & Memos')
@section('header', 'School Announcements & Academic Bulletin')
@section('subheader', 'Post grade submission deadlines, faculty memos, and public school advisories with audience targeting.')

@section('content')
<div class="space-y-6">
    {{-- Post New Announcement Form --}}
    <div class="school-card p-6">
        <h2 class="text-base font-bold text-slate-900 mb-4">Post New Announcement / Memo</h2>
        <form method="POST" action="{{ route('admin.announcements.store') }}" class="grid grid-cols-1 md:grid-cols-12 gap-4">
            @csrf
            <div class="md:col-span-6">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Title <span class="text-rose-600">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="180"
                       placeholder="e.g., First Term Grade Submission Deadline — Friday 5:00 PM"
                       class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Category</label>
                <select name="category" class="w-full rounded-lg border-slate-300 text-sm">
                    @foreach($categoryLabels as $val => $label)
                        <option value="{{ $val }}" @selected(old('category', 'memo') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Target Audience</label>
                <select name="audience" class="w-full rounded-lg border-slate-300 text-sm">
                    @foreach($audienceLabels as $val => $label)
                        <option value="{{ $val }}" @selected(old('audience', 'faculty') === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-9">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Message / Details <span class="text-rose-600">*</span></label>
                <textarea name="body" rows="3" required maxlength="3000"
                          placeholder="Provide clear instructions, dates, or reminders for faculty or parents..."
                          class="w-full rounded-lg border-slate-300 text-sm">{{ old('body') }}</textarea>
            </div>
            <div class="md:col-span-3 flex flex-col justify-between gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Expires On (Optional)</label>
                    <input type="date" name="expires_at" value="{{ old('expires_at') }}" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700">
                    <input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned')) class="rounded border-slate-300 text-school-600">
                    Pin to top of bulletin
                </label>
                <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-school-600 hover:bg-school-700 text-white text-sm font-semibold shadow-sm transition">
                    Publish Announcement
                </button>
            </div>
        </form>
    </div>

    {{-- Announcements List --}}
    <div class="school-card overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900">Posted Bulletins ({{ $announcements->total() }})</h2>
            <span class="text-xs text-slate-500">Only "Public &amp; All Faculty" notices appear on the public homepage.</span>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($announcements as $item)
                @php
                    $catClass = match ($item->category) {
                        'deadline' => 'bg-rose-100 text-rose-800',
                        'event' => 'bg-emerald-100 text-emerald-800',
                        'memo' => 'bg-school-100 text-school-800',
                        default => 'bg-slate-100 text-slate-700',
                    };
                @endphp
                <div class="p-5 flex flex-col md:flex-row md:items-start justify-between gap-4 {{ ! $item->is_published ? 'bg-slate-50/70 opacity-75' : '' }}">
                    <div class="space-y-1.5 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($item->is_pinned)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900">PINNED</span>
                            @endif
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $catClass }}">
                                {{ $categoryLabels[$item->category] ?? ucfirst($item->category) }}
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700">
                                {{ $audienceLabels[$item->audience] ?? $item->audience }}
                            </span>
                            @if(! $item->is_published)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-600">DRAFT</span>
                            @endif
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">{{ $item->title }}</h3>
                        <p class="text-xs text-slate-600 whitespace-pre-line leading-relaxed">{{ $item->body }}</p>
                        <p class="text-[11px] text-slate-400">
                            Posted by {{ $item->author?->name ?? 'Administrator' }} &bull; {{ $item->created_at->format('M d, Y h:i A') }}
                            @if($item->expires_at) &bull; Expires {{ $item->expires_at->format('M d, Y') }} @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <form method="POST" action="{{ route('admin.announcements.pin', $item) }}">
                            @csrf
                            <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-amber-100 text-slate-700 text-xs font-semibold">
                                {{ $item->is_pinned ? 'Unpin' : 'Pin' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.announcements.toggle', $item) }}">
                            @csrf
                            <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-school-100 text-slate-700 text-xs font-semibold">
                                {{ $item->is_published ? 'Unpublish' : 'Publish' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.announcements.destroy', $item) }}" onsubmit="return confirm('Delete this announcement?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-semibold">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-sm text-slate-500">
                    No announcements posted yet. Use the form above to share memos or submission deadlines.
                </div>
            @endforelse
        </div>
        @if($announcements->hasPages())
            <div class="px-6 py-3 border-t border-slate-200">{{ $announcements->links() }}</div>
        @endif
    </div>
</div>
@endsection
