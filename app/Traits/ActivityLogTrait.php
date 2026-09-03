<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait ActivityLogTrait
{
    /**
     * Log activity to database and Laravel log files.
     *
     * @param string $action       The action performed (e.g., CREATE, UPDATE, DELETE, LOGIN)
     * @param string $module       The module/domain target (e.g., User, Lead, Country)
     * @param string $description  Human-readable description of the activity
     * @param array|null $payload  Structured metadata or parameters (e.g., input payload, before/after differences)
     * @param string $level        The severity level of the activity (info, warning, error, critical)
     * @return void
     */
    public function logActivity(
        string $action,
        string $module,
        string $description,
        ?array $payload = null,
        string $level = 'info'
    ): void {
        $userId = null;
        $request = request();
        $ipAddress = null;
        $userAgent = null;
        $url = null;
        $method = null;

        try {
            // Resolve basic request info if inside an HTTP context
            if ($request) {
                $ipAddress = $request->ip();
                $userAgent = $request->userAgent();
                $url = $request->fullUrl();
                $method = $request->method();
            }

            // Resolve user ID dynamically from multiple guards
            $userId = $this->resolveAuthenticatedUserId();

            // Sanitize sensitive keys from payload if provided
            $sanitizedPayload = $payload ? $this->sanitizeLogPayload($payload) : null;

            // Attempt to log to Database
            ActivityLog::create([
                'user_id' => $userId,
                'action' => strtoupper($action),
                'module' => $module,
                'description' => $description,
                'payload' => $sanitizedPayload,
                'level' => strtolower($level),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'url' => $url,
                'method' => $method,
            ]);

            // Structured logging context for files
            $logContext = [
                'user_id' => $userId ?? 'Guest/System',
                'action' => strtoupper($action),
                'ip' => $ipAddress,
                'url' => $url,
                'method' => $method,
                'payload' => $sanitizedPayload,
            ];

            $logMessage = "[{$module}] {$action}: {$description}";
            $this->writeToLaravelLog($logMessage, $level, $logContext);

        } catch (\Throwable $th) {
            // Gracefully handle DB failure (e.g., db down, table doesn't exist yet)
            // Ensure this failure does NOT crash the application response
            Log::error("Failed to log activity to database: " . $th->getMessage(), [
                'exception' => $th,
                'original_log' => [
                    'action' => $action,
                    'module' => $module,
                    'description' => $description,
                    'payload' => $payload ?? null,
                    'level' => $level,
                ]
            ]);

            // Fallback: log the original activity straight to files with a suffix indicating DB logging failed
            $fallbackMessage = "[{$module}] {$action}: {$description} (DB Logging Failed)";
            $fallbackContext = [
                'user_id' => $userId ?? 'Guest/System',
                'ip' => $ipAddress,
                'url' => $url,
                'method' => $method,
                'payload' => $payload ? $this->sanitizeLogPayload($payload) : null,
            ];
            $this->writeToLaravelLog($fallbackMessage, $level, $fallbackContext);
        }
    }

    /**
     * Resolve authenticated user ID from various application guards.
     *
     * @return int|string|null
     */
    protected function resolveAuthenticatedUserId()
    {
        // Check standard API/JWT/Sanctum or Web guards in order of popularity
        $guards = ['api', 'sanctum', 'web'];

        foreach ($guards as $guard) {
            try {
                if (Auth::guard($guard)->check()) {
                    return Auth::guard($guard)->id();
                }
            } catch (\Throwable $e) {
                // Guard not defined or has issues - continue to next guard
                continue;
            }
        }

        // Final fallback to default auth manager if checks above pass or fail
        try {
            if (Auth::check()) {
                return Auth::id();
            }
        } catch (\Throwable $e) {
            // Do nothing
        }

        return null;
    }

    /**
     * Write log entry to files using correct severity level.
     *
     * @param string $message
     * @param string $level
     * @param array $context
     * @return void
     */
    protected function writeToLaravelLog(string $message, string $level, array $context): void
    {
        switch (strtolower($level)) {
            case 'warning':
                Log::warning($message, $context);
                break;
            case 'error':
                Log::error($message, $context);
                break;
            case 'critical':
                Log::critical($message, $context);
                break;
            case 'info':
            default:
                Log::info($message, $context);
                break;
        }
    }

    /**
     * Recursively sanitize sensitive parameters from payload array.
     *
     * @param array $payload
     * @return array
     */
    protected function sanitizeLogPayload(array $payload): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'token',
            'token_secret',
            'credit_card',
            'cvv',
            'authorization',
            'api_key',
            'secret'
        ];

        array_walk_recursive($payload, function (&$value, $key) use ($sensitiveKeys) {
            if (in_array(strtolower($key), $sensitiveKeys, true)) {
                $value = '[REDACTED]';
            }
        });

        return $payload;
    }
}
