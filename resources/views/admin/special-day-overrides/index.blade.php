@extends('layouts.admin')

@section('title', 'Special Day Overrides')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Special Day Overrides</h4>
                    <div class="card-tools">
                        @can('create', App\Models\SpecialDayOverride::class)
                        <a href="{{ route('admin.special-day-overrides.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Override
                        </a>
                        @endcan
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <select name="type" class="form-select">
                                    <option value="">All Types</option>
                                    @foreach($types as $type)
                                        <option value="{{ $type }}" {{ request('type') == $type ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $type)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="date" name="override_date" class="form-control" value="{{ request('override_date') }}" placeholder="Select Date">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                                <a href="{{ route('admin.special-day-overrides.index') }}" class="btn btn-secondary w-100 mt-1">Clear</a>
                            </div>
                        </div>
                    </form>

                    <!-- Overrides Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Custom Schedule</th>
                                    <th>Remarks</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($overrides as $override)
                                <tr>
                                    <td>{{ $override->override_date->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge bg-{{ $override->type == 'exam_day' ? 'danger' : ($override->type == 'half_day' ? 'warning' : ($override->type == 'event_day' ? 'info' : 'dark')) }}">
                                            {{ $override->getReadableType() }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($override->bell_schedule_id && $override->bellSchedule)
                                            {{ $override->bellSchedule->name }}
                                        @elseif($override->custom_periods)
                                            <span class="badge bg-success">Custom Periods</span>
                                        @else
                                            <span class="text-muted">None</span>
                                        @endif
                                    </td>
                                    <td>{{ Str::limit($override->remarks ?? '', 50) }}</td>
                                    <td>{{ $override->createdBy->name ?? 'N/A' }}</td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('admin.special-day-overrides.show', $override) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            @can('update', $override)
                                            <a href="{{ route('admin.special-day-overrides.edit', $override) }}" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            @endcan
                                            @can('delete', $override)
                                            <form action="{{ route('admin.special-day-overrides.destroy', $override) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this override?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center">No special day overrides found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $overrides->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
