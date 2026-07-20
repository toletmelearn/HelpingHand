<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class InstallationChecker
{
    /**
     * Run all system environment verification checks.
     */
    public function verifyAll(): array
    {
        return [
            'php' => $this->checkPhp(),
            'laravel' => $this->checkLaravel(),
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'cron' => $this->checkCron(),
            'storage' => $this->checkStoragePermissions(),
            'ssl' => $this->checkSsl(),
            'smtp' => $this->checkSmtp(),
            'stripe' => $this->checkStripe(),
            'sms_whatsapp' => $this->checkSmsWhatsApp(),
        ];
    }

    protected function checkPhp(): array
    {
        $requiredExtensions = ['pdo', 'openssl', 'mbstring', 'zip', 'gd', 'xml', 'bcmath', 'curl'];
        $missing = [];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }

        $status = count($missing) === 0 ? 'success' : 'error';
        $msg = $status === 'success' 
            ? "PHP version " . PHP_VERSION . " matches constraints. All required extensions loaded."
            : "Missing required PHP extensions: " . implode(', ', $missing);

        return [
            'name' => 'PHP Environment',
            'status' => $status,
            'message' => $msg,
            'meta' => [
                'Version' => PHP_VERSION,
                'OS' => PHP_OS,
                'Extensions Loaded' => count(get_loaded_extensions())
            ]
        ];
    }

    protected function checkLaravel(): array
    {
        return [
            'name' => 'Laravel Framework',
            'status' => 'success',
            'message' => "Running Laravel version " . app()->version() . " in " . app()->environment() . " mode.",
            'meta' => [
                'Version' => app()->version(),
                'Environment' => app()->environment(),
                'Debug Mode' => config('app.debug') ? 'On' : 'Off',
                'Config Cached' => app()->configurationIsCached() ? 'Yes' : 'No'
            ]
        ];
    }

    protected function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $duration = round((microtime(true) - $start) * 1000, 2);
            $driver = DB::connection()->getDriverName();

            $version = 'Unknown';
            if ($driver === 'sqlite') {
                $version = DB::selectOne("select sqlite_version() as version")->version;
            } else {
                $row = DB::selectOne("SELECT VERSION() as version");
                if ($row) {
                    $version = $row->version;
                }
            }

            return [
                'name' => 'Database (MySQL/MariaDB)',
                'status' => 'success',
                'message' => "Connected successfully. Database server: " . $version,
                'meta' => [
                    'Driver' => $driver,
                    'Ping Latency' => "{$duration}ms",
                    'Database Name' => DB::connection()->getDatabaseName()
                ]
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'Database (MySQL/MariaDB)',
                'status' => 'error',
                'message' => "Database connection failed: " . $e->getMessage(),
                'meta' => []
            ];
        }
    }

    protected function checkRedis(): array
    {
        // Check if Redis cache is used
        $driver = config('cache.default');
        if ($driver !== 'redis' && config('queue.default') !== 'redis') {
            return [
                'name' => 'Redis Cache Store',
                'status' => 'warning',
                'message' => "Redis is not configured as default Cache or Queue store. Currently using '{$driver}' driver.",
                'meta' => [
                    'Cache Driver' => $driver,
                    'Queue Connection' => config('queue.default')
                ]
            ];
        }

        try {
            $redis = app('redis');
            $redis->ping();
            return [
                'name' => 'Redis Cache Store',
                'status' => 'success',
                'message' => "Redis connection is active and responding.",
                'meta' => []
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'Redis Cache Store',
                'status' => 'error',
                'message' => "Failed to ping Redis server: " . $e->getMessage(),
                'meta' => []
            ];
        }
    }

    protected function checkQueue(): array
    {
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
            $pulse = Cache::get('queue_worker_heartbeat');
            
            $workerActive = $pulse && (time() - $pulse < 120);

            return [
                'name' => 'Queue Runner',
                'status' => $workerActive ? 'success' : 'warning',
                'message' => $workerActive 
                    ? "Queue worker active. Backlogs check: {$pending} pending, {$failed} failed." 
                    : "No active queue worker detected. Please ensure queue daemon 'php artisan queue:work' is running.",
                'meta' => [
                    'Connection' => config('queue.default'),
                    'Pending Backlogs' => $pending,
                    'Failed Backlogs' => $failed,
                    'Worker Status' => $workerActive ? 'Active' : 'Stale/Offline'
                ]
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'Queue Runner',
                'status' => 'error',
                'message' => "Queue database diagnostics failed: " . $e->getMessage(),
                'meta' => []
            ];
        }
    }

    protected function checkCron(): array
    {
        $pulse = Cache::get('scheduler_heartbeat');
        if (!$pulse) {
            return [
                'name' => 'Cron Task Scheduler',
                'status' => 'warning',
                'message' => "No cron heartbeat detected. Set up '* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1' in crontab.",
                'meta' => []
            ];
        }

        $diff = round((time() - $pulse) / 60, 1);
        $status = $diff < 15 ? 'success' : 'error';
        $msg = $status === 'success'
            ? "Task scheduler heartbeat active (last run was {$diff}m ago)."
            : "Task scheduler heartbeat is stale. Last run: " . date('Y-m-d H:i:s', $pulse) . " ({$diff}m ago).";

        return [
            'name' => 'Cron Task Scheduler',
            'status' => $status,
            'message' => $msg,
            'meta' => [
                'Last Heartbeat' => date('Y-m-d H:i:s', $pulse),
                'Interval Diff' => "{$diff} minutes"
            ]
        ];
    }

    protected function checkStoragePermissions(): array
    {
        $paths = [
            'Storage Directory' => storage_path(),
            'Framework Cache' => storage_path('framework/cache'),
            'Framework Views' => storage_path('framework/views'),
            'Bootstrap Cache' => base_path('bootstrap/cache')
        ];
        
        $errors = [];
        foreach ($paths as $name => $path) {
            if (!is_writable($path)) {
                $errors[] = "{$name} is not writable";
            }
        }
        
        if (!empty($errors)) {
            return [
                'name' => 'Storage Permissions',
                'status' => 'error',
                'message' => "Folder write errors found: " . implode(', ', $errors),
                'meta' => []
            ];
        }

        return [
            'name' => 'Storage Permissions',
            'status' => 'success',
            'message' => "All framework storage & bootstrap paths are writable.",
            'meta' => []
        ];
    }

    protected function checkSsl(): array
    {
        $appUrl = config('app.url');
        $isSecure = str_starts_with($appUrl, 'https://') || 
                     (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                     (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

        return [
            'name' => 'SSL Certificate Security',
            'status' => $isSecure ? 'success' : 'warning',
            'message' => $isSecure 
                ? "Secure HTTPS is active on url: " . $appUrl
                : "SSL is inactive or APP_URL is configured as HTTP. Move to HTTPS for production.",
            'meta' => [
                'App URL' => $appUrl,
                'Port' => $_SERVER['SERVER_PORT'] ?? 80
            ]
        ];
    }

    protected function checkSmtp(): array
    {
        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');

        if (empty($host) || $host === 'smtp.mailtrap.io') {
            return [
                'name' => 'SMTP Mailer Settings',
                'status' => 'warning',
                'message' => "SMTP mailer uses default sandbox credentials (Mailtrap/Empty). Ensure production mail server is set.",
                'meta' => [
                    'Host' => $host,
                    'Port' => $port,
                ]
            ];
        }

        // Test socket connection
        try {
            $connection = @fsockopen($host, $port, $errno, $errstr, 3.0);
            if ($connection) {
                fclose($connection);
                return [
                    'name' => 'SMTP Mailer Settings',
                    'status' => 'success',
                    'message' => "Successfully connected to SMTP mail server: {$host}:{$port}.",
                    'meta' => [
                        'Host' => $host,
                        'Port' => $port,
                        'Encryption' => config('mail.mailers.smtp.encryption')
                    ]
                ];
            } else {
                return [
                    'name' => 'SMTP Mailer Settings',
                    'status' => 'error',
                    'message' => "Failed to connect to SMTP mail server: [{$errno}] {$errstr}",
                    'meta' => []
                ];
            }
        } catch (\Throwable $e) {
            return [
                'name' => 'SMTP Mailer Settings',
                'status' => 'error',
                'message' => "Socket connection exception: " . $e->getMessage(),
                'meta' => []
            ];
        }
    }

    protected function checkStripe(): array
    {
        $secretKey = config('services.stripe.secret');
        if (empty($secretKey)) {
            return [
                'name' => 'Stripe Gateway Connectivity',
                'status' => 'warning',
                'message' => "Stripe Secret Key is empty. Credit card payments are offline.",
                'meta' => []
            ];
        }

        try {
            // Check secret key validity via quick api test ping
            $client = new \Stripe\StripeClient($secretKey);
            $client->plans->all(['limit' => 1]);

            return [
                'name' => 'Stripe Gateway Connectivity',
                'status' => 'success',
                'message' => "Stripe API connection successfully authenticated (Mode: " . (str_starts_with($secretKey, 'sk_test') ? 'Test/Sandbox' : 'Live') . ").",
                'meta' => [
                    'Mode' => str_starts_with($secretKey, 'sk_test') ? 'Sandbox' : 'Live',
                ]
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'Stripe Gateway Connectivity',
                'status' => 'error',
                'message' => "Stripe API authentication failed: " . $e->getMessage(),
                'meta' => []
            ];
        }
    }

    protected function checkSmsWhatsApp(): array
    {
        $twilioSid = config('services.twilio.sid');
        $whatsappKey = config('services.whatsapp.key');

        $twilioOk = !empty($twilioSid);
        $whatsappOk = !empty($whatsappKey);

        $status = ($twilioOk && $whatsappOk) ? 'success' : 'warning';
        $msg = "SMS settings (Twilio): " . ($twilioOk ? 'Active' : 'Missing') . ". WhatsApp settings: " . ($whatsappOk ? 'Active' : 'Missing') . ".";

        return [
            'name' => 'SMS / WhatsApp Channels',
            'status' => $status,
            'message' => $msg,
            'meta' => [
                'Twilio Active' => $twilioOk ? 'Yes' : 'No',
                'WhatsApp Active' => $whatsappOk ? 'Yes' : 'No',
            ]
        ];
    }
}
