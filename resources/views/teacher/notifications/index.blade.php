@extends('layouts.teacher')

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="mb-0">Notifications</h4>
    </div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
            @forelse($notifications as $notification)
                <li class="list-group-item {{ $notification->read_at ? '' : 'bg-light' }}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-bold">{{ $notification->data['title'] ?? 'Notification' }}</div>
                            <div class="text-muted">{{ $notification->data['message'] ?? '' }}</div>
                            <div class="text-muted small">{{ $notification->created_at->diffForHumans() }}</div>
                        </div>
                        @if(!$notification->read_at)
                            <form method="POST" action="{{ route('teacher.notifications.read', $notification->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Mark read</button>
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="list-group-item text-muted">No notifications yet.</li>
            @endforelse
        </ul>
    </div>
</div>

<div class="mt-3">
    {{ $notifications->links() }}
</div>
@endsection
