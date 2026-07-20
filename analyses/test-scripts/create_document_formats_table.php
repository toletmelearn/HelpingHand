<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

// Check if table exists
if (!Schema::hasTable('document_formats')) {
    // Create the table directly with SQL
    DB::statement("
        CREATE TABLE document_formats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(100) NULL,
            description TEXT NULL,
            template_content JSON NULL,
            css_styles JSON NULL,
            header_content TEXT NULL,
            footer_content TEXT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            is_default BOOLEAN DEFAULT FALSE,
            page_size VARCHAR(20) DEFAULT 'A4',
            orientation ENUM('portrait', 'landscape') DEFAULT 'portrait',
            margin_top DECIMAL(8, 2) DEFAULT 10.00,
            margin_bottom DECIMAL(8, 2) DEFAULT 10.00,
            margin_left DECIMAL(8, 2) DEFAULT 10.00,
            margin_right DECIMAL(8, 2) DEFAULT 10.00,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )
    ");
    
    // Add indexes for better performance
    DB::statement("CREATE INDEX idx_document_formats_is_active ON document_formats(is_active)");
    DB::statement("CREATE INDEX idx_document_formats_is_default ON document_formats(is_default)");
    DB::statement("CREATE INDEX idx_document_formats_type ON document_formats(type)");
    
    echo "Table document_formats created successfully!\n";
} else {
    echo "Table document_formats already exists.\n";
}

// Insert some sample document format data
$sampleFormats = [
    [
        'name' => 'Standard Certificate',
        'type' => 'certificate',
        'description' => 'Standard certificate format for academic achievements',
        'template_content' => json_encode([
            'header' => '<div class="header">Certificate Header</div>',
            'body' => '<div class="body">Certificate Body Content</div>',
            'footer' => '<div class="footer">Certificate Footer</div>'
        ]),
        'css_styles' => json_encode([
            'font_family' => 'Arial, sans-serif',
            'font_size' => '12pt',
            'colors' => [
                'header' => '#000000',
                'body' => '#333333',
                'footer' => '#666666'
            ]
        ]),
        'header_content' => '<h1>Standard Certificate Header</h1>',
        'footer_content' => '<p>Footer content for certificates</p>',
        'is_active' => true,
        'is_default' => true,
        'page_size' => 'A4',
        'orientation' => 'portrait',
        'margin_top' => 10.00,
        'margin_bottom' => 10.00,
        'margin_left' => 10.00,
        'margin_right' => 10.00,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'name' => 'Admit Card Template',
        'type' => 'admit-card',
        'description' => 'Template for examination admit cards',
        'template_content' => json_encode([
            'header' => '<div class="header">Admit Card Header</div>',
            'body' => '<div class="body">Admit Card Body with student details</div>',
            'footer' => '<div class="footer">Admit Card Footer</div>'
        ]),
        'css_styles' => json_encode([
            'font_family' => 'Times New Roman, serif',
            'font_size' => '10pt',
            'colors' => [
                'header' => '#0000FF',
                'body' => '#000000',
                'footer' => '#0000FF'
            ]
        ]),
        'header_content' => '<h1>Examination Admit Card</h1>',
        'footer_content' => '<p>Valid for examination only</p>',
        'is_active' => true,
        'is_default' => false,
        'page_size' => 'A4',
        'orientation' => 'portrait',
        'margin_top' => 15.00,
        'margin_bottom' => 15.00,
        'margin_left' => 12.00,
        'margin_right' => 12.00,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'name' => 'Report Card Format',
        'type' => 'report',
        'description' => 'Format for student report cards',
        'template_content' => json_encode([
            'header' => '<div class="header">Report Card Header</div>',
            'body' => '<div class="body">Report Card with grades and remarks</div>',
            'footer' => '<div class="footer">Report Card Footer</div>'
        ]),
        'css_styles' => json_encode([
            'font_family' => 'Helvetica, Arial, sans-serif',
            'font_size' => '11pt',
            'colors' => [
                'header' => '#2E8B57',
                'body' => '#2F4F4F',
                'footer' => '#2E8B57'
            ]
        ]),
        'header_content' => '<h1>Student Report Card</h1>',
        'footer_content' => '<p>Generated on: {{date}}</p>',
        'is_active' => true,
        'is_default' => false,
        'page_size' => 'A4',
        'orientation' => 'portrait',
        'margin_top' => 20.00,
        'margin_bottom' => 20.00,
        'margin_left' => 15.00,
        'margin_right' => 15.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]
];

$existingCount = DB::table('document_formats')->count();
if ($existingCount == 0) {
    foreach ($sampleFormats as $format) {
        DB::table('document_formats')->insert($format);
    }
    echo count($sampleFormats) . " sample document formats inserted successfully.\n";
} else {
    echo $existingCount . " document formats already exist in the table.\n";
}