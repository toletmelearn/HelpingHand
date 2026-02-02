@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Subject-wise Progress Report</h1>
            <p class="mb-4">Track lesson plan coverage by subject</p>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Subject Coverage Statistics</h5>
                </div>
                <div class="card-body">
                    <canvas id="subjectChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Lesson Plans by Subject</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Number of Plans</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($subjectProgress as $progress)
                                <tr>
                                    <td>{{ $progress->subject->name ?? 'N/A' }}</td>
                                    <td>{{ $progress->plan_count }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="2" class="text-center">No lesson plans found</td>
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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('subjectChart').getContext('2d');
const subjectData = {
    labels: [
        @foreach($subjectProgress as $progress)
            '{{ $progress->subject->name ?? "N/A" }}',
        @endforeach
    ],
    datasets: [{
        label: 'Number of Lesson Plans',
        data: [
            @foreach($subjectProgress as $progress)
                {{ $progress->plan_count }},
            @endforeach
        ],
        backgroundColor: [
            'rgba(255, 99, 132, 0.2)',
            'rgba(54, 162, 235, 0.2)',
            'rgba(255, 205, 86, 0.2)',
            'rgba(75, 192, 192, 0.2)',
            'rgba(153, 102, 255, 0.2)',
            'rgba(255, 159, 64, 0.2)'
        ],
        borderColor: [
            'rgb(255, 99, 132)',
            'rgb(54, 162, 235)',
            'rgb(255, 205, 86)',
            'rgb(75, 192, 192)',
            'rgb(153, 102, 255)',
            'rgb(255, 159, 64)'
        ],
        borderWidth: 1
    }]
};

const subjectChart = new Chart(ctx, {
    type: 'bar',
    data: subjectData,
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>
@endpush
@endsection
