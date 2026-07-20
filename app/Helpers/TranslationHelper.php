<?php

if (!function_exists('__t')) {
    /**
     * Translate a key using database translations
     * 
     * @param string $key
     * @param array $replace
     * @param string|null $locale
     * @return string
     */
    function __t($key, $replace = [], $locale = null)
    {
        $locale = $locale ?? app()->getLocale();
        
        // Try to get translation from database
        $translation = \App\Models\Translation::translate($key, $locale);
        
        // If no translation found, try Laravel's default translation
        if ($translation === $key && function_exists('__')) {
            $translation = __($key, $replace, $locale);
        }
        
        // Replace placeholders
        if (!empty($replace)) {
            foreach ($replace as $placeholder => $value) {
                $translation = str_replace(':' . $placeholder, $value, $translation);
            }
        }
        
        return $translation;
    }
}

if (!function_exists('get_available_languages')) {
    /**
     * Get all active languages
     * 
     * @return \Illuminate\Support\Collection
     */
    function get_available_languages()
    {
        return \App\Models\Language::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}

if (!function_exists('get_current_language')) {
    /**
     * Get current active language
     * 
     * @return \App\Models\Language|null
     */
    function get_current_language()
    {
        $locale = app()->getLocale();
        return \App\Models\Language::where('code', $locale)->first();
    }
}

if (!function_exists('trans_choice_db')) {
    /**
     * Translate with pluralization from database
     * 
     * @param string $key
     * @param int $count
     * @param array $replace
     * @param string|null $locale
     * @return string
     */
    function trans_choice_db($key, $count, $replace = [], $locale = null)
    {
        $translation = __t($key, $replace, $locale);
        
        // Simple pluralization logic
        if ($count != 1 && !str_contains($translation, '|')) {
            $translation .= 's';
        }
        
        return $translation;
    }
}
