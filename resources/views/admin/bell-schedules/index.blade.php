@extends('layouts.admin')

@section('title', 'Bell Schedules')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Bell Schedules</h4>
                    <div class="card-tools">
                        @can('create', App\Models\BellSchedule::class)
                        <a href="{{ route('admin.bell-schedules.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Bell Schedule
                        </a>
                        @endcan
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <select name="season_type" class="form-select">
                                    <option value="">All Seasons</option>
                                    @foreach($seasonTypes as $seasonType)
                                        <option value="{{ $seasonType }}" {{ request('season_type') == $seasonType ? 'selected' : '' }}>
                                            {{ ucfirst($seasonType) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="target_group" class="form-select">
                                    <option value="">All Groups</option>
                                    <option value="all" {{ request('target_group') == 'all' ? 'selected' : '' }}>All Classes</option>
                                    <option value="primary" {{ request('target_group') == 'primary' ? 'selected' : '' }}>Primary</option>
                                    <option value="middle" {{ request('target_group') == 'middle' ? 'selected' : '' }}>Middle</option>
                                    <option value="senior" {{ request('target_group') == 'senior' ? 'selected' : '' }}>Senior</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                                <a href="{{ route('admin.bell-schedules.index') }}" class="btn btn-secondary w-100 mt-1">Clear</a>
                            </div>
                        </div>
                    </form>

                    <!-- Schedules Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Season</th>
                                    <th>Date Range</th>
                                    <th>Status</th>
                                    <th>Target Group</th>
                                    <th>Periods</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($schedules as $schedule)
                                <tr>
                                    <td>{{ $schedule->name }}</td>
                                    <td>
                                        <span class="badge bg-{{ $schedule->season_type == 'summer' ? 'warning' : 'info' }}">
                                            {{ ucfirst($schedule->season_type) }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $schedule->start_date->format('d M Y') }} - {{ $schedule->end_date->format('d M Y') }}
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $schedule->isActive() ? 'success' : 'secondary' }}">
                                            {{ ucfirst($schedule->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            {{ $schedule->target_group ?: 'All Classes' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">{{ $schedule->getPeriodCount() }} periods</span>
                                    </td>
                                    <td>{{ $schedule->createdBy->name ?? 'N/A' }}</td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('admin.bell-schedules.show', $schedule) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            @can('update', $schedule)
                                            <a href="{{ route('admin.bell-schedules.edit', $schedule) }}" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            @endcan
                                            @can('delete', $schedule)
                                            <form action="{{ route('admin.bell-schedules.destroy', $schedule) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this bell schedule?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center">No bell schedules found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $schedules->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
