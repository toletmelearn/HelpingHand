@extends('layouts.admin')

@section('title', 'Admin Configuration Panel')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="bi bi-gear"></i> Admin Configuration Panel
                        </h6>
                        <p class="text-muted mb-0">Configure all system modules and features from this centralized panel</p>
                    </div>
                    <div>
                        <button type="submit" form="configForm" class="btn btn-primary shadow-sm">
                            <i class="bi bi-save"></i> Save Configurations
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    
                    <form id="configForm" action="{{ route('admin.configurations.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        
                        @php $globalIndex = 0; @endphp
                        <div class="row">
                            @foreach($modules as $moduleKey => $module)
                                <div class="col-md-6 col-lg-4 mb-4" id="config-card-{{ $moduleKey }}">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="card-title mb-0">
                                                <i class="bi {{ $module['icon'] }}"></i> {{ $module['label'] }}
                                            </h6>
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                        onclick="resetModuleDefaults('{{ $moduleKey }}')">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Reset to Defaults
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            @foreach($module['configs'] as $configKey => $config)
                                                <div class="mb-3">
                                                    @if($config->type === 'boolean')
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" 
                                                                   type="checkbox" 
                                                                   name="configurations[{{ $globalIndex }}][value]" 
                                                                   id="{{ $moduleKey }}_{{ $configKey }}"
                                                                   {{ $config->getValue() ? 'checked' : '' }}>
                                                            <input type="hidden" name="configurations[{{ $globalIndex }}][module]" value="{{ $moduleKey }}">
                                                            <input type="hidden" name="configurations[{{ $globalIndex }}][key]" value="{{ $configKey }}">
                                                            <label class="form-check-label" for="{{ $moduleKey }}_{{ $configKey }}">
                                                                {{ $config->label }}
                                                            </label>
                                                        </div>
                                                    @elseif($config->type === 'integer')
                                                        <label for="{{ $moduleKey }}_{{ $configKey }}" class="form-label">
                                                            {{ $config->label }}
                                                        </label>
                                                        <input type="number" 
                                                               class="form-control" 
                                                               id="{{ $moduleKey }}_{{ $configKey }}"
                                                               name="configurations[{{ $globalIndex }}][value]" 
                                                               value="{{ $config->getValue() }}">
                                                        <input type="hidden" name="configurations[{{ $globalIndex }}][module]" value="{{ $moduleKey }}">
                                                        <input type="hidden" name="configurations[{{ $globalIndex }}][key]" value="{{ $configKey }}">
                                                    @elseif($config->type === 'file')
                                                        <label for="{{ $moduleKey }}_{{ $configKey }}" class="form-label">
                                                            {{ $config->label }}
                                                        </label>
                                                        <input type="file" 
                                                               class="form-control" 
                                                               id="{{ $moduleKey }}_{{ $configKey }}"
                                                               name="configurations[{{ $globalIndex }}][file]" 
                                                               accept="image/*">
                                                        <input type="hidden" name="configurations[{{ $globalIndex }}][module]" value="{{ $moduleKey }}">
                                                        <input type="hidden" name="configurations[{{ $globalIndex }}][key]" value="{{ $configKey }}">
                                                        @if($config->getValue())
                                                            <div class="mt-2 text-center p-2 bg-light border rounded">
                                                                 <img src="{{ asset('storage/' . $config->getValue()) }}" 
                                                                      alt="{{ $config->label }}" 
                                                                      style="max-height: 60px; max-width: 100%; object-fit: contain;">
                                                            </div>
                                                        @endif
                                                     @elseif($config->type === 'json')
                                                         <label for="{{ $moduleKey }}_{{ $configKey }}" class="form-label fw-bold">
                                                             {{ $config->label }}
                                                         </label>
                                                         <textarea class="form-control font-monospace" 
                                                                   id="{{ $moduleKey }}_{{ $configKey }}"
                                                                   name="configurations[{{ $globalIndex }}][value]" 
                                                                   rows="10">{{ is_string($config->value) ? $config->value : json_encode($config->getValue(), JSON_PRETTY_PRINT) }}</textarea>
                                                         <input type="hidden" name="configurations[{{ $globalIndex }}][module]" value="{{ $moduleKey }}">
                                                         <input type="hidden" name="configurations[{{ $globalIndex }}][key]" value="{{ $configKey }}">
                                                     @else
                                                         <label for="{{ $moduleKey }}_{{ $configKey }}" class="form-label">
                                                             {{ $config->label }}
                                                         </label>
                                                         <input type="text" 
                                                                class="form-control" 
                                                                id="{{ $moduleKey }}_{{ $configKey }}"
                                                                name="configurations[{{ $globalIndex }}][value]" 
                                                                value="{{ $config->getValue() }}">
                                                         <input type="hidden" name="configurations[{{ $globalIndex }}][module]" value="{{ $moduleKey }}">
                                                         <input type="hidden" name="configurations[{{ $globalIndex }}][key]" value="{{ $configKey }}">
                                                     @endif
                                                    
                                                    @if($config->description)
                                                        <small class="form-text text-muted">{{ $config->description }}</small>
                                                    @endif
                                                </div>
                                                @php $globalIndex++; @endphp
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="text-end mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
                                <i class="bi bi-save"></i> Save Configurations
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset to Defaults Modal -->
<div class="modal fade" id="resetModal" tabindex="-1" aria-labelledby="resetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetModalLabel">Reset Module to Defaults</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reset all configurations in this module to their default values?</p>
                <p class="text-warning"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="resetForm" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-warning">Reset to Defaults</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function resetModuleDefaults(module) {
    document.getElementById('resetForm').action = "{{ route('admin.configurations.reset-defaults') }}?module=" + module;
    var resetModal = new bootstrap.Modal(document.getElementById('resetModal'));
    resetModal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    // Hash tracking and highlight logic
    function handleHashChange() {
        if (window.location.hash) {
            const targetCard = document.querySelector(window.location.hash);
            if (targetCard) {
                setTimeout(() => {
                    targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const cardInner = targetCard.querySelector('.card');
                    if (cardInner) {
                        cardInner.style.border = '2px solid #fd7e14';
                        cardInner.style.boxShadow = '0 0 20px rgba(253, 126, 20, 0.4)';
                        setTimeout(() => {
                            cardInner.style.transition = 'all 1s ease-out';
                            cardInner.style.border = '';
                            cardInner.style.boxShadow = '';
                        }, 3000);
                    }
                }, 300);
            }
        }
    }

    handleHashChange();
    window.addEventListener('hashchange', handleHashChange);
});
</script>
@endsection
