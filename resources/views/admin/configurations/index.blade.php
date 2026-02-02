@extends('layouts.admin')

@section('title', 'Admin Configuration Panel')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-gear"></i> Admin Configuration Panel
                    </h6>
                    <p class="text-muted mb-0">Configure all system modules and features from this centralized panel</p>
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
                    
                    <form action="{{ route('admin.configurations.update') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            @foreach($modules as $moduleKey => $module)
                                <div class="col-md-6 col-lg-4 mb-4">
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
                                                                   name="configurations[{{ $loop->index }}][value]" 
                                                                   id="{{ $moduleKey }}_{{ $configKey }}"
                                                                   {{ $config->getValue() ? 'checked' : '' }}
                                                                   onchange="this.form.submit()">
                                                            <input type="hidden" name="configurations[{{ $loop->index }}][module]" value="{{ $moduleKey }}">
                                                            <input type="hidden" name="configurations[{{ $loop->index }}][key]" value="{{ $configKey }}">
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
                                                               name="configurations[{{ $loop->index }}][value]" 
                                                               value="{{ $config->getValue() }}"
                                                               onchange="this.form.submit()">
                                                        <input type="hidden" name="configurations[{{ $loop->index }}][module]" value="{{ $moduleKey }}">
                                                        <input type="hidden" name="configurations[{{ $loop->index }}][key]" value="{{ $configKey }}">
                                                    @else
                                                        <label for="{{ $moduleKey }}_{{ $configKey }}" class="form-label">
                                                            {{ $config->label }}
                                                        </label>
                                                        <input type="text" 
                                                               class="form-control" 
                                                               id="{{ $moduleKey }}_{{ $configKey }}"
                                                               name="configurations[{{ $loop->index }}][value]" 
                                                               value="{{ $config->getValue() }}"
                                                               onchange="this.form.submit()">
                                                        <input type="hidden" name="configurations[{{ $loop->index }}][module]" value="{{ $moduleKey }}">
                                                        <input type="hidden" name="configurations[{{ $loop->index }}][key]" value="{{ $configKey }}">
                                                    @endif
                                                    
                                                    @if($config->description)
                                                        <small class="form-text text-muted">{{ $config->description }}</small>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
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

// Auto-save functionality for better UX
document.addEventListener('DOMContentLoaded', function() {
    // Add a small delay to prevent too many requests
    let saveTimeout;
    
    document.querySelectorAll('input[type="checkbox"], input[type="number"], input[type="text"]').forEach(input => {
        input.addEventListener('change', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                this.form.submit();
            }, 1000);
        });
    });
});
</script>
@endsection
