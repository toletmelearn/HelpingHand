@extends('layouts.admin')

@section('title', 'System Logs Center')

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
    .log-terminal {
        background-color: #1e1e1e;
        color: #d4d4d4;
        font-family: 'Courier New', Courier, monospace;
        font-size: 0.85rem;
        padding: 1.5rem;
        border-radius: 8px;
        max-height: 500px;
        overflow-y: auto;
        border: 1px solid #333;
    }
    .log-line {
        margin-bottom: 0.4rem;
        line-height: 1.4;
    }
    .log-time {
        color: #569cd6;
    }
    .log-level-error {
        color: #f44747;
        font-weight: bold;
    }
    .log-level-warning {
        color: #dcdcaa;
        font-weight: bold;
    }
    .log-level-info {
        color: #4fc1ff;
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('operations.dashboard') }}">Operations</a></li>
                    <li class="breadcrumb-item active" aria-current="page">System Logs</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark"><i class="bi bi-terminal text-secondary me-2"></i> System Logs Center</h3>
            <p class="text-muted">Browse real-time Laravel stack traces, audit event records, and log details separated by component tabs.</p>
        </div>
    </div>

    <!-- Log Tabs -->
    <div class="card glass-card border-0 mb-4">
        <div class="card-header bg-light border-0">
            <ul class="nav nav-tabs card-header-tabs" id="logTabs" role="tablist">
                @foreach(array_keys($categorizedLogs) as $index => $tab)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-capitalize {{ $index === 0 ? 'active' : '' }}" 
                                id="{{ $tab }}-tab" 
                                data-bs-toggle="tab" 
                                data-bs-target="#{{ $tab }}-panel" 
                                type="button" 
                                role="tab" 
                                aria-controls="{{ $tab }}-panel" 
                                aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                            {{ str_replace('_', ' ', $tab) }}
                            <span class="badge bg-secondary ms-1">{{ count($categorizedLogs[$tab]) }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="card-body bg-dark text-light p-3 rounded-bottom">
            <div class="tab-content" id="logTabsContent">
                @foreach($categorizedLogs as $tab => $entries)
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" 
                         id="{{ $tab }}-panel" 
                         role="tabpanel" 
                         aria-labelledby="{{ $tab }}-tab">
                        <div class="log-terminal">
                            @forelse($entries as $entry)
                                <div class="log-line">
                                    <span class="log-time">[{{ $entry['timestamp'] }}]</span>
                                    <span class="log-level-{{ strtolower($entry['level']) }}">
                                        {{ $entry['level'] }}:
                                    </span>
                                    <span class="log-message">{{ $entry['message'] }}</span>
                                </div>
                            @empty
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-terminal-dash fs-1 d-block mb-2"></i>
                                    No log entries found in this category.
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
