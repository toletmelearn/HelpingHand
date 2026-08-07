<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Class Timetable - {{ $title }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0 0 4px 0;
            font-size: 18px;
            color: #1a365d;
        }
        .header h2 {
            margin: 0 0 4px 0;
            font-size: 14px;
            color: #2d3748;
        }
        .header p {
            margin: 0;
            color: #666;
            font-size: 10px;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.grid th {
            background-color: #1a365d;
            color: #fff;
            border: 1px solid #cbd5e1;
            padding: 6px;
            font-size: 10px;
            text-align: center;
        }
        table.grid td {
            border: 1px solid #cbd5e1;
            padding: 6px;
            font-size: 10px;
            text-align: center;
            vertical-align: middle;
            height: 30px;
        }
        table.grid td.period-label {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: left;
        }
        table.grid td.non-teaching {
            background-color: #e2e8f0;
            color: #64748b;
            font-style: italic;
        }
        .subject { font-weight: bold; display: block; }
        .teacher { color: #555; display: block; font-size: 9px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name', 'School') }}</h1>
        <h2>Class Timetable — {{ $title }}</h2>
        <p>Academic Session: {{ $session->name ?? 'N/A' }} &nbsp;|&nbsp; Generated: {{ now()->format('d/m/Y') }}</p>
    </div>

    <table class="grid">
        <thead>
            <tr>
                <th>Period</th>
                @foreach($days as $day)
                    <th>{{ $day }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($periods as $period)
                <tr>
                    <td class="period-label">{{ $period }}</td>
                    @foreach($days as $day)
                        @php
                            $meta = $periodMeta[$period][$day] ?? null;
                            $isNonTeaching = $meta && !$meta['is_teaching'];
                            $beyondClassDay = $meta && $lastTeachingPeriod && $meta['order_index'] > $lastTeachingPeriod;
                            $slot = $grid[$period][$day] ?? null;
                        @endphp
                        <td class="{{ ($isNonTeaching || $beyondClassDay) ? 'non-teaching' : '' }}">
                            @if($beyondClassDay)
                                &mdash;
                            @elseif($isNonTeaching)
                                {{ $meta['label'] }}
                            @elseif($slot)
                                <span class="subject">{{ $slot->subject->code ?? $slot->subject->name ?? '' }}</span>
                                <span class="teacher">{{ $slot->teacher->short_name ?? '' }}{{ $slot->coTeacher ? ' / '.$slot->coTeacher->short_name : '' }}</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
