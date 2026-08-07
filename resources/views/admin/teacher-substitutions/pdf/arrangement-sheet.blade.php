<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Arrangement Sheet - {{ $date->format('d M Y') }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
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
            padding: 5px;
            font-size: 9px;
            text-align: center;
        }
        table.grid td {
            border: 1px solid #cbd5e1;
            padding: 5px;
            font-size: 9px;
            text-align: center;
            vertical-align: middle;
            height: 32px;
        }
        table.grid td.class-label {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: left;
        }
        .absent { color: #b91c1c; text-decoration: line-through; display: block; }
        .substitute { color: #166534; font-weight: bold; display: block; }
        .subject { color: #555; display: block; font-size: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name', 'School') }}</h1>
        <h2>Arrangement Sheet — {{ $date->format('d F Y (l)') }}</h2>
        <p>Generated: {{ now()->format('d/m/Y H:i') }}</p>
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
                        @php $substitution = $grid[$class->id][$period] ?? null; @endphp
                        <td>
                            @if($substitution)
                                <span class="absent">{{ $substitution->absentTeacher->name ?? 'N/A' }}</span>
                                <span class="substitute">{{ $substitution->substituteTeacher->name ?? 'Not assigned' }}</span>
                                <span class="subject">{{ $substitution->subject->name ?? '' }}</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
