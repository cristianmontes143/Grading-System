@extends('layouts.app')

@section('title', 'Database Backups')
@section('header', 'PostgreSQL Backup & Restore')
@section('subheader', 'Create, download, and restore verified snapshots of all school grading records.')

@section('content')
<div class="space-y-6">
    <div class="school-card p-6">
        <h2 class="text-base font-bold text-slate-900 mb-3">Create New Database Backup</h2>
        <form method="POST" action="{{ route('admin.backups.store') }}" class="flex flex-col sm:flex-row gap-3 items-end">
            @csrf
            <div class="flex-1 w-full">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Backup Notes (Optional)</label>
                <input type="text" name="notes" placeholder="e.g. Pre-Term 1 Lock Snapshot" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold shadow-sm whitespace-nowrap">
                + Create Backup Now
            </button>
        </form>
    </div>

    <div class="school-card overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
            <h2 class="text-base font-semibold text-slate-900">Stored Backups ({{ $backups->count() }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Filename &amp; Notes</th>
                    <th class="px-5 py-3">Size</th>
                    <th class="px-5 py-3">Created By</th>
                    <th class="px-5 py-3">Last Restored</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($backups as $backup)
                    <tr>
                        <td class="px-5 py-3">
                            <p class="font-mono text-xs font-semibold text-slate-900">{{ $backup->filename }}</p>
                            @if($backup->notes)
                                <p class="text-xs text-slate-500 mt-0.5">{{ $backup->notes }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-600">{{ $backup->human_size }}</td>
                        <td class="px-5 py-3 text-xs text-slate-600">
                            {{ $backup->creator?->name ?? 'System' }}
                            <span class="block text-slate-400">{{ $backup->created_at->format('Y-m-d H:i:s') }}</span>
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-500">{{ $backup->restored_at?->format('Y-m-d H:i:s') ?? 'Never' }}</td>
                        <td class="px-5 py-3 text-right whitespace-nowrap space-x-2">
                            <a href="{{ route('admin.backups.download', $backup) }}" class="text-xs font-semibold text-school-600 hover:underline">Download</a>
                            <form method="POST" action="{{ route('admin.backups.restore', $backup) }}" class="inline"
                                  onsubmit="return confirm('Restore database from {{ $backup->filename }}? Current data will be replaced by this snapshot.')">
                                @csrf
                                <button type="submit" class="px-2.5 py-1 rounded bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold">Restore</button>
                            </form>
                            <form method="POST" action="{{ route('admin.backups.destroy', $backup) }}" class="inline"
                                  onsubmit="return confirm('Permanently delete backup file {{ $backup->filename }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-rose-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No backups created yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
