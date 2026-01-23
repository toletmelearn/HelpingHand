<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bell Timing Management - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .period-card {
            border-left: 4px solid var(--bs-primary);
            transition: all 0.3s ease;
        }
        .period-card.break {
            border-left-color: var(--bs-warning);
        }
        .period-card.lunch {
            border-left-color: var(--bs-success);
        }
        .period-card.assembly {
            border-left-color: var(--bs-info);
        }
        .time-badge {
            font-family: monospace;
            background-color: #e9ecef;
            color: #495057;
        }
        .day-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-alarm"></i> Bell Timing Management</h1>
            <div>
                <a href="{{ route('bell-timing.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add Schedule
                </a>
                <a href="{{ route('bell-timing.weekly') }}" class="btn btn-info">
                    <i class="bi bi-calendar-week"></i> Weekly View
                </a>
                <a href="{{ route('bell-timing.print') }}" class="btn btn-info ms-2">
                    <i class="bi bi-printer"></i> Print View
                </a>
                <a href="{{ route('bell-timing.bulk-create') }}" class="btn btn-success">
                    <i class="bi bi-file-earmark-plus"></i> Bulk Create
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

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Schedules</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('bell-timing.index') }}" class="row g-3">
                    <div class="col-md-3">
                        <label for="day_of_week" class="form-label">Day of Week</label>
                        <select class="form-select" id="day_of_week" name="day_of_week">
                            <option value="">All Days</option>
                            @foreach($daysOfWeek as $day)
                                <option value="{{ $day }}" {{ request('day_of_week') == $day ? 'selected' : '' }}>
                                    {{ $day }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="class_section" class="form-label">Class/Section</label>
                        <select class="form-select" id="class_section" name="class_section">
                            <option value="">All Classes</option>
                            @foreach($classSections as $section)
                                <option value="{{ $section }}" {{ request('class_section') == $section ? 'selected' : '' }}>
                                    {{ $section }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="is_active" class="form-label">Status</label>
                        <select class="form-select" id="is_active" name="is_active">
                            <option value="">All Status</option>
                            <option value="1" {{ request('is_active') == '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('is_active') == '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="academic_year" class="form-label">Academic Year</label>
                        <select class="form-select" id="academic_year" name="academic_year">
                            <option value="">All Years</option>
                            @foreach($academicYears as $year)
                                <option value="{{ $year }}" {{ request('academic_year') == $year ? 'selected' : '' }}>
                                    {{ $year }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bell Timing Records -->
        @if($bellTimings->count() > 0)
            <div class="row">
                @foreach($bellTimings->groupBy('day_of_week') as $day => $timings)
                    <div class="col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header day-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-calendar-day"></i> {{ $day }}
                                    <span class="float-end badge bg-light text-dark">{{ $timings->count() }} periods</span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    @foreach($timings->sortBy('order_index') as $timing)
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card period-card {{ $timing->is_break ? 'break' : '' }}" 
                                                 style="border-left-color: {{ $timing->color_code }};">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <h6 class="card-title mb-1">
                                                            {{ $timing->period_name }}
                                                            @if($timing->custom_label)
                                                                <small class="text-muted">({{ $timing->custom_label }})</small>
                                                            @endif
                                                        </h6>
                                                        <span class="badge {{ $timing->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                            {{ $timing->is_active ? 'Active' : 'Inactive' }}
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="time-badge p-2 mb-2 rounded">
                                                        <i class="bi bi-clock"></i> 
                                                        {{ $timing->getFormattedTimeRange() }}
                                                    </div>
                                                    
                                                    <div class="mb-2">
                                                        <small class="text-muted">
                                                            <i class="bi bi-sort-numeric-up"></i> Order: {{ $timing->order_index }}
                                                        </small>
                                                    </div>
                                                    
                                                    @if($timing->class_section)
                                                        <div class="mb-2">
                                                            <small class="text-muted">
                                                                <i class="bi bi-mortarboard"></i> {{ $timing->class_section }}
                                                            </small>
                                                        </div>
                                                    @endif
                                                    
                                                    <div class="mt-3">
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <a href="{{ route('bell-timing.show', $timing) }}" 
                                                               class="btn btn-outline-info" title="View Details">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <a href="{{ route('bell-timing.edit', $timing) }}" 
                                                               class="btn btn-outline-warning" title="Edit">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <form action="{{ route('bell-timing.destroy', $timing) }}" 
                                                                  method="POST" 
                                                                  style="display: inline;"
                                                                  onsubmit="return confirm('Delete this bell timing?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <div class="d-flex justify-content-center">
                {{ $bellTimings->links() }}
            </div>
        @else
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i> 
                No bell timing schedules found. 
                <a href="{{ route('bell-timing.create') }}" class="alert-link">Create your first schedule!</a>
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>