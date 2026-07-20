@extends('layouts.app')

@section('title', 'Two-Factor Authentication')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-shield-lock"></i> Two-Factor Authentication</h4>
                </div>
                <div class="card-body">
                    @if($enabled)
                        <!-- 2FA Enabled State -->
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> Two-factor authentication is currently <strong>enabled</strong> for your account.
                        </div>

                        <h5 class="mt-4">Manage Two-Factor Authentication</h5>
                        
                        <div class="d-grid gap-2 mt-3">
                            <button class="btn btn-warning" onclick="regenerateCodes()">
                                <i class="bi bi-arrow-repeat"></i> Regenerate Recovery Codes
                            </button>
                            <button class="btn btn-danger" onclick="disable2FA()">
                                <i class="bi bi-x-circle"></i> Disable Two-Factor Authentication
                            </button>
                        </div>

                        <!-- Recovery Codes Display -->
                        <div id="recoveryCodesDisplay" class="mt-4" style="display: none;">
                            <div class="alert alert-warning">
                                <h5><i class="bi bi-exclamation-triangle"></i> Recovery Codes</h5>
                                <p>Store these codes in a secure location. They can be used to access your account if you lose your authenticator device.</p>
                                <div id="recoveryCodesList" class="bg-light p-3 rounded">
                                    <!-- Recovery codes will be displayed here -->
                                </div>
                            </div>
                        </div>

                    @else
                        <!-- 2FA Disabled State -->
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> Two-factor authentication is currently <strong>disabled</strong>.
                        </div>

                        <p>Add an extra layer of security to your account by enabling two-factor authentication.</p>
                        
                        <h5 class="mt-4">How it works:</h5>
                        <ol>
                            <li>Click "Enable 2FA" below</li>
                            <li>Scan the QR code with your authenticator app (Google Authenticator, Authy, etc.)</li>
                            <li>Enter the 6-digit code from your app to verify</li>
                            <li>Save your recovery codes in a secure location</li>
                        </ol>

                        <button class="btn btn-primary btn-lg mt-3" onclick="enable2FA()">
                            <i class="bi bi-shield-check"></i> Enable Two-Factor Authentication
                        </button>

                        <!-- Setup Modal Content -->
                        <div id="setupContainer" style="display: none;">
                            <div class="card mt-4">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">Step 1: Scan QR Code</h5>
                                </div>
                                <div class="card-body text-center">
                                    <div id="qrCodeContainer" class="mb-3">
                                        <!-- QR code will be displayed here -->
                                    </div>
                                    <p class="text-muted">Or enter this code manually:</p>
                                    <code id="secretKey" class="bg-light p-2"></code>
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">Step 2: Verify Code</h5>
                                </div>
                                <div class="card-body">
                                    <p>Enter the 6-digit code from your authenticator app:</p>
                                    <input type="text" id="verificationCode" class="form-control form-control-lg text-center" maxlength="6" placeholder="000000" style="font-size: 2rem; letter-spacing: 1rem;">
                                    <button class="btn btn-success btn-lg w-100 mt-3" onclick="verify2FA()">
                                        <i class="bi bi-check-circle"></i> Verify and Enable
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Disable 2FA Modal -->
                    <div id="disableModal" style="display: none;">
                        <div class="card mt-3 border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">Confirm Disable</h5>
                            </div>
                            <div class="card-body">
                                <p>Enter your password to confirm disabling two-factor authentication:</p>
                                <input type="password" id="confirmPassword" class="form-control" placeholder="Your Password">
                                <div class="d-grid gap-2 mt-3">
                                    <button class="btn btn-danger" onclick="confirmDisable2FA()">
                                        <i class="bi bi-x-circle"></i> Confirm Disable
                                    </button>
                                    <button class="btn btn-secondary" onclick="cancelDisable()">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
async function enable2FA() {
    try {
        const response = await fetch('{{ route("two-factor.enable") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('setupContainer').style.display = 'block';
            document.getElementById('qrCodeContainer').innerHTML = `<img src="data:image/svg+xml;base64,${data.qr_code}" alt="QR Code">`;
            document.getElementById('secretKey').textContent = data.secret;
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to enable 2FA');
    }
}

async function verify2FA() {
    const code = document.getElementById('verificationCode').value;
    
    if (code.length !== 6) {
        alert('Please enter a 6-digit code');
        return;
    }
    
    try {
        const response = await fetch('{{ route("two-factor.verify") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ code })
        });
        
        const data = await response.json();
        
        if (data.success) {
            displayRecoveryCodes(data.recovery_codes);
            setTimeout(() => {
                location.reload();
            }, 5000);
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Verification failed');
    }
}

function disable2FA() {
    document.getElementById('disableModal').style.display = 'block';
}

function cancelDisable() {
    document.getElementById('disableModal').style.display = 'none';
    document.getElementById('confirmPassword').value = '';
}

async function confirmDisable2FA() {
    const password = document.getElementById('confirmPassword').value;
    
    if (!password) {
        alert('Please enter your password');
        return;
    }
    
    try {
        const response = await fetch('{{ route("two-factor.disable") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to disable 2FA');
    }
}

async function regenerateCodes() {
    if (!confirm('Are you sure you want to regenerate recovery codes? Old codes will no longer work.')) {
        return;
    }
    
    try {
        const response = await fetch('{{ route("two-factor.regenerate-codes") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            displayRecoveryCodes(data.recovery_codes);
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to regenerate codes');
    }
}

function displayRecoveryCodes(codes) {
    const codesList = codes.map(code => `<code class="d-block p-2 mb-1">${code}</code>`).join('');
    document.getElementById('recoveryCodesList').innerHTML = codesList;
    document.getElementById('recoveryCodesDisplay').style.display = 'block';
}
</script>
@endpush
@endsection
