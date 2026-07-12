<!DOCTYPE html>
<html>
<head>
    <title>Admit Card - {{ $admitCard->data['student_name'] ?? 'Student' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 0;
            color: #333;
        }
        .content {
            border: 2px solid #333;
            padding: 20px;
            margin: 20px 0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .info-col {
            width: 48%;
        }
        .info-item {
            margin-bottom: 5px;
        }
        .subjects {
            margin-top: 20px;
        }
        .instructions {
            margin-top: 20px;
            padding: 10px;
            background-color: #f5f5f5;
            border-left: 4px solid #333;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ccc;
        }
        .signature {
            margin-top: 40px;
        }
        strong {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $admitCard->data['school_name'] ?? 'School Name' }}</h2>
        <p>{{ $admitCard->data['academic_session'] ?? 'Academic Session' }}</p>
    </div>

    <div class="content">
        @php
            $studentPhoto = $admitCard->student && $admitCard->student->photo && file_exists(public_path('storage/' . $admitCard->student->photo))
                ? public_path('storage/' . $admitCard->student->photo)
                : public_path('images/default-avatar.png');
        @endphp
        <img src="{{ $studentPhoto }}" alt="Student Photo" style="width: 80px; height: 90px; object-fit: cover; float: right; border: 1px solid #333; margin-left: 10px;">
        <div class="info-row">
            <div class="info-col">
                <div class="info-item"><strong>Student Name:</strong> {{ $admitCard->data['student_name'] ?? 'N/A' }}</div>
                <div class="info-item"><strong>Roll Number:</strong> {{ $admitCard->data['roll_number'] ?? 'N/A' }}</div>
                <div class="info-item"><strong>Class:</strong> {{ $admitCard->data['class_name'] ?? 'N/A' }}</div>
                <div class="info-item"><strong>Section:</strong> {{ $admitCard->data['section'] ?? 'N/A' }}</div>
                <div class="info-item"><strong>Date of Birth:</strong> {{ $admitCard->data['dob'] ?? 'N/A' }}</div>
            </div>
            <div class="info-col">
                <div class="info-item"><strong>Exam Name:</strong> {{ $admitCard->data['exam_name'] ?? 'N/A' }}</div>
                <div class="info-item"><strong>Exam Date:</strong> {{ $admitCard->data['exam_date'] ?? 'N/A' }}</div>
                <div class="info-item"><strong>Exam Time:</strong> {{ $admitCard->data['exam_time'] ?? 'N/A' }}</div>
                @php
                    $seating = \App\Models\ExamSeatingArrangement::where('exam_id', $admitCard->exam_id)
                        ->where('student_id', $admitCard->student_id)
                        ->first();
                @endphp
                <div class="info-item"><strong>Exam Room:</strong> {{ $seating->room_number ?? 'Not Assigned' }}</div>
                <div class="info-item"><strong>Seat Number:</strong> {{ $seating->seat_number ?? 'Not Assigned' }}</div>
            </div>
        </div>

        <div class="subjects">
            <h4>Subjects:</h4>
            <ul>
                @foreach($admitCard->data['subjects'] ?? [] as $subject)
                    <li>{{ $subject }}</li>
                @endforeach
            </ul>
        </div>

        <div class="instructions">
            <h4>Instructions:</h4>
            <p>{{ $admitCard->data['instructions'] ?? 'No instructions available.' }}</p>
        </div>
    </div>

    <div class="footer">
        <p><em>Please bring this admit card along with valid ID proof for the examination.</em></p>
        <div class="signature">
            <p><strong>Principal Signature</strong></p>
            <hr style="width: 150px; margin: 20px auto;">
        </div>
    </div>
</body>
</html>