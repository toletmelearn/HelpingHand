<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ApiAccessControl
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Rate limiting
        $rateLimitResponse = $this->rateLimit($request);

        if ($rateLimitResponse instanceof Response) {
            return $rateLimitResponse;
        }
        
        // API access logging
        $this->logApiAccess($request);
        
        // Role-based access control
        if (!$this->authorizeRequest($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Insufficient permissions.',
                'timestamp' => now()->toISOString()
            ], 403);
        }
        
        return $next($request);
    }
    
    /**
     * Apply rate limiting to API requests
     */
    private function rateLimit(Request $request)
    {
        $key = $this->resolveRequestSignature($request);
        
        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts())) {
            $retryAfter = RateLimiter::availableIn($key);
            
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $retryAfter,
                'timestamp' => now()->toISOString()
            ], 429)->header('Retry-After', $retryAfter);
        }
        
        RateLimiter::hit($key, $this->decayMinutes() * 60);
    }
    
    /**
     * Resolve request signature for rate limiting
     */
    private function resolveRequestSignature(Request $request)
    {
        $userIdentifier = Auth::check() ? Auth::id() : $request->ip();
        return 'api:' . $userIdentifier . ':' . $request->path();
    }
    
    /**
     * Maximum number of attempts allowed
     */
    private function maxAttempts()
    {
        return Auth::check() ? 60 : 10; // 60 for authenticated, 10 for unauthenticated
    }
    
    /**
     * Decay time in minutes
     */
    private function decayMinutes()
    {
        return 1; // 1 minute window
    }
    
    /**
     * Log API access for audit purposes
     */
    private function logApiAccess(Request $request)
    {
        // Log API access to database or file
        // This could integrate with the existing AuditLog system
        \Log::info('API Access', [
            'user_id' => Auth::check() ? Auth::id() : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'timestamp' => now()->toISOString()
        ]);
    }
    
    /**
     * Authorize the request based on user roles and permissions
     */
    private function authorizeRequest(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if (in_array($routeName, $this->publicTemporaryBlocklist(), true)) {
            return false;
        }

        if (in_array($routeName, $this->publicAllowlist(), true)) {
            return true;
        }

        $user = $request->user();

        if (!$user instanceof User) {
            return false;
        }

        if ($this->isAdmin($user) && $this->tokenAllows($user, 'mobile:admin')) {
            return true;
        }

        if (in_array($routeName, $this->oldTokenRecoveryRoutes(), true)) {
            return true;
        }

        if (in_array($routeName, $this->authSelfRoutes(), true)) {
            return $this->tokenAllows($user, 'mobile:user');
        }

        if (in_array($routeName, $this->notificationRoutes(), true)) {
            return $this->tokenAllows($user, 'mobile:user');
        }

        if (in_array($routeName, $this->highRiskBlocklist(), true)) {
            return false;
        }

        if (in_array($routeName, $this->parentBlockedRoutes(), true)) {
            return false;
        }

        if (in_array($routeName, $this->studentSelfRoutes(), true)) {
            return $this->hasRole($user, 'student')
                && $this->isStudentSelf($request, $user)
                && $this->tokenAllows($user, 'mobile:student');
        }

        if (in_array($routeName, $this->teacherSelfRoutes(), true)) {
            return $this->hasRole($user, 'teacher')
                && $this->isTeacherSelf($request, $user)
                && $this->tokenAllows($user, 'mobile:teacher');
        }

        return false;
    }

    private function publicAllowlist(): array
    {
        return [
            'api.v1.login',
            'api.v1.register',
            'api.v1.bell-timing.today',
        ];
    }

    private function publicTemporaryBlocklist(): array
    {
        return [
            'api.v1.exam-papers.available-for-class',
            'api.v1.exam-papers.search',
        ];
    }

    private function authSelfRoutes(): array
    {
        return [
            'api.v1.me',
            'api.v1.update-profile',
            'api.v1.change-password',
        ];
    }

    private function oldTokenRecoveryRoutes(): array
    {
        return [
            'api.v1.logout',
            'api.v1.logout-all',
            'api.v1.refresh-token',
        ];
    }

    private function notificationRoutes(): array
    {
        return [
            'api.v1.notifications.index',
            'api.v1.notifications.mark-as-read',
            'api.v1.notifications.mark-all-read',
            'api.v1.notifications.unread-count',
        ];
    }

    private function studentSelfRoutes(): array
    {
        return [
            'api.v1.dashboard.student',
            'api.v1.students.show',
            'api.v1.students.attendance',
            'api.v1.students.results',
            'api.v1.students.fees',
            'api.v1.attendance.student-monthly',
        ];
    }

    private function teacherSelfRoutes(): array
    {
        return [
            'api.v1.dashboard.teacher',
            'api.v1.teachers.show',
            'api.v1.teachers.classes',
            'api.v1.teachers.papers',
            'api.v1.teachers.subject-classes',
            'api.v1.teachers.attendance-data',
            'api.v1.teachers.grading-data',
            'api.v1.lesson-plans.my',
        ];
    }

    private function parentBlockedRoutes(): array
    {
        return [
            'api.v1.dashboard.parent',
            'api.v1.guardians.index',
            'api.v1.guardians.store',
            'api.v1.guardians.show',
            'api.v1.guardians.update',
            'api.v1.guardians.destroy',
            'api.v1.guardians.children',
            'api.v1.guardians.notifications',
        ];
    }

    private function highRiskBlocklist(): array
    {
        return [
            'api.v1.students.index',
            'api.v1.students.store',
            'api.v1.students.update',
            'api.v1.students.destroy',
            'api.v1.teachers.index',
            'api.v1.teachers.store',
            'api.v1.teachers.update',
            'api.v1.teachers.destroy',
            'api.v1.guardians.index',
            'api.v1.guardians.store',
            'api.v1.guardians.show',
            'api.v1.guardians.update',
            'api.v1.guardians.destroy',
            'api.v1.guardians.children',
            'api.v1.guardians.notifications',
            'api.v1.attendance.index',
            'api.v1.attendance.store',
            'api.v1.attendance.show',
            'api.v1.attendance.update',
            'api.v1.attendance.destroy',
            'api.v1.attendance.daily-report',
            'api.v1.attendance.bulk-mark',
            'api.v1.exam-papers.index',
            'api.v1.exam-papers.store',
            'api.v1.exam-papers.update',
            'api.v1.exam-papers.destroy',
            'api.v1.exam-papers.toggle-publish',
            'api.v1.bell-timing.store',
            'api.v1.bell-timing.update',
            'api.v1.bell-timing.destroy',
            'api.v1.bell-timing.bulk-create',
            'api.v1.lesson-plans.store',
            'api.v1.lesson-plans.update',
        ];
    }

    private function isAdmin(User $user): bool
    {
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['admin', 'super-admin', 'super_admin']);
        }

        return $this->hasRole($user, 'admin')
            || $this->hasRole($user, 'super-admin')
            || $this->hasRole($user, 'super_admin');
    }

    private function isStudentSelf(Request $request, User $user): bool
    {
        if ($request->route()?->getName() === 'api.v1.dashboard.student') {
            return Student::where('user_id', $user->id)->exists();
        }

        $studentId = $this->routeParameterId($request, ['student', 'id', 'studentId']);

        if (!$studentId) {
            return false;
        }

        return Student::where('id', $studentId)
            ->where('user_id', $user->id)
            ->exists();
    }

    private function isTeacherSelf(Request $request, User $user): bool
    {
        $routeName = $request->route()?->getName();

        if (in_array($routeName, ['api.v1.dashboard.teacher', 'api.v1.lesson-plans.my'], true)) {
            return Teacher::where('user_id', $user->id)->exists();
        }

        $teacherId = $this->routeParameterId($request, ['teacher', 'id']);

        if (!$teacherId) {
            return false;
        }

        return Teacher::where('id', $teacherId)
            ->where('user_id', $user->id)
            ->exists();
    }

    private function routeParameterId(Request $request, array $names): ?int
    {
        foreach ($names as $name) {
            $value = $request->route($name);

            if (is_object($value) && isset($value->id)) {
                return (int) $value->id;
            }

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    private function hasRole(User $user, string $role): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole($role);
    }

    private function tokenAllows(User $user, array|string $abilities): bool
    {
        $token = $user->currentAccessToken();

        if (!$token || !method_exists($token, 'can')) {
            return false;
        }

        foreach ((array) $abilities as $ability) {
            if ($token->can($ability)) {
                return true;
            }
        }

        return false;
    }
}
