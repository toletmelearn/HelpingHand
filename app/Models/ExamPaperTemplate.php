<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamPaperTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'template_content',  // HTML content for the template
        'subject',
        'class_section',
        'academic_year',
        'is_active',
        'created_by',
        'updated_by',
        'header_content',
        'instruction_block',
        'footer_content',
        'section_config',    // JSON for sections (A, B, C, etc.)
        'marks_distribution', // JSON for marks distribution
        'metadata',
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'section_config' => 'array',
        'marks_distribution' => 'array',
        'metadata' => 'array',
    ];
    
    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeBySubject($query, $subject)
    {
        return $query->where('subject', $subject);
    }
    
    public function scopeByClass($query, $classSection)
    {
        return $query->where('class_section', $classSection);
    }
    
    // Methods
    public function getSections(): array
    {
        return $this->section_config ?? [];
    }
    
    public function getMarksDistribution(): array
    {
        return $this->marks_distribution ?? [];
    }
    
    public function renderTemplate(array $data = []): string
    {
        $template = $this->template_content;
        
        // Replace placeholders with actual data
        foreach ($data as $key => $value) {
            $template = str_replace('{'.$key.'}', $value, $template);
        }
        
        return $template;
    }
}
