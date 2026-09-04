<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subtitle }} - {{ $title }}</title>
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
        table.grid td.empty {
            color: #aaa;
        }
        .cell-line { display: block; }
        .cell-line.primary { font-weight: bold; }
        .cell-line.secondary { color: #555; font-size: 9px; }
        .cell-line.tertiary { color: #777; font-size: 8px; font-style: italic; }
        .empty-cell-marker { color: #aaa; }
        .footer {
            margin-top: 15px;
            text-align: center;
            color: #888;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $schoolName }}</h1>
        <h2>{{ $subtitle }} — {{ $title }}</h2>
    </div>

    @if($periods->isEmpty())
        <p style="text-align: center; color: #888;">No published timetable periods to show.</p>
    @else
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
                            @php($lines = $grid[$period][$day] ?? null)
                            <td class="{{ $lines ? '' : 'empty' }}">
                                @if($lines)
                                    @foreach(array_values($lines) as $index => $line)
                                        <span class="cell-line {{ $index === 0 ? 'primary' : ($index === 1 ? 'secondary' : 'tertiary') }}">{{ $line }}</span>
                                    @endforeach
                                @else
                                    <span class="empty-cell-marker">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Generated on {{ $generatedAt->format('d M Y, h:i A') }}
    </div>
</body>
</html>
