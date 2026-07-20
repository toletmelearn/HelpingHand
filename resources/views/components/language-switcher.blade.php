<!-- Language Switcher Component -->
@php
    $currentLanguage = get_current_language();
    $availableLanguages = get_available_languages();
@endphp

@if($availableLanguages->count() > 1)
<div class="dropdown">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="languageSwitcher" data-bs-toggle="dropdown" aria-expanded="false">
        @if($currentLanguage)
            <span class="me-1">{{ $currentLanguage->flag_emoji }}</span>
            <span class="d-none d-md-inline">{{ $currentLanguage->name }}</span>
        @else
            <i class="bi bi-globe"></i> <span class="d-none d-md-inline">Language</span>
        @endif
    </button>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageSwitcher">
        @foreach($availableLanguages as $language)
            <li>
                <a class="dropdown-item {{ $currentLanguage && $currentLanguage->code === $language->code ? 'active' : '' }}" 
                   href="{{ route('admin.languages.switch', $language->code) }}">
                    <span class="me-2">{{ $language->flag_emoji }}</span>
                    {{ $language->name }}
                    @if($language->is_default)
                        <span class="badge bg-primary ms-2">Default</span>
                    @endif
                </a>
            </li>
        @endforeach
        
        @if(auth()->check() && auth()->user()->hasRole('admin'))
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item" href="{{ route('admin.languages.index') }}">
                    <i class="bi bi-gear"></i> Manage Languages
                </a>
            </li>
        @endif
    </ul>
</div>
@endif
