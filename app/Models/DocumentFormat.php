<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DocumentFormat extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'template_content',
        'css_styles',
        'header_content',
        'footer_content',
        'is_active',
        'is_default',
        'page_size',
        'orientation',
        'margin_top',
        'margin_bottom',
        'margin_left',
        'margin_right',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'template_content' => 'array', // For storing structured template data
        'css_styles' => 'array', // For storing CSS styles
    ];

    /**
     * Scope to get active document formats
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get the default document format
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Set the default format (make sure only one is default at a time)
     */
    public static function setAsDefault($id)
    {
        // First, unset the current default
        self::where('is_default', true)->update(['is_default' => false]);
        
        // Then set the new one as default
        self::where('id', $id)->update(['is_default' => true]);
    }
}