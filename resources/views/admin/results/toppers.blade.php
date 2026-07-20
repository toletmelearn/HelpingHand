@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Class Toppers - {{ $exam->name }}</h4>
                    <a href="{{ route('admin.results.index') }}" class="btn btn-secondary btn-sm">Back to Results</a>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Exam:</strong> {{ $exam->name }}</p>
                            <p><strong>Academic Year:</strong> {{ $exam->academic_year }}</p>
                        </div>
                        <div class="col-md-6 text-right">
                            <p><strong>Total Students:</strong> {{ count($toppers) }}</p>
                            <p><strong>Date:</strong> {{ date('d M Y') }}</p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Rank</th>
                                    <th>Student Name</th>
                                    <th>Student ID</th>
                                    <th>Class</th>
                                    <th>Average Percentage</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($toppers as $index => $topper)
                                <tr>
                                    <td>
                                        @if($index === 0)
                                            <span class="badge badge-gold" style="font-size: 1.2em;">🥇 {{ $index + 1 }}</span>
                                        @elseif($index === 1)
                                            <span class="badge badge-silver" style="font-size: 1.2em;">🥈 {{ $index + 1 }}</span>
                                        @elseif($index === 2)
                                            <span class="badge badge-bronze" style="font-size: 1.2em;">🥉 {{ $index + 1 }}</span>
                                        @else
                                            <span class="badge badge-secondary">{{ $index + 1 }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $topper['student_name'] }}</td>
                                    <td>{{ $topper['student_id'] }}</td>
                                    <td>{{ $topper['class_name'] }}</td>
                                    <td>
                                        <strong>{{ $topper['average_percentage'] }}%</strong>
                                    </td>
                                    <td>
                                        @if($topper['average_percentage'] >= 90)
                                            <span class="badge badge-success">Outstanding</span>
                                        @elseif($topper['average_percentage'] >= 80)
                                            <span class="badge badge-primary">Excellent</span>
                                        @elseif($topper['average_percentage'] >= 70)
                                            <span class="badge badge-info">Good</span>
                                        @elseif($topper['average_percentage'] >= 60)
                                            <span class="badge badge-warning">Average</span>
                                        @else
                                            <span class="badge badge-secondary">Below Average</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center">No toppers data available</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Performance Statistics -->
                    @if(count($toppers) > 0)
                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5>Top Performer</h5>
                                    <h4>{{ $toppers[0]['student_name'] }}</h4>
                                    <p>{{ $toppers[0]['average_percentage'] }}%</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h5>Average Top Score</h5>
                                    <h4>{{ round(collect($toppers)->avg('average_percentage'), 2) }}%</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h5>Total Toppers</h5>
                                    <h4>{{ count($toppers) }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.badge-gold {
    background-color: #FFD700 !important;
    color: #000 !important;
}
.badge-silver {
    background-color: #C0C0C0 !important;
    color: #000 !important;
}
.badge-bronze {
    background-color: #CD7F32 !important;
    color: #fff !important;
}
</style>
@endsection