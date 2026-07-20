<?php
$output = "Static Controller Middleware Analysis\n";
$output .= "=====================================\n\n";

function analyzeDirectory($dir) {
    global $output;
    $files = glob($dir . '/*.php');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $className = basename($file, '.php');
        
        // Find constructor
        if (preg_match('/public\s+function\s+__construct\s*\((.*?)\)\s*\{(.*?)\}/s', $content, $matches)) {
            $constructorBody = $matches[2];
            // Find middleware calls
            if (preg_match_all('/\$this->middleware\((.*?)\);/', $constructorBody, $midMatches)) {
                $output .= "Controller: {$className}\n";
                foreach ($midMatches[1] as $midCall) {
                    $output .= "  - Middleware: " . trim($midCall) . "\n";
                }
                $output .= "\n";
            }
        }
    }
}

analyzeDirectory(__DIR__ . '/../app/Http/Controllers');
analyzeDirectory(__DIR__ . '/../app/Http/Controllers/Admin');

file_put_contents(__DIR__ . '/controller_middleware.txt', $output);
echo "Analysis written to controller_middleware.txt";
