<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseApiController
{
    /**
     * Login API
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'device_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->sendError('Invalid credentials', ['error' => 'Email or password is incorrect'], 401);
        }

        // The web login (CentralLoginController) already blocks deactivated
        // accounts -- this mirrors the same check so the mobile API can't be
        // used to bypass it.
        if ($user->status !== 'active') {
            return $this->sendError('Account inactive', ['error' => 'Your account is inactive. Please contact the administrator.'], 403);
        }

        // Create token
        $deviceName = $request->device_name ?? 'mobile-app';
        $token = $user->createToken($deviceName, $this->tokenAbilitiesFor($user))->plainTextToken;

        // Get user roles
        $roles = $user->roles->pluck('name');

        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
                'profile_photo' => $user->profile_photo_url ?? null,
            ]
        ], 'Login successful');
    }

    /**
     * Register API (if enabled)
     */
    public function register(Request $request)
    {
        // Registration disabled - users must be created by admin
        return $this->sendError('Registration disabled', ['error' => 'Please contact administrator for account creation'], 403);
    }

    /**
     * Logout API
     */
    public function logout(Request $request)
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return $this->sendResponse([], 'Logged out successfully');
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request)
    {
        // Revoke all tokens
        $request->user()->tokens()->delete();

        return $this->sendResponse([], 'Logged out from all devices successfully');
    }

    /**
     * Get authenticated user info
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $roles = $user->roles->pluck('name');

        return $this->sendResponse([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'roles' => $roles,
            'profile_photo' => $user->profile_photo_url ?? null,
            'preferred_language' => $user->preferred_language ?? 'en',
        ], 'User info retrieved successfully');
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|max:500',
            'preferred_language' => 'sometimes|string|in:en,hi',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $user = $request->user();
        $user->update($request->only(['name', 'phone', 'address', 'preferred_language']));

        return $this->sendResponse([
            'user' => $user
        ], 'Profile updated successfully');
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->sendError('Invalid current password', ['error' => 'Current password is incorrect'], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return $this->sendResponse([], 'Password changed successfully');
    }

    /**
     * Refresh token
     */
    public function refreshToken(Request $request)
    {
        $user = $request->user();
        
        // Delete current token
        $request->user()->currentAccessToken()->delete();
        
        // Create new token
        $deviceName = $request->device_name ?? 'mobile-app';
        $token = $user->createToken($deviceName, $this->tokenAbilitiesFor($user))->plainTextToken;

        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Token refreshed successfully');
    }

    private function tokenAbilitiesFor(User $user): array
    {
        $abilities = ['mobile:user'];

        if ($this->userHasAnyRole($user, ['admin', 'super-admin', 'super_admin'])) {
            $abilities[] = 'mobile:admin';
        }

        if ($this->userHasAnyRole($user, ['teacher'])) {
            $abilities[] = 'mobile:teacher';
        }

        if ($this->userHasAnyRole($user, ['student'])) {
            $abilities[] = 'mobile:student';
        }

        if ($this->userHasAnyRole($user, ['parent'])) {
            $abilities[] = 'mobile:parent';
        }

        return array_values(array_unique($abilities));
    }

    private function userHasAnyRole(User $user, array $roles): bool
    {
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }

        if (!method_exists($user, 'hasRole')) {
            return false;
        }

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
