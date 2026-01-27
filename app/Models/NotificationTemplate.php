<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class NotificationTemplate extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'name',
        'event_type',
        'channel',
        'subject',
        'template_content',
        'variables',
        'is_active',
        'delay_minutes',
        'recipient_roles',
        'conditions',
        'description',
        'template_format', // pdf, excel, csv, html
        'header_config',
        'footer_config',
        'layout_config',
        'styles',
        'permissions',
        'category', // notification, report, export
        'preview_image',
    ];
    
    protected $casts = [
        'variables' => 'array',
        'recipient_roles' => 'array',
        'conditions' => 'array',
        'header_config' => 'array',
        'footer_config' => 'array',
        'layout_config' => 'array',
        'styles' => 'array',
        'permissions' => 'array',
        'is_active' => 'boolean',
        'delay_minutes' => 'integer',
    ];
    
    protected $appends = [
        'display_name',
        'is_delayed',
    ];
    
    // Accessors
    public function getDisplayNameAttribute()
    {
        return $this->name . ' (' . $this->event_type . ' - ' . $this->channel . ')';
    }
    
    public function getIsDelayedAttribute()
    {
        return $this->delay_minutes > 0;
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeForEvent($query, $eventType)
    {
        return $query->where('event_type', $eventType);
    }
    
    public function scopeForChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }
    
    // Methods
    public function render($variables = [])
    {
        $content = $this->template_content;
        
        // Replace variables in the template
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        return $content;
    }
    
    public function getAvailableVariables()
    {
        $defaultVariables = [
            'teacher_name',
            'date',
            'arrival_time',
            'departure_time',
            'late_minutes',
            'early_departure_minutes',
            'working_hours',
            'school_name',
            'admin_contact'
        ];
        
        return array_merge($defaultVariables, $this->variables ?: []);
    }
    
    public function shouldSend($eventType, $data = [])
    {
        if (!$this->is_active || $this->event_type !== $eventType) {
            return false;
        }
        
        // Check conditions if any
        if ($this->conditions) {
            foreach ($this->conditions as $condition) {
                if (!$this->evaluateCondition($condition, $data)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    private function evaluateCondition($condition, $data)
    {
        // Simple condition evaluation
        // In production, you might want to use a proper expression evaluator
        if (isset($condition['field']) && isset($condition['operator']) && isset($condition['value'])) {
            $fieldValue = $data[$condition['field']] ?? null;
            $expectedValue = $condition['value'];
            
            switch ($condition['operator']) {
                case '=':
                case '==':
                    return $fieldValue == $expectedValue;
                case '>':
                    return $fieldValue > $expectedValue;
                case '<':
                    return $fieldValue < $expectedValue;
                case '>=':
                    return $fieldValue >= $expectedValue;
                case '<=':
                    return $fieldValue <= $expectedValue;
                case '!=':
                    return $fieldValue != $expectedValue;
                default:
                    return false;
            }
        }
        
        return true;
    }
    
    public function getDelayTime()
    {
        if ($this->delay_minutes > 0) {
            return Carbon::now()->addMinutes($this->delay_minutes);
        }
        return Carbon::now();
    }
    
    /**
     * Scope to get only report templates
     */
    public function scopeReportTemplates($query)
    {
        return $query->where('category', 'report')->orWhere('category', 'export');
    }
    
    /**
     * Scope to get only notification templates
     */
    public function scopeNotificationTemplates($query)
    {
        return $query->where('category', 'notification');
    }
    
    /**
     * Check if template is compatible with export format
     */
    public function isCompatibleWithFormat($format)
    {
        $supportedFormats = ['pdf', 'excel', 'csv', 'html'];
        
        if (!$this->template_format) {
            return true; // If no format specified, assume compatible with all
        }
        
        return $this->template_format === $format || $this->template_format === 'all';
    }
    
    /**
     * Get available template variables
     */
    public function getAvailableVariablesExtended()
    {
        $defaultVariables = [
            'company_name',
            'report_title',
            'generated_date',
            'logo_url',
            'signature_url',
            'footer_text',
            'table_data',
            'chart_data',
            'summary_data',
            'teacher_name',
            'department',
            'attendance_data',
            'performance_metrics',
            'analytics_data',
        ];
        
        return array_merge($defaultVariables, $this->variables ?: []);
    }
    
    /**
     * Apply template styling to content
     */
    public function applyStyling($content)
    {
        if ($this->styles && is_array($this->styles)) {
            $styledContent = '<div class="template-wrapper" style="' . ($this->styles['wrapper'] ?? '') . '">
                ' . $content . '
            </div>';
            
            return $styledContent;
        }
        
        return $content;
    }
    
    /**
     * Get header content
     */
    public function getHeaderContent()
    {
        if (!$this->header_config) {
            return '';
        }
        
        $header = '<div class="template-header" style="' . ($this->header_config['styles'] ?? '') . '">
            <div class="header-content">
                ' . ($this->header_config['content'] ?? '') . '
            </div>
        </div>';
        
        return $header;
    }
    
    /**
     * Get footer content
     */
    public function getFooterContent()
    {
        if (!$this->footer_config) {
            return '';
        }
        
        $footer = '<div class="template-footer" style="' . ($this->footer_config['styles'] ?? '') . '">
            <div class="footer-content">
                ' . ($this->footer_config['content'] ?? '') . '
            </div>
        </div>';
        
        return $footer;
    }
}
