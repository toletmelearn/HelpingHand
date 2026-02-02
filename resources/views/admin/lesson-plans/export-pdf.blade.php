<!DOCTYPE html>
<html>
<head>
    <title>Lesson Plans Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Lesson Plans Report</h1>
        <p>Generated on: {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>

    <div class="stats">
        <div class="stat-card">
            <h3>{{ $lessonPlans->count() }}</h3>
            <p>Total Lesson Plans</p>
        </div>
        <div class="stat-card">
            <h3>{{ $lessonPlans->where('plan_type', 'daily')->count() }}</h3>
            <p>Daily Plans</p>
        </div>
        <div class="stat-card">
            <h3>{{ $lessonPlans->where('plan_type', 'weekly')->count() }}</h3>
            <p>Weekly Plans</p>
        </div>
        <div class="stat-card">
            <h3>{{ $lessonPlans->where('plan_type', 'monthly')->count() }}</h3>
            <p>Monthly Plans</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Teacher</th>
                <th>Class</th>
                <th>Section</th>
                <th>Subject</th>
                <th>Date</th>
                <th>Topic</th>
                <th>Type</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lessonPlans as $plan)
            <tr>
                <td>{{ $plan->teacher->name ?? 'N/A' }}</td>
                <td>{{ $plan->class->name ?? 'N/A' }}</td>
                <td>{{ $plan->section->name ?? 'N/A' }}</td>
                <td>{{ $plan->subject->name ?? 'N/A' }}</td>
                <td>{{ $plan->date->format('Y-m-d') }}</td>
                <td>{{ $plan->topic }}</td>
                <td>{{ ucfirst($plan->plan_type) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>