@extends('layouts.admin')

@section('title', 'Today\'s Substitutions - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-calendar-day"></i> Today's Substitutions - {{ now()->format('d F Y (l)') }}
                    </h4>
                </div>
                <div class="card-body">
                    @if($substitutions->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Period</th>
                                        <th>Absent Teacher</th>
                                        <th>Substitute Teacher</th>
                                        <th>Class & Section</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($substitutions as $substitution)
                                    <tr>
                                        <td>
                                            {{ $substitution->period_name }}
                                            @if($substitution->bellTiming)
                                                <br><small class="text-muted">{{ $substitution->bellTiming->day_of_week }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $substitution->absentTeacher->user->name ?? 'N/A' }}</td>
                                        <td>
                                            @if($substitution->substituteTeacher)
                                                {{ $substitution->substituteTeacher->user->name ?? 'N/A' }}
                                            @else
                                                <span class="badge badge-warning">Not Assigned</span>
                                            @endif
                                        </td>
                                        <td>{{ $substitution->class->name }} - {{ $substitution->section->name }}</td>
                                        <td>{{ $substitution->subject->name }}</td>
                                        <td>
                                            <span class="badge 
                                                @if($substitution->status == 'pending') badge-warning
                                                @elseif($substitution->status == 'assigned') badge-info
                                                @elseif($substitution->status == 'approved') badge-success
                                                @elseif($substitution->status == 'cancelled') badge-danger
                                                @endif
                                            ">
                                                {{ $substitution->getReadableStatus() }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('admin.teacher-substitutions.show', $substitution) }}" 
                                                   class="btn btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('admin.teacher-substitutions.edit', $substitution) }}" 
                                                   class="btn btn-outline-secondary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                @if($substitution->isPending() && $substitution->substituteTeacher)
                                                    <form action="{{ route('admin.teacher-substitutions.assign', $substitution) }}" method="POST" style="display:inline;">
                                                        @csrf
                                                        <input type="hidden" name="substitute_teacher_id" value="{{ $substitution->substitute_teacher_id }}">
                                                        <button type="submit" class="btn btn-outline-success" title="Assign Substitute">
                                                            <i class="fas fa-user-check"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h4>No substitutions scheduled for today</h4>
                            <p class="text-muted">All teachers are present and accounted for.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
