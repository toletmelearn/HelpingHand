@extends('layouts.admin')

@section('title', 'Result Monitor - Examination')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-chart-bar"></i> Result Monitor
                    </h4>
                    <p class="mb-0">Track marks uploading status by class and subject</p>
                </div>
                <div class="card-body">
                    @if($exam)
                        <div class="alert alert-info">
                            <strong>Current Exam:</strong> {{ $exam->name ?? 'N/A' }}
                        </div>
                    @else
                        <div class="alert alert-warning">
                            No exams available
                        </div>
                    @endif
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th>Marks Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($data as $item)
                                    <tr>
                                        <td>{{ $item->class }}</td>
                                        <td>{{ $item->subject }}</td>
                                        <td>{{ $item->teacher }}</td>
                                        <td>
                                            @if($item->total_marks > 0)
                                                <span class="badge bg-success">Uploaded</span>
                                            @else
                                                <span class="badge bg-danger">Pending</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center">No data available</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection