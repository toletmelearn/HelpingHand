<!-- Notification Dropdown -->
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-bell"></i>
        <span id="notification-count" class="badge bg-danger position-absolute top-0 start-100 translate-middle rounded-pill" style="font-size: 0.7em;">
            {{ auth()->user()->unreadNotifications->count() }}
        </span>
    </a>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown" style="min-width: 350px;">
        <li class="dropdown-header d-flex justify-content-between align-items-center">
            <span>Notifications</span>
            <a href="#" id="mark-all-read" class="text-decoration-none small">Mark all as read</a>
        </li>
        <li><hr class="dropdown-divider"></li>
        
        @forelse(auth()->user()->unreadNotifications->take(5) as $notification)
            <li>
                <a class="dropdown-item notification-item {{ $notification->read_at ? '' : 'fw-bold' }}" href="#" data-id="{{ $notification->id }}">
                    <div class="d-flex w-100">
                        <div class="flex-grow-1">
                            <div class="fw-medium">{{ $notification->data['title'] ?? 'Notification' }}</div>
                            <small class="text-muted">{{ $notification->data['message'] ?? $notification->type }}</small>
                            <br>
                            <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                        </div>
                        @if(!$notification->read_at)
                            <span class="badge bg-primary rounded-pill ms-2">New</span>
                        @endif
                    </div>
                </a>
            </li>
        @empty
            <li>
                <a class="dropdown-item text-center text-muted" href="#">
                    <i class="fas fa-check-circle me-1"></i> No new notifications
                </a>
            </li>
        @endforelse
        
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item text-center" href="{{ route('notifications.index') }}">
                View all notifications
            </a>
        </li>
    </ul>
</li>

<!-- JavaScript for notification handling -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mark notification as read when clicked
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const notificationId = this.getAttribute('data-id');
            
            fetch(`/api/notifications/${notificationId}/read`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // Update UI to show notification as read
                    this.classList.remove('fw-bold');
                    this.innerHTML = this.innerHTML.replace('New', '');
                    
                    // Update notification count
                    updateNotificationCount();
                }
            })
            .catch(error => console.error('Error:', error));
        });
    });
    
    // Mark all notifications as read
    document.getElementById('mark-all-read').addEventListener('click', function(e) {
        e.preventDefault();
        
        fetch('/api/notifications/mark-all-read', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                // Update UI to show all notifications as read
                document.querySelectorAll('.notification-item').forEach(item => {
                    item.classList.remove('fw-bold');
                    const badge = item.querySelector('.badge');
                    if(badge) badge.remove();
                });
                
                // Update notification count
                updateNotificationCount();
            }
        })
        .catch(error => console.error('Error:', error));
    });
    
    // Function to update notification count
    function updateNotificationCount() {
        fetch('/api/notifications/unread-count', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            const countElement = document.getElementById('notification-count');
            if(countElement) {
                if(data.count > 0) {
                    countElement.textContent = data.count;
                    countElement.style.display = 'block';
                } else {
                    countElement.style.display = 'none';
                }
            }
        })
        .catch(error => console.error('Error:', error));
    }
    
    // Refresh notification count periodically (every 30 seconds)
    setInterval(updateNotificationCount, 30000);
});
</script>