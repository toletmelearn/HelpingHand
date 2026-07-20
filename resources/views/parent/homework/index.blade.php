@extends('layouts.parent')

@section('title', 'My Child\'s Homework')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">My Child's Homework</h4>
                </div>
                <div class="card-body">
                    @if($homeworks->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Subject</th>
                                        <th>Title</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($homeworks as $hw)
                                        <tr>
                                            <td>{{ $hw->created_at->format('Y-m-d') }}</td>
                                            <td>{{ $hw->subject->name ?? 'N/A' }}</td>
                                            <td>{{ Str::limit($hw->title, 30) }}</td>
                                            <td>{{ $hw->due_date ?? 'N/A' }}</td>
                                            <td>
                                                <span class="badge bg-{{ $hw->status === 'active' ? 'success' : ($hw->status === 'inactive' ? 'secondary' : 'info') }}">
                                                    {{ ucfirst($hw->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                <a href="{{ route('parent.homework.show', $hw) }}" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center">
                            <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                            <h5>No homework assigned</h5>
                            <p class="text-muted">Your child doesn't have any homework assigned yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection