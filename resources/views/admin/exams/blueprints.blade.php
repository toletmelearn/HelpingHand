@extends('layouts.admin')

@section('title', 'Exam Blueprint & Competency Mapping')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <!-- Blueprint Form & Overview -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
                    <h5 class="mb-0">Blueprint Mapping for {{ $exam->name }}</h5>
                    <span class="badge bg-light text-dark">Class: {{ $exam->class_name }} | Subject: {{ $exam->subject }}</span>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <div class="progress mb-4" style="height: 25px;">
                        <div class="progress-bar @if($totalWeightage > 100) bg-danger @elseif($totalWeightage == 100) bg-success @else bg-info @endif" 
                             role="progressbar" 
                             style="width: {{ $totalWeightage }}%; font-weight: bold;" 
                             aria-valuenow="{{ $totalWeightage }}" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                             Total Weightage: {{ $totalWeightage }}%
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Topic / Chapter Name</th>
                                    <th>Competency Level</th>
                                    <th>Weightage (%)</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($blueprints as $blueprint)
                                    <tr>
                                        <td>{{ $blueprint->topic_name }}</td>
                                        <td>
                                            <span class="badge bg-secondary">{{ ucfirst($blueprint->competency_level) }}</span>
                                        </td>
                                        <td>{{ $blueprint->weightage_percentage }}%</td>
                                        <td>
                                            <form action="{{ route('admin.exams.blueprints.destroy', $blueprint->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Remove this topic?')">
                                                    <i class="fas fa-trash-alt"></i> Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No blueprint competency mapping defined yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Mapping Sidebar Card -->
        <div class="col-lg-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">Add Competency Topic</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.exams.blueprints.store', $exam->id) }}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label for="topic_name" class="form-label font-weight-bold">Topic / Chapter Name</label>
                            <input type="text" name="topic_name" id="topic_name" class="form-control" required placeholder="e.g. Chapter 4: Fractions">
                        </div>

                        <div class="form-group mb-3">
                            <label for="competency_level" class="form-label font-weight-bold">Target Competency</label>
                            <select name="competency_level" id="competency_level" class="form-control" required>
                                <option value="recall">Recall &amp; Knowledge</option>
                                <option value="understanding">Understanding &amp; Comprehension</option>
                                <option value="application">Application &amp; Practical Execution</option>
                                <option value="analysis">Analysis &amp; Evaluation</option>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label for="weightage_percentage" class="form-label font-weight-bold">Weightage Percentage (%)</label>
                            <input type="number" step="0.01" name="weightage_percentage" id="weightage_percentage" class="form-control" required min="0.01" max="100" placeholder="e.g. 15.00">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Add to Blueprint</button>
                    </form>
                </div>
            </div>
            <div class="mt-3">
                <a href="{{ route('admin.exams.index') }}" class="btn btn-secondary w-100">Back to Exams List</a>
            </div>
        </div>
    </div>
</div>
@endsection
