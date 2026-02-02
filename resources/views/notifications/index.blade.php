@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Notifications</h4>
                    <a href="{{ route('notifications.mark-all-read') }}" class="btn btn-sm btn-outline-primary">
                        Mark All as Read
                    </a>
                </div>
                <div class="card-body">
                    @if($notifications->count() > 0)
                        <div class="list-group">
                            @foreach($notifications as $notification)
                                <div class="list-group-item d-flex justify-content-between align-items-start {{ $notification->read_at ? '' : 'bg-light' }}">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold">{{ $notification->data['title'] ?? 'Notification' }}</div>
                                        <p class="mb-1">{{ $notification->data['message'] ?? $notification->type }}</p>
                                        <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">{{ $loop->iteration }}</span>
                                    @if(!$notification->read_at)
                                        <span class="badge bg-success ms-2">New</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="mt-4">
                            {{ $notifications->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No notifications yet</h5>
                            <p class="text-muted">You don't have any notifications at the moment.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
