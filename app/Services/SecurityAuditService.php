<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class SecurityAuditService
{
    protected $securityLog = [];
    
    /**
     * Perform comprehensive security audit
     */
    public function performSecurityAudit()
    {
        $this->securityLog = [
            'audit_timestamp' => now()->toISOString(),
            'auditor' => \Illuminate\Support\Facades\Auth::user()->name ?? 'System',
            'findings' => []
        ];
        
        // Perform all security checks
        $this->checkAuthenticationSecurity();
        $this->checkAuthorizationSecurity();
        $this->checkInputValidation();
        $this->checkDatabaseSecurity();
        $this->checkFileSecurity();
        $this->checkNetworkSecurity();
        $this->checkApplicationSecurity();
        $this->checkSessionSecurity();
        $this->checkEncryptionSecurity();
        $this->checkLoggingSecurity();
        
        // Generate security report
        $this->generateSecurityReport();
        
        return $this->securityLog;
    }
    
    /**
     * Check authentication security
     */
    private function checkAuthenticationSecurity()
    {
        $findings = [];
        
        // Check password policies
        $weakPasswordUsers = DB::table('users')
            ->whereRaw("LENGTH(password) < 8")
            ->count();
            
        if ($weakPasswordUsers > 0) {
            $findings[] = [
                'type' => 'critical',
                'issue' => 'Weak Password Policy',
                'description' => "{$weakPasswordUsers} users have weak passwords",
                'recommendation' => 'Enforce minimum 8 character passwords with complexity requirements'
            ];
        }
        
        // Check password reset tokens
        $expiredTokens = DB::table('password_reset_tokens')
            ->where('created_at', '<', now()->subHours(24))
            ->count();
            
        if ($expiredTokens > 0) {
            $findings[] = [
                'type' => 'warning',
                'issue' => 'Expired Password Reset Tokens',
                'description' => "{$expiredTokens} expired password reset tokens found",
                'recommendation' => 'Clean up expired password reset tokens regularly'
            ];
        }
        
        // Check session timeout
        $configTimeout = config('session.lifetime', 120);
        if ($configTimeout > 240) {
            $findings[] = [
                'type' => 'warning',
                'issue' => 'Long Session Timeout',
                'description' => "Session timeout set to {$configTimeout} minutes",
                'recommendation' => 'Reduce session timeout to 120 minutes or less'
            ];
        }
        
        $this->securityLog['findings']['authentication'] = $findings;
    }
    
    /**
     * Check authorization security
     */
    private function checkAuthorizationSecurity()
    {
        $findings = [];
        
        // Check for unauthorized access attempts
        $failedLogins = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->where('name', 'like', '%Login%')
            ->count();
            
        if ($failedLogins > 10) {
            $findings[] = [
                'type' => 'warning',
                'issue' => 'High Failed Login Attempts',
                'description' => "{$failedLogins} failed login attempts in last 24 hours",
                'recommendation' => 'Implement rate limiting and IP blocking for failed logins'
            ];
        }
        
        // Check role assignments
        $usersWithoutRoles = DB::table('users')
            ->whereNotIn('id', function($query) {
                $query->select('user_id')->from('model_has_roles');
            })
            ->count();
            
        if ($usersWithoutRoles > 0) {
            $findings[] = [
                'type' => 'warning',
                'issue' => 'Users Without Roles',
                'description' => "{$usersWithoutRoles} users have no assigned roles",
                'recommendation' => 'Assign appropriate roles to all users'
            ];
        }
        
        // Check for privilege escalation
        $adminUsers = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'admin')
            ->count();
            
        if ($adminUsers > 5) {
            $findings[] = [
                'type' => 'warning',
                'issue' => 'High Admin User Count',
                'description' => "{$adminUsers} admin users found",
                'recommendation' => 'Review and minimize admin user accounts'
            ];
        }
        
        $this->securityLog['findings']['authorization'] = $findings;
    }
    
    /**
     * Check input validation
     */
    private function checkInputValidation()
    {
        $findings = [];
        
        // Check for SQL injection vulnerabilities
        $tables = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();
        $vulnerableFields = [];
        
        foreach ($tables as $table) {
            $columns = Schema::getColumnListing($table);
            foreach ($columns as $column) {
                // Check for columns that might be vulnerable
                if (in_array($column, ['email', 'phone', 'name', 'address'])) {
                    $sampleData = DB::table($table)->first();
                    if ($sampleData && isset($sampleData->$column)) {
                        $data = $sampleData->$column;
                        if (strlen($data) > 1000) {
                            $vulnerableFields[] = "{$table}.{$column}";
                        }
                    }
                }
            }
        }
        
        if (!empty($vulnerableFields)) {
            $findings[] = [
                'type' => 'critical',
                'issue' => 'Potential Input Validation Issues',
                'description' => 'Large input fields detected: ' . implode(', ', $vulnerableFields),
                'recommendation' => 'Implement proper input validation and sanitization'
            ];
        }
        
        // Check for XSS vulnerabilities
        $xssFields = DB::table('users')
            ->where('name', 'like', '%<script%')
            ->orWhere('email', 'like', '%<script%')
            ->count();
            
        if ($xssFields > 0) {
            $findings[] = [
                'type' => 'critical',
                'issue' => 'Potential XSS Vulnerabilities',
                'description' => "{$xssFields} records with potential XSS payloads",
                'recommendation' => 'Implement output escaping and Content Security Policy'
            ];
        }
        
        $this->securityLog['findings']['input_validation'] = $findings;
    }
    
    /**
     * Check database security
     */
    private function checkDatabaseSecurity()
    {
        $findings = [];
        
        // Check database connection security
        $connectionConfig = config('database.connections.mysql');
        if (!isset($connectionConfig['options'][\PDO::MYSQL_ATTR_SSL_CA])) {
            $findings[] = [
                'type' => 'warning',
                'issue' => 'No SSL Connection',
                'description' => 'Database connection not using SSL encryption',
                'recommendation' => 'Enable SSL for database connections'
            ];
        }
        
        // Check for exposed database credentials
        $envFiles = ['.env', '.env.example'];
        foreach ($envFiles as $file) {
            if (file_exists(base_path($file))) {
                $content = file_get_contents(base_path($file));
                if (strpos($content, 'DB_PASSWORD') !== false && 
                    strpos($content, 'password') !== false) {
                    $findings[] = [
                        'type' => 'warning',
                        'issue' => 'Exposed Database Credentials',
                        'description' => "Database credentials found in {$file}",
                        'recommendation' => 'Secure .env files and use proper secret management'
                    ];
                }
            }
        }
        
        // Check for backup security
        $backupFiles = glob(storage_path('app/backup_*.sql'));
        $unprotectedBackups = 0;
        
        foreach ($backupFiles as $backup) {
            if (!str_contains($backup, 'protected')) {
                $unprotectedBackups++;
            }
        }
        
        if ($unprotectedBackups > 0) {
            $findings[] = [
                'type' => 'warning',
                'issue' => 'Unprotected Database Backups',
                'description' => "{$unprotectedBackups} unprotected database backup files found",
                'recommendation' => 'Encrypt database backups and secure storage'
            ];
        }
        
        $this->securityLog['findings']['database'] = $findings;
    }
    
    /**
     * Check file security
     */
    private function checkFileSecurity()
    {
        $findings = [];
        
        // Check file upload security
        $uploadPaths = ['public/uploads', 'storage/app/public'];
        foreach ($uploadPaths as $path) {
            if (is_dir(public_path($path))) {
                $files = glob(public_path($path . '/*'));
                $executableFiles = 0;
                
                foreach ($files as $file) {
                    if (is_file($file) && is_executable($file)) {
                        $executableFiles++;
                    }
                }
                
                if ($executableFiles > 0) {
                    $findings[] = [
                        'type' => 'critical',
                        'issue' => 'Executable Uploads Allowed',
                        'description' => "{$executableFiles} executable files in upload directory",
                        'recommendation' => 'Restrict file upload types and permissions'
                    ];
                }
            }
        }
        
        // Check for exposed configuration files
        $configFiles = ['config/app.php', 'config/database.php', 'config/mail.php'];
        $exposedFiles = [];
        
        foreach ($configFiles as $file) {
            if (file_exists(public_path($file))) {
                $exposedFiles[] = $file;
            }
        }
        
        if (!empty($exposedFiles)) {
            $findings[] = [
                'type' => 'critical',
                'issue' => 'Exposed Configuration Files',
                'description' => 'Configuration files accessible via web: ' . implode(', ', $exposedFiles),
                'recommendation' => 'Move configuration files outside web root'
            ];
        }
        
        $this->securityLog['findings']['file_security'] = $findings;
    }
    
    /**
     * Check network security
     */
    private function checkNetworkSecurity()
    {
        $findings = [];
        
        // Check for HTTPS enforcement
        if (!app()->isProduction() && !isset($_SERVER['HTTPS'])) {
            $findings[] = [
                'type' => 'warning',
                'issue' => 'HTTPS Not Enforced',
                'description' => 'HTTPS not enforced in production environment',
                'recommendation' => 'Configure HTTPS and force secure connections'
            ];
        }
        
        // Check for proper CORS configuration
        $corsOrigin = config('cors.allowed_origins');
        if (in_array('*', $corsOrigin)) {
            $findings[] = [
                'type' => 'warning',
                'issue' => 'Permissive CORS Policy',
                'description' => 'CORS allows all origins (*)',
                'recommendation' => 'Restrict CORS to specific trusted origins'
            ];
        }
        
        $this->securityLog['findings']['network'] = $findings;
    }
    
    /**
     * Check application security
     */
    private function checkApplicationSecurity()
    {
        $findings = [];
        
        // Check for debug mode in production
        if (config('app.debug') && app()->isProduction()) {
            $findings[] = [
                'type' => 'critical',
                'issue' => 'Debug Mode Enabled',
                'description' => 'Application debug mode enabled in production',
                'recommendation' => 'Disable debug mode in production environment'
            ];
        }
        
        // Check for exposed Laravel version
        $response = $this->makeTestRequest('/');
        if (strpos($response, 'Laravel') !== false) {
            $findings[] = [
                'type' => 'warning',
                'issue' => 'Laravel Version Exposed',
                'description' => 'Laravel framework information visible in HTTP responses',
                'recommendation' => 'Remove identifying information from responses'
            ];
        }
        
        // Check for security headers
        $headers = get_headers(url('/'), 1);
        $securityHeaders = [
            'X-Content-Type-Options',
            'X-Frame-Options', 
            'X-XSS-Protection',
            'Strict-Transport-Security'
        ];
        
        $missingHeaders = array_diff($securityHeaders, array_keys($headers));
        if (!empty($missingHeaders)) {
            $findings[] = [
                'type' => 'warning',
                'issue' => 'Missing Security Headers',
                'description' => 'Missing security headers: ' . implode(', ', $missingHeaders),
                'recommendation' => 'Configure security headers in web server or middleware'
            ];
        }
        
        $this->securityLog['findings']['application'] = $findings;
    }
    
    /**
     * Check session security
     */
    private function checkSessionSecurity()
    {
        $findings = [];
        
        // Check session configuration
        $sessionDriver = config('session.driver');
        if ($sessionDriver === 'file') {
            $sessionPath = config('session.files');
            if (!is_writable($sessionPath)) {
                $findings[] = [
                    'type' => 'critical',
                    'issue' => 'Session Storage Issues',
                    'description' => 'Session storage directory not writable',
                    'recommendation' => 'Fix session storage permissions or use database sessions'
                ];
            }
        }
        
        // Check session timeout
        $sessionLifetime = config('session.lifetime');
        if ($sessionLifetime > 240) {
            $findings[] = [
                'type' => 'warning',
                'issue' => 'Long Session Lifetime',
                'description' => "Session lifetime set to {$sessionLifetime} minutes",
                'recommendation' => 'Reduce session lifetime for better security'
            ];
        }
        
        $this->securityLog['findings']['session'] = $findings;
    }
    
    /**
     * Check encryption security
     */
    private function checkEncryptionSecurity()
    {
        $findings = [];
        
        // Check app key
        $appKey = config('app.key');
        if (!$appKey || $appKey === 'base64:your-app-key-here') {
            $findings[] = [
                'type' => 'critical',
                'issue' => 'Missing Application Key',
                'description' => 'Application encryption key not properly configured',
                'recommendation' => 'Generate and configure proper application key'
            ];
        }
        
        // Check for weak encryption
        $cipher = config('app.cipher');
        if ($cipher !== 'AES-256-CBC') {
            $findings[] = [
                'type' => 'warning',
                'issue' => 'Weak Encryption Algorithm',
                'description' => "Using {$cipher} instead of AES-256-CBC",
                'recommendation' => 'Use AES-256-CBC for application encryption'
            ];
        }
        
        $this->securityLog['findings']['encryption'] = $findings;
    }
    
    /**
     * Check logging security
     */
    private function checkLoggingSecurity()
    {
        $findings = [];
        
        // Check log level
        $logLevel = config('logging.default');
        if ($logLevel === 'debug' && app()->isProduction()) {
            $findings[] = [
                'type' => 'warning',
                'issue' => 'Verbose Logging in Production',
                'description' => 'Debug level logging enabled in production',
                'recommendation' => 'Use warning or error level logging in production'
            ];
        }
        
        // Check log file permissions
        $logPath = storage_path('logs');
        if (is_dir($logPath)) {
            $logFiles = glob($logPath . '/*.log');
            foreach ($logFiles as $logFile) {
                if (fileperms($logFile) & 0x0004) { // World readable
                    $findings[] = [
                        'type' => 'warning',
                        'issue' => 'Log File Permissions',
                        'description' => 'Log file readable by world: ' . basename($logFile),
                        'recommendation' => 'Restrict log file permissions to 640 or 600'
                    ];
                }
            }
        }
        
        $this->securityLog['findings']['logging'] = $findings;
    }
    
    /**
     * Generate security report
     */
    private function generateSecurityReport()
    {
        $report = [
            'summary' => $this->generateSecuritySummary(),
            'critical_findings' => $this->countFindingsByType('critical'),
            'warning_findings' => $this->countFindingsByType('warning'),
            'total_findings' => $this->countTotalFindings(),
            'security_score' => $this->calculateSecurityScore()
        ];
        
        $this->securityLog['report'] = $report;
        $this->logSecurityFindings();
    }
    
    /**
     * Count findings by type
     */
    private function countFindingsByType($type)
    {
        $count = 0;
        foreach ($this->securityLog['findings'] as $category => $findings) {
            foreach ($findings as $finding) {
                if ($finding['type'] === $type) {
                    $count++;
                }
            }
        }
        return $count;
    }
    
    /**
     * Count total findings
     */
    private function countTotalFindings()
    {
        $count = 0;
        foreach ($this->securityLog['findings'] as $category => $findings) {
            $count += count($findings);
        }
        return $count;
    }
    
    /**
     * Calculate security score
     */
    private function calculateSecurityScore()
    {
        $total = $this->countTotalFindings();
        $critical = $this->countFindingsByType('critical');
        $warning = $this->countFindingsByType('warning');
        
        // Score calculation (100 = perfect, 0 = worst)
        $baseScore = 100;
        $criticalPenalty = $critical * 20;
        $warningPenalty = $warning * 5;
        
        $score = max(0, $baseScore - $criticalPenalty - $warningPenalty);
        
        return [
            'score' => $score,
            'grade' => $score >= 90 ? 'A' : ($score >= 70 ? 'B' : ($score >= 50 ? 'C' : 'F')),
            'interpretation' => $this->getScoreInterpretation($score)
        ];
    }
    
    /**
     * Generate security summary
     */
    private function generateSecuritySummary()
    {
        $total = $this->countTotalFindings();
        $critical = $this->countFindingsByType('critical');
        $warning = $this->countFindingsByType('warning');
        
        $summary = "Security audit completed. ";
        $summary .= "Total findings: {$total} ({$critical} critical, {$warning} warning). ";
        
        if ($critical > 0) {
            $summary .= "Immediate action required for {$critical} critical issues. ";
        }
        
        if ($warning > 0) {
            $summary .= "Recommend review of {$warning} warning issues. ";
        }
        
        $summary .= "Security score: " . $this->securityLog['report']['security_score']['grade'];
        
        return $summary;
    }
    
    /**
     * Get score interpretation
     */
    private function getScoreInterpretation($score)
    {
        if ($score >= 90) {
            return 'Excellent - System has strong security measures';
        } elseif ($score >= 70) {
            return 'Good - System has good security but could be improved';
        } elseif ($score >= 50) {
            return 'Fair - System has moderate security issues that need attention';
        } else {
            return 'Poor - System has significant security vulnerabilities requiring immediate attention';
        }
    }
    
    /**
     * Log security findings
     */
    private function logSecurityFindings()
    {
        Log::channel('security')->info('Security Audit Completed', [
            'timestamp' => $this->securityLog['audit_timestamp'],
            'findings_count' => $this->countTotalFindings(),
            'critical_count' => $this->countFindingsByType('critical'),
            'security_score' => $this->securityLog['report']['security_score']['score']
        ]);
    }
    
    /**
     * Make test request
     */
    private function makeTestRequest($url)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, url($url));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            return $response ?: '';
        } catch (\Exception $e) {
            return '';
        }
    }
}