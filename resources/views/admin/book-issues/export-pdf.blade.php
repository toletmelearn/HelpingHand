<!DOCTYPE html>
<html>
<head>
    <title>Library Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .info {
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
        <h1>Library Management Report</h1>
        <p>Generated on: {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>

    <div class="info">
        <p><strong>Library Settings:</strong></p>
        <ul>
            <li>Default Issue Days: {{ $librarySetting->default_issue_days }}</li>
            <li>Fine Per Day: ${{ $librarySetting->fine_per_day }}</li>
            <li>Low Stock Threshold: {{ $librarySetting->low_stock_threshold }}</li>
            <li>Auto Reminder Enabled: {{ $librarySetting->auto_reminder_enabled ? 'Yes' : 'No' }}</li>
        </ul>
    </div>

    <div class="stats">
        <div class="stat-card">
            <h3>{{ $bookIssues->count() }}</h3>
            <p>Total Issues</p>
        </div>
        <div class="stat-card">
            <h3>{{ $bookIssues->where('status', 'issued')->count() }}</h3>
            <p>Currently Issued</p>
        </div>
        <div class="stat-card">
            <h3>{{ $bookIssues->where('status', 'returned')->count() }}</h3>
            <p>Total Returned</p>
        </div>
        <div class="stat-card">
            <h3>${{ $bookIssues->where('status', 'returned')->sum('fine_amount') }}</h3>
            <p>Fine Collected</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Book Name</th>
                <th>Author</th>
                <th>Student Name</th>
                <th>Issue Date</th>
                <th>Due Date</th>
                <th>Return Date</th>
                <th>Status</th>
                <th>Fine Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bookIssues as $issue)
            <tr>
                <td>{{ $issue->book->book_name }}</td>
                <td>{{ $issue->book->author }}</td>
                <td>{{ $issue->student->first_name . ' ' . $issue->student->last_name }}</td>
                <td>{{ $issue->issue_date->format('Y-m-d') }}</td>
                <td>{{ $issue->due_date->format('Y-m-d') }}</td>
                <td>{{ $issue->return_date ? $issue->return_date->format('Y-m-d') : '-' }}</td>
                <td>{{ ucfirst($issue->status) }}</td>
                <td>${{ $issue->fine_amount }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>