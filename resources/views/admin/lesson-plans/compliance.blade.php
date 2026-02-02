@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Teacher Compliance</h1>
            <p class="mb-4">Track teacher compliance with lesson plan uploads</p>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Lesson Plan Upload Status</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th>Total Plans</th>
                                    <th>This Week</th>
                                    <th>Last Week</th>
                                    <th>Compliance Rate</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($teachers as $teacher)
                                    <tr>
                                        <td>{{ $teacher->name }}</td>
                                        <td>{{ $teacher->lesson_plans_count }}</td>
                                        <td>{{ $teacher->lessonPlans->where('date', '>=', now()->startOfWeek())->count() }}</td>
                                        <td>{{ $teacher->lessonPlans->whereBetween('date', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])->count() }}</td>
                                        <td>
                                            @php
                                                $currentWeekPlans = $teacher->lessonPlans->where('date', '>=', now()->startOfWeek())->count();
                                                $expectedPlans = 5; // Assuming 5 days in a school week
                                                $complianceRate = $expectedPlans > 0 ? round(($currentWeekPlans / $expectedPlans) * 100, 2) : 0;
                                            @endphp
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar 
                                                    @if($complianceRate >= 80) bg-success
                                                    @elseif($complianceRate >= 50) bg-warning
                                                    @else bg-danger
                                                    @endif" 
                                                     role="progressbar" 
                                                     style="width: {{ $complianceRate }}%">
                                                    {{ $complianceRate }}%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @php
                                                $currentWeekPlans = $teacher->lessonPlans->where('date', '>=', now()->startOfWeek())->count();
                                                $expectedPlans = 5;
                                                $status = '';
                                                if($currentWeekPlans >= $expectedPlans) {
                                                    $status = 'On Track';
                                                } elseif($currentWeekPlans >= $expectedPlans * 0.7) {
                                                    $status = 'Needs Attention';
                                                } else {
                                                    $status = 'Critical';
                                                }
                                            @endphp
                                            <span class="
                                                badge 
                                                @if($status === 'On Track') bg-success
                                                @elseif($status === 'Needs Attention') bg-warning
                                                @else bg-danger
                                                @endif
                                            ">
                                                {{ $status }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No teachers found.</td>
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
