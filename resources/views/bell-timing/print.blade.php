<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bell Schedule - Print View</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: white;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .filters {
            margin-bottom: 20px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 5px;
        }
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .break-row {
            background-color: #fff3cd !important;
        }
        .period-row {
            background-color: #d1ecf1 !important;
        }
        .print-button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            margin-bottom: 10px;
        }
        .print-only {
            display: none;
        }
        @media print {
            .no-print {
                display: none;
            }
            .print-only {
                display: block;
            }
            body {
                margin: 0;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>School Bell Schedule - Print View</h1>
        <p>Printed on: {{ now()->format('F j, Y g:i A') }}</p>
    </div>

    <div class="filters no-print">
        <form method="GET" action="{{ route('bell-timing.print') }}">
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div>
                    <label for="class_section">Class/Section:</label>
                    <select name="class_section" id="class_section" onchange="this.form.submit()">
                        <option value="">All Classes</option>
                        @foreach($classSections as $section)
                            <option value="{{ $section }}" {{ request('class_section') == $section ? 'selected' : '' }}>
                                {{ $section }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="academic_year">Academic Year:</label>
                    <select name="academic_year" id="academic_year" onchange="this.form.submit()">
                        <option value="">All Years</option>
                        @foreach($academicYears as $year)
                            <option value="{{ $year }}" {{ request('academic_year') == $year ? 'selected' : '' }}>
                                {{ $year }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>
    </div>

    <div class="print-only">
        <p><strong>Selected Class:</strong> {{ $classSection ?: 'All Classes' }}</p>
        <p><strong>Academic Year:</strong> {{ $academicYear ?: 'All Years' }}</p>
    </div>

    <div class="table-container">
        @if($timetable->isNotEmpty())
            <table>
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Period</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Duration</th>
                        <th>Type</th>
                        <th>Order</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day)
                        @php
                            $daySchedules = $timetable->where('day_of_week', $day)->sortBy('order_index');
                        @endphp
                        
                        @if($daySchedules->count() > 0)
                            @foreach($daySchedules as $schedule)
                                <tr class="{{ $schedule->is_break ? 'break-row' : 'period-row' }}">
                                    <td>
                                        @if($loop->first)
                                            <strong>{{ $day }}</strong>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $schedule->period_name }}</strong>
                                        @if($schedule->custom_label)
                                            <br><small>{{ $schedule->custom_label }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $schedule->start_time->format('h:i A') }}</td>
                                    <td>{{ $schedule->end_time->format('h:i A') }}</td>
                                    <td>{{ $schedule->duration_formatted }}</td>
                                    <td>
                                        @if($schedule->is_break)
                                            <span style="background-color: #ffc107; padding: 2px 6px; border-radius: 3px;">Break</span>
                                        @else
                                            <span style="background-color: #28a745; padding: 2px 6px; border-radius: 3px;">Class</span>
                                        @endif
                                    </td>
                                    <td>{{ $schedule->order_index }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td><strong>{{ $day }}</strong></td>
                                <td colspan="6">No schedule</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @else
            <div style="text-align: center; padding: 20px; background: #f8d7da; color: #721c24; border-radius: 5px;">
                <p>No bell schedules found for the selected criteria.</p>
            </div>
        @endif
    </div>

    <div style="margin-top: 20px;" class="no-print">
        <button class="print-button" onclick="window.print()">🖨️ Print Schedule</button>
        <a href="{{ route('bell-timing.weekly') }}" style="margin-left: 10px; background-color: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">Back to Weekly View</a>
    </div>

    <div class="print-only" style="margin-top: 20px; text-align: center; font-size: 12px; color: #666;">
        <p>Generated by HelpingHand School Management System</p>
    </div>
</body>
</html>