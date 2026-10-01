{{-- Notification bell. app.js polls data-poll-url and shows toasts for anything newer than data-last-id. --}}
<a class="icon-btn bell" href="{{ route('admin.notifications.index') }}" aria-label="Notifications{{ ($unreadCount ?? 0) > 0 ? ', '.$unreadCount.' unread' : '' }}"
   data-bell data-poll-url="{{ route('admin.notifications.poll') }}" data-last-id="{{ $lastNotificationId ?? 0 }}"
   data-poll-interval="{{ auth()->user()?->hasPermission('housekeeping.work') ? 15 : 30 }}">
    <x-icon name="bell" /><span class="dot" data-bell-count @if(($unreadCount ?? 0) < 1) hidden @endif>{{ ($unreadCount ?? 0) > 99 ? '99+' : ($unreadCount ?? 0) }}</span>
</a>
