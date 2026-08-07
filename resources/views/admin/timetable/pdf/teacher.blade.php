<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Teacher Timetable - {{ $teacher->name }}</title>
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
        /* Free TEACHING periods are left visibly blank -- no placeholder text or shading. */
        .class-section { font-weight: bold; display: block; }
        .subject { color: #555; display: block; font-size: 9px; }
        .co-teacher { color: #777; display: block; font-size: 8px; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name', 'School') }}</h1>
        <h2>Teacher Timetable — {{ $teacher->name }}</h2>
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
                            $slot = $grid[$period][$day] ?? null;
                            // T6 item 4: this teacher may be the slot's primary
                            // or its co-teacher -- show whichever one they are NOT.
                            $otherTeacher = null;
                            if ($slot) {
                                $otherTeacher = $slot->teacher && $slot->teacher->id === $teacher->id ? $slot->coTeacher : $slot->teacher;
                            }
                        @endphp
                        <td class="{{ $isNonTeaching ? 'non-teaching' : '' }}">
                            @if($isNonTeaching)
                                {{ $meta['label'] }}
                            @elseif($slot)
                                <span class="class-section">{{ $slot->schoolClass->name ?? '' }}{{ $slot->section ? ' '.$slot->section->name : '' }}</span>
                                <span class="subject">{{ $slot->subject->code ?? $slot->subject->name ?? '' }}</span>
                                @if($otherTeacher)
                                    <span class="co-teacher">with {{ $otherTeacher->short_name }}</span>
                                @endif
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
