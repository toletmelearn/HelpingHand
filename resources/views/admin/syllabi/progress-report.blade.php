@extends('layouts.app')

@section('title', 'Syllabus Progress Report')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Syllabus Progress Report</h4>
                    <a href="{{ route('admin.syllabi.index') }}" class="btn btn-secondary">Back to Syllabi</a>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <select name="class_name" class="form-control">
                                    <option value="">All Classes</option>
                                    @foreach($classes as $class)
                                        <option value="{{ $class }}" {{ request('class_name') == $class ? 'selected' : '' }}>
                                            {{ $class }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select name="subject" class="form-control">
                                    <option value="">All Subjects</option>
                                    @foreach($subjects as $subject)
                                        <option value="{{ $subject }}" {{ request('subject') == $subject ? 'selected' : '' }}>
                                            {{ $subject }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                                <a href="{{ route('admin.syllabi.progress-report') }}" class="btn btn-secondary w-100 mt-1">Clear</a>
                            </div>
                        </div>
                    </form>

                    <!-- Progress Summary -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card text-white bg-primary">
                                <div class="card-body text-center">
                                    <h5>Total Syllabi</h5>
                                    <h3>{{ count($progressData) }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-success">
                                <div class="card-body text-center">
                                    <h5>Avg. Coverage</h5>
                                    <h3>{{ round(collect($progressData)->avg('coverage_percentage'), 2) }}%</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-info">
                                <div class="card-body text-center">
                                    <h5>On Track</h5>
                                    <h3>{{ collect($progressData)->filter(function($item) { return $item['coverage_percentage'] >= $item['duration_percentage']; })->count() }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-warning">
                                <div class="card-body text-center">
                                    <h5>Behind Schedule</h5>
                                    <h3>{{ collect($progressData)->filter(function($item) { return $item['coverage_percentage'] < $item['duration_percentage']; })->count() }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Progress Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Syllabus Title</th>
                                    <th>Subject</th>
                                    <th>Class</th>
                                    <th>Total Chapters</th>
                                    <th>Covered Chapters</th>
                                    <th>Coverage %</th>
                                    <th>Duration %</th>
                                    <th>Status</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($progressData as $data)
                                <tr>
                                    <td>{{ $data['syllabus']->title }}</td>
                                    <td>{{ $data['syllabus']->subject }}</td>
                                    <td>{{ $data['syllabus']->class_name }}</td>
                                    <td>{{ $data['total_chapters'] }}</td>
                                    <td>{{ $data['covered_chapters'] }}</td>
                                    <td>{{ $data['coverage_percentage'] }}%</td>
                                    <td>{{ $data['duration_percentage'] }}%</td>
                                    <td>
                                        @if($data['coverage_percentage'] >= $data['duration_percentage'])
                                            <span class="badge bg-success">On Track</span>
                                        @else
                                            <span class="badge bg-warning">Behind</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-info" role="progressbar" 
                                                 style="width: <?php echo $data['coverage_percentage']; ?>%;" 
                                                 aria-valuenow="<?php echo $data['coverage_percentage']; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $data['coverage_percentage']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center">No syllabi found.</td>
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