@extends('layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="card-title mb-1">Notification History</h4>
                <p class="mb-0 text-muted">All superadmin notifications, including read and unread activity.</p>
            </div>
            <form method="POST" action="{{ route('superadmin.notifications.read') }}">
                @csrf
                <button type="submit" class="btn btn-primary">Mark All Read</button>
            </form>
        </div>

        <div class="card-body">
            <div class="notification-history-list">
                @forelse($notifications as $notification)
                    @php
                        $isUnread = is_null($notification->read_at);
                        $companyName = optional($notification->company)->name;
                        $actorName = optional($notification->actor)->name;
                    @endphp

                    <a href="{{ route('superadmin.notifications.open', $notification->id) }}" class="notification-history-item {{ $isUnread ? 'is-unread' : '' }}">
                        <div class="notification-history-icon">
                            <i class="typcn typcn-user-add-outline mx-0"></i>
                        </div>

                        <div class="notification-history-content">
                            <div class="notification-history-title-row">
                                <h5>{{ $notification->title }}</h5>
                                <span class="notification-history-status {{ $isUnread ? 'unread' : 'read' }}">
                                    {{ $isUnread ? 'Unread' : 'Read' }}
                                </span>
                            </div>

                            <p>{{ $notification->message }}</p>

                            <div class="notification-history-meta">
                                @if($companyName)
                                    <span>Company: {{ $companyName }}</span>
                                @endif
                                @if($actorName)
                                    <span>By: {{ $actorName }}</span>
                                @endif
                                <span>{{ optional($notification->created_at)->format('d-m-Y h:i A') }}</span>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="notification-history-empty">
                        No notifications found.
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $notifications->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .notification-history-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .notification-history-item {
        display: flex;
        gap: 14px;
        padding: 16px;
        color: #d8dbea;
        text-decoration: none;
        border: 1px solid #343850;
        border-radius: 8px;
        background: #25283a;
    }

    .notification-history-item:hover,
    .notification-history-item:focus {
        color: #ffffff;
        text-decoration: none;
        background: #30344b;
        border-color: #4b5276;
    }

    .notification-history-item.is-unread {
        border-color: rgba(255, 23, 68, .35);
        background: rgba(255, 23, 68, .08);
    }

    .notification-history-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 42px;
        width: 42px;
        height: 42px;
        border-radius: 8px;
        color: #ffffff;
        background: linear-gradient(135deg, #ff1764, #2f80ed);
        font-size: 18px;
    }

    .notification-history-content {
        flex: 1;
        min-width: 0;
    }

    .notification-history-title-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 6px;
    }

    .notification-history-title-row h5 {
        margin: 0;
        color: #ffffff;
        font-size: 15px;
        font-weight: 700;
    }

    .notification-history-content p {
        margin: 0 0 10px;
        color: #d8dbea;
        font-size: 14px;
        line-height: 1.4;
    }

    .notification-history-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 16px;
        color: #9ba2b6;
        font-size: 12px;
    }

    .notification-history-status {
        padding: 4px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }

    .notification-history-status.unread {
        color: #ffffff;
        background: #ff1764;
    }

    .notification-history-status.read {
        color: #d8dbea;
        background: #44495f;
    }

    .notification-history-empty {
        padding: 34px 16px;
        color: #aeb4c7;
        text-align: center;
        border: 1px solid #343850;
        border-radius: 8px;
        background: #25283a;
    }
</style>
@endpush
