@extends('layouts.app')

@section('title', 'Notifications')
@section('header', 'Notifications Center')
@section('subheader', 'Grade submissions, revision notices, and administrator reminders.')

@section('content')
<div class="space-y-5 max-w-4xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <a href="{{ route('notifications.index') }}"
               class="px-3.5 py-1.5 rounded-lg text-xs font-semibold {{ $filter !== 'unread' ? 'bg-school-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                All Notifications
            </a>
            <a href="{{ route('notifications.index', ['filter' => 'unread']) }}"
               class="px-3.5 py-1.5 rounded-lg text-xs font-semibold {{ $filter === 'unread' ? 'bg-school-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                Unread ({{ $unreadCount }})
            </a>
        </div>

        @if($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold">
                    Mark All Read
                </button>
            </form>
        @endif
    </div>

    <div class="school-card overflow-hidden">
        <div class="divide-y divide-slate-100">
            @forelse($notifications as $notif)
                @php
                    $isUnread = is_null($notif->read_at);
                    $type = $notif->data['type'] ?? 'info';
                    $dot = match ($type) {
                        'success' => 'bg-emerald-500',
                        'warning' => 'bg-amber-500',
                        default => 'bg-school-600',
                    };
                @endphp
                <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 {{ $isUnread ? 'bg-school-50/40' : 'bg-white' }}">
                    <div class="flex items-start gap-3">
                        <span class="w-2.5 h-2.5 rounded-full mt-1.5 shrink-0 {{ $isUnread ? $dot : 'bg-slate-300' }}"></span>
                        <div>
                            <p class="text-sm font-bold text-slate-900">{{ $notif->data['title'] ?? 'Notification' }}</p>
                            <p class="text-xs text-slate-600 mt-0.5 leading-relaxed">{{ $notif->data['message'] ?? '' }}</p>
                            <p class="text-[11px] text-slate-400 mt-1">{{ $notif->created_at->format('M d, Y h:i A') }} &bull; {{ $notif->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if(! empty($notif->data['url']))
                            <form method="POST" action="{{ route('notifications.read', ['notification' => $notif->id, 'redirect' => 1]) }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-school-600 hover:bg-school-700 text-white text-xs font-semibold">
                                    Open &rarr;
                                </button>
                            </form>
                        @endif
                        @if($isUnread)
                            <form method="POST" action="{{ route('notifications.read', $notif->id) }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold">
                                    Mark Read
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-10 text-center text-sm text-slate-500">
                    {{ $filter === 'unread' ? 'You are all caught up! No unread notifications.' : 'No notifications yet.' }}
                </div>
            @endforelse
        </div>
        @if($notifications->hasPages())
            <div class="px-5 py-3 border-t border-slate-200">{{ $notifications->links() }}</div>
        @endif
    </div>
</div>
@endsection
