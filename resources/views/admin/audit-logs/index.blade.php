@extends('layouts.app')

@section('title', 'Activity & Audit Logs')
@section('header', 'Activity & Grade Audit Logs')
@section('subheader', 'Immutable PostgreSQL record of authentication, configuration, and grade modification events.')

@section('content')
<div class="space-y-4">
    <form method="GET" action="{{ route('admin.audit-logs.index') }}"
          class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-col sm:flex-row gap-3">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Search by actor, event, reason, or IP..."
               class="flex-1 rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
        <select name="event_type" class="rounded-lg border-slate-300 text-sm focus:border-school-500 focus:ring-school-500">
            <option value="">All Event Types</option>
            @foreach($eventTypes as $type)
                <option value="{{ $type }}" @selected(request('event_type') === $type)>{{ $type }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 rounded-lg bg-school-600 hover:bg-school-700 text-white text-sm font-semibold">
            Filter
        </button>
        <a href="{{ route('admin.audit-logs.export', request()->query()) }}"
           class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold text-center whitespace-nowrap">
            Export CSV
        </a>
        @if(request()->hasAny(['search', 'event_type']))
            <a href="{{ route('admin.audit-logs.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium text-center">
                Reset
            </a>
        @endif
    </form>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Date &amp; Time</th>
                    <th class="px-5 py-3">Actor</th>
                    <th class="px-5 py-3">Event</th>
                    <th class="px-5 py-3">Grade Change</th>
                    <th class="px-5 py-3">Reason / Details</th>
                    <th class="px-5 py-3">IP Address</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($logs as $log)
                    <tr>
                        <td class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="px-5 py-3">
                            <p class="font-medium text-slate-900">{{ $log->actor?->name ?? 'System' }}</p>
                            <p class="text-xs text-slate-500">{{ $log->actor?->email ?? '' }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
                                {{ $log->event_type }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-slate-700 whitespace-nowrap">
                            @if($log->previous_grade !== null || $log->updated_grade !== null)
                                {{ $log->previous_grade ?? '—' }} &rarr; <strong>{{ $log->updated_grade ?? '—' }}</strong>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-600 max-w-xs truncate" title="{{ $log->reason }}">
                            {{ $log->reason ?? '—' }}
                        </td>
                        <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-slate-500">No audit records match your filter.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="px-5 py-3 border-t border-slate-200">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
