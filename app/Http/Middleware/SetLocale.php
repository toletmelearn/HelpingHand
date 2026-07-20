<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use App\Models\Language;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if language is set in session
        if (Session::has('app_language')) {
            $locale = Session::get('app_language');
        } 
        // Check if user is authenticated and has language preference
        elseif (auth()->check() && auth()->user()->preferred_language) {
            $locale = auth()->user()->preferred_language;
            Session::put('app_language', $locale);
        }
        // Use default language from database
        else {
            $defaultLanguage = Language::where('is_default', true)->first();
            $locale = $defaultLanguage ? $defaultLanguage->code : config('app.locale', 'en');
            Session::put('app_language', $locale);
        }

        // Verify language exists and is active
        $language = Language::where('code', $locale)->where('is_active', true)->first();
        
        if ($language) {
            App::setLocale($language->code);
        } else {
            // Fallback to default
            App::setLocale(config('app.locale', 'en'));
        }

        return $next($request);
    }
}
