<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Result Card</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .result-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .result-container {
                box-shadow: none;
                padding: 10px;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        .print-button {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .print-button button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .print-button button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="result-container">
        <div class="print-button no-print">
            <button onclick="window.print()">🖨️ Print Result Card</button>
        </div>
        
        {!! $formattedResult !!}
    </div>
    
    <script>
        // Auto-print when page loads with print parameter
        window.onload = function() {
            if (window.location.search.includes('print=1')) {
                window.print();
            }
        }
    </script>
</body>
</html>