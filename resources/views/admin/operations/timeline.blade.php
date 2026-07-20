@extends('layouts.admin')

@section('title', 'Activity Timeline')

@section('content')
<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.06);
    }
    
    /* TIMELINE STYLES */
    .timeline-container {
        position: relative;
        padding-left: 2.5rem;
        margin-top: 1rem;
    }
    .timeline-container::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e9ecef;
    }
    .timeline-item {
        position: relative;
        margin-bottom: 2rem;
    }
    .timeline-badge {
        position: absolute;
        left: -33px;
        top: 4px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #fff;
        border: 4px solid #0d6efd;
        z-index: 2;
    }
    .timeline-badge.badge-import { border-color: #0d6efd; }
    .timeline-badge.badge-finance { border-color: #198754; }
    .timeline-badge.badge-payment { border-color: #0dcaf0; }
    .timeline-badge.badge-auth { border-color: #ffc107; }
    .timeline-badge.badge-backup { border-color: #6c757d; }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('operations.dashboard') }}">Operations</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Activity Timeline</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark"><i class="bi bi-clock-history text-dark me-2"></i> System Activity Timeline</h3>
            <p class="text-muted">High-level sequential visualization of system actions, user logins, data syncs, and financial processes.</p>
        </div>
    </div>

    <!-- Timeline Wrapper -->
    <div class="row">
        <div class="col-lg-9 col-12 mx-auto">
            <div class="card glass-card border-0 p-4">
                <h5 class="fw-bold text-dark mb-4"><i class="bi bi-calendar3 me-1 text-primary"></i> Chronological Activity Trail</h5>
                
                <div class="timeline-container">
                    @foreach($timeline as $item)
                        @php
                            $badgeClass = 'badge-import';
                            $title = strtolower($item['title']);
                            if (str_contains($title, 'finance') || str_contains($title, 'fee')) {
                                $badgeClass = 'badge-finance';
                            } elseif (str_contains($title, 'payment') || str_contains($title, 'stripe')) {
                                $badgeClass = 'badge-payment';
                            } elseif (str_contains($title, 'auth') || str_contains($title, 'login')) {
                                $badgeClass = 'badge-auth';
                            } elseif (str_contains($title, 'backup') || str_contains($title, 'disaster')) {
                                $badgeClass = 'badge-backup';
                            }
                        @endphp
                        
                        <div class="timeline-item">
                            <div class="timeline-badge {{ $badgeClass }}"></div>
                            <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap">
                                <h6 class="fw-bold mb-0 text-dark">{{ $item['title'] }}</h6>
                                <span class="badge bg-light text-dark small border">
                                    {{ $item['date'] }} at {{ $item['time'] }}
                                </span>
                            </div>
                            <p class="text-muted mb-0 small">
                                {{ $item['description'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
