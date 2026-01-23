<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bell Timing Details - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .detail-card {
            border-left: 4px solid var(--bs-primary);
        }
        .time-display {
            font-family: monospace;
            font-size: 1.2rem;
            background-color: #f8f9fa;
            padding: 0.5rem;
            border-radius: 0.375rem;
        }
        .info-badge {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-alarm"></i> Bell Timing Details</h1>
            <div>
                <a href="{{ route('bell-timing.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
                <a href="{{ route('bell-timing.edit', $bellTiming) }}" class="btn btn-warning">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row">
            <div class="col-md-8">
                <div class="card detail-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history"></i> Schedule Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="fw-bold">Period Name:</label>
                                <p class="fs-5">{{ $bellTiming->period_name }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold">Day of Week:</label>
                                <p class="fs-5">
                                    <span class="badge bg-info">{{ $bellTiming->day_of_week }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="fw-bold">Start Time:</label>
                                <div class="time-display">
                                    <i class="bi bi-play-circle"></i> {{ $bellTiming->start_time->format('h:i A') }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold">End Time:</label>
                                <div class="time-display">
                                    <i class="bi bi-stop-circle"></i> {{ $bellTiming->end_time->format('h:i A') }}
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="fw-bold">Duration:</label>
                                <p class="fs-5">{{ $bellTiming->duration_formatted }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold">Order Index:</label>
                                <p class="fs-5">
                                    <span class="badge bg-secondary">{{ $bellTiming->order_index }}</span>
                                </p>
                            </div>
                        </div>

                        @if($bellTiming->class_section)
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="fw-bold">Class/Section:</label>
                                <p class="fs-5">
                                    <span class="badge bg-success">{{ $bellTiming->class_section }}</span>
                                </p>
                            </div>
                        </div>
                        @endif

                        @if($bellTiming->custom_label)
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="fw-bold">Custom Label:</label>
                                <p class="fs-5 text-muted">{{ $bellTiming->custom_label }}</p>
                            </div>
                        </div>
                        @endif

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="fw-bold">Status:</label>
                                <p>
                                    @if($bellTiming->is_active)
                                        <span class="badge bg-success fs-6">Active</span>
                                    @else
                                        <span class="badge bg-secondary fs-6">Inactive</span>
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold">Type:</label>
                                <p>
                                    @if($bellTiming->is_break)
                                        <span class="badge bg-warning fs-6">Break Time</span>
                                    @else
                                        <span class="badge bg-primary fs-6">Class Period</span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        @if($bellTiming->academic_year || $bellTiming->semester)
                        <div class="row mb-3">
                            <div class="col-md-6">
                                @if($bellTiming->academic_year)
                                <label class="fw-bold">Academic Year:</label>
                                <p>{{ $bellTiming->academic_year }}</p>
                                @endif
                            </div>
                            <div class="col-md-6">
                                @if($bellTiming->semester)
                                <label class="fw-bold">Semester:</label>
                                <p>{{ $bellTiming->semester }}</p>
                                @endif
                            </div>
                        </div>
                        @endif

                        @if($bellTiming->color_code)
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="fw-bold">Color Code:</label>
                                <div class="d-inline-block ms-2">
                                    <div style="width: 30px; height: 30px; background-color: {{ $bellTiming->color_code }}; border-radius: 4px; border: 1px solid #ccc;"></div>
                                </div>
                                <span class="ms-2">{{ $bellTiming->color_code }}</span>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Metadata</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="fw-bold small text-muted">Created By:</label>
                            <p>
                                @if($bellTiming->createdBy)
                                    {{ $bellTiming->createdBy->name }}
                                @else
                                    <em>Unknown User</em>
                                @endif
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="fw-bold small text-muted">Created At:</label>
                            <p>{{ $bellTiming->created_at->format('M j, Y g:i A') }}</p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="fw-bold small text-muted">Last Updated:</label>
                            <p>{{ $bellTiming->updated_at->format('M j, Y g:i A') }}</p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="fw-bold small text-muted">Database ID:</label>
                            <p>#{{ $bellTiming->id }}</p>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="{{ route('bell-timing.edit', $bellTiming) }}" class="btn btn-warning">
                                <i class="bi bi-pencil"></i> Edit Schedule
                            </a>
                            
                            <form action="{{ route('bell-timing.destroy', $bellTiming) }}" 
                                  method="POST" 
                                  onsubmit="return confirm('Are you sure you want to delete this bell timing?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="bi bi-trash"></i> Delete Schedule
                                </button>
                            </form>
                            
                            <a href="{{ route('bell-timing.create') }}" class="btn btn-outline-primary">
                                <i class="bi bi-plus-circle"></i> Create New
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>