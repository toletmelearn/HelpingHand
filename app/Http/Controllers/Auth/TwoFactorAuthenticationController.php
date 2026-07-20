<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorAuthenticationController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->middleware('auth');
        $this->google2fa = new Google2FA();
    }

    /**
     * Show 2FA setup page
     */
    public function show()
    {
        $user = auth()->user();
        
        return view('auth.two-factor', [
            'user' => $user,
            'enabled' => $user->two_factor_enabled,
        ]);
    }

    /**
     * Enable 2FA
     */
    public function enable(Request $request)
    {
        $user = auth()->user();

        // Generate secret key
        $secret = $this->google2fa->generateSecretKey();
        
        // Store secret temporarily (not enabled yet until verified)
        $user->two_factor_secret = encrypt($secret);
        $user->save();

        // Generate QR Code
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $qrCode = $this->generateQrCode($qrCodeUrl);

        return response()->json([
            'success' => true,
            'secret' => $secret,
            'qr_code' => $qrCode,
            'message' => 'Scan the QR code with your authenticator app and verify with a code',
        ]);
    }

    /**
     * Verify and confirm 2FA
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = auth()->user();

        if (!$user->two_factor_secret) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is not set up',
            ], 400);
        }

        $secret = decrypt($user->two_factor_secret);
        $valid = $this->google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code',
            ], 400);
        }

        // Enable 2FA
        $user->two_factor_enabled = true;
        $user->two_factor_confirmed_at = now();
        
        // Generate recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();
        $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication enabled successfully',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = auth()->user();

        // Verify password
        if (!\Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password',
            ], 400);
        }

        // Disable 2FA
        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication disabled successfully',
        ]);
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $user = auth()->user();

        if (!$user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is not enabled',
            ], 400);
        }

        $recoveryCodes = $this->generateRecoveryCodes();
        $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Recovery codes regenerated successfully',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Challenge user for 2FA code during login
     */
    public function challenge(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = auth()->user();

        if (!$user->two_factor_enabled) {
            return redirect()->route('dashboard');
        }

        $secret = decrypt($user->two_factor_secret);
        
        // Try normal verification code
        $valid = $this->google2fa->verifyKey($secret, $request->code, 2);

        // If not valid, try recovery codes
        if (!$valid) {
            $valid = $this->verifyRecoveryCode($user, $request->code);
        }

        if (!$valid) {
            return back()->withErrors(['code' => 'Invalid verification code']);
        }

        // Mark as verified in session
        session(['two_factor_verified' => true]);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Generate QR Code
     */
    private function generateQrCode($url)
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        
        $writer = new Writer($renderer);
        return base64_encode($writer->writeString($url));
    }

    /**
     * Generate recovery codes
     */
    private function generateRecoveryCodes($count = 10)
    {
        $codes = [];
        
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        }
        
        return $codes;
    }

    /**
     * Verify recovery code
     */
    private function verifyRecoveryCode($user, $code)
    {
        if (!$user->two_factor_recovery_codes) {
            return false;
        }

        $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
        
        $codeIndex = array_search(strtoupper($code), $recoveryCodes);
        
        if ($codeIndex === false) {
            return false;
        }

        // Remove used recovery code
        unset($recoveryCodes[$codeIndex]);
        $user->two_factor_recovery_codes = encrypt(json_encode(array_values($recoveryCodes)));
        $user->save();

        return true;
    }
}
