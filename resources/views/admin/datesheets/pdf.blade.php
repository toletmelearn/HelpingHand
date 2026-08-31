<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $datesheet->name }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #333; margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 15px; }
        .header h1 { margin: 0 0 4px 0; font-size: 18px; color: #1a365d; }
        .header p { margin: 0; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 5px 8px; text-align: left; }
        th { background: #1a365d; color: #fff; }
        tr:nth-child(even) { background: #f5f5f5; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $datesheet->name }}</h1>
        <p>{{ $datesheet->exam_type }} &middot; {{ $datesheet->academicSession->name ?? '' }} &middot; {{ $datesheet->start_date->format('d M Y') }} - {{ $datesheet->end_date->format('d M Y') }}</p>
    </div>

    <table>
        <thead>
            <tr><th>Date</th><th>Day</th><th>Class</th><th>Section</th><th>Subject</th><th>Time</th><th>Marks</th><th>Room</th></tr>
        </thead>
        <tbody>
            @forelse($entries as $entry)
                <tr>
                    <td>{{ $entry->exam_date->format('d M Y') }}</td>
                    <td>{{ $entry->day_of_week }}</td>
                    <td>{{ $entry->schoolClass->name ?? '?' }}</td>
                    <td>{{ $entry->section->name ?? 'Whole class' }}</td>
                    <td>{{ $entry->subject->name ?? '?' }}</td>
                    <td>{{ $entry->start_time }} - {{ $entry->end_time }}</td>
                    <td>{{ $entry->total_marks }}</td>
                    <td>{{ $entry->room ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No entries.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
