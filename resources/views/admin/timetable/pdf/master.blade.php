<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Master Timetable</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .day-page {
            page-break-after: always;
        }
        .day-page:last-child {
            page-break-after: auto;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header h1 {
            margin: 0 0 3px 0;
            font-size: 16px;
            color: #1a365d;
        }
        .header h2 {
            margin: 0 0 3px 0;
            font-size: 13px;
            color: #2d3748;
        }
        .header p {
            margin: 0;
            color: #666;
            font-size: 9px;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.grid th {
            background-color: #1a365d;
            color: #fff;
            border: 1px solid #cbd5e1;
            padding: 4px;
            font-size: 8px;
            text-align: center;
        }
        table.grid td {
            border: 1px solid #cbd5e1;
            padding: 4px;
            font-size: 8px;
            text-align: center;
            vertical-align: middle;
        }
        table.grid td.class-label {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: left;
        }
        table.grid td.non-teaching {
            background-color: #e2e8f0;
            color: #64748b;
            font-style: italic;
        }
    </style>
</head>
<body>
    @foreach($days as $day)
        <div class="day-page">
            <div class="header">
                <h1>{{ config('app.name', 'School') }}</h1>
                <h2>Master Timetable — {{ $day }}</h2>
                <p>Academic Session: {{ $session->name ?? 'N/A' }} &nbsp;|&nbsp; Generated: {{ now()->format('d/m/Y') }}</p>
            </div>

            <table class="grid">
                <thead>
                    <tr>
                        <th>Class</th>
                        @foreach($periods as $period)
                            <th>{{ $period }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($classes as $class)
                        <tr>
                            <td class="class-label">{{ $class->name }}</td>
                            @foreach($periods as $period)
                                @php
                                    $meta = $periodMeta[$period][$day] ?? null;
                                    $isNonTeaching = $meta && !$meta['is_teaching'];
                                    $slot = $byDay[$day][$class->id][$period] ?? null;
                                @endphp
                                <td class="{{ $isNonTeaching ? 'non-teaching' : '' }}">
                                    @if($isNonTeaching)
                                        {{ $meta['label'] }}
                                    @elseif($slot)
                                        {{ $slot->subject->code ?? $slot->subject->name ?? '' }}/{{ $slot->teacher->short_name ?? '' }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
