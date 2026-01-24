<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $examPaper->title }} - Print</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .exam-details {
            margin-bottom: 20px;
        }
        .question {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .question-number {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .question-text {
            margin-left: 20px;
        }
        .marks {
            float: right;
            font-weight: bold;
            color: #007bff;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            border-top: 2px solid #000;
            padding-top: 10px;
            font-size: 12px;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.1;
            font-size: 100px;
            color: red;
            z-index: -1;
            pointer-events: none;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <!-- Watermark for exam papers -->
    <div class="watermark">CONFIDENTIAL</div>
    
    <div class="header">
        <h1>{{ config('app.name', 'School Name') }}</h1>
        <h2>{{ $examPaper->title }}</h2>
        <p><strong>Subject:</strong> {{ $examPaper->subject }} | 
           <strong>Class:</strong> {{ $examPaper->class_section }} | 
           <strong>Exam Type:</strong> {{ $examPaper->exam_type }}</p>
        <p><strong>Date:</strong> {{ $examPaper->exam_date ? $examPaper->exam_date->format('d-m-Y') : 'N/A' }} | 
           <strong>Time:</strong> {{ $examPaper->exam_time ?: 'N/A' }} | 
           <strong>Marks:</strong> {{ $examPaper->total_marks ?: $examPaper->auto_calculated_total ?: 'N/A' }}</p>
    </div>
    
    <div class="exam-details">
        @if($examPaper->instructions)
        <div class="instructions">
            <h3>Instructions:</h3>
            <p>{!! nl2br(e($examPaper->instructions)) !!}</p>
        </div>
        @endif
        
        @if($examPaper->questions_data)
        <div class="questions">
            <h3>Questions:</h3>
            @foreach($examPaper->questions_data as $index => $question)
            <div class="question">
                <div class="question-number">
                    Question {{ $index + 1 }}
                    <span class="marks">[{{ $question['marks'] ?? 0 }} marks]</span>
                </div>
                <div class="question-text">
                    {!! nl2br(e($question['text'] ?? '')) !!}
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
    
    <div class="footer">
        <p>Printed on: {{ now()->format('d-m-Y H:i:s') }} | Printed by: {{ auth()->user()->name ?? 'System' }}</p>
        <p>This is a confidential examination paper. Handle with care.</p>
    </div>
    
    <script>
        window.onload = function() {
            // Mark the paper as printed
            // We could make an AJAX call here to update print count, but for simplicity we'll just print
            window.print();
        };
    </script>
</body>
</html>