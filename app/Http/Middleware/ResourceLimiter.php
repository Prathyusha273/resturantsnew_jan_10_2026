<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResourceLimiter
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Set resource limits for shared hosting
        $this->setResourceLimits();

        // Check memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();

        if ($memoryUsage > ($memoryLimit * 0.8)) { // 80% of limit
            Log::warning('High memory usage detected', [
                'usage' => $memoryUsage,
                'limit' => $memoryLimit,
                'url' => $request->url()
            ]);

            // Force garbage collection
            gc_collect_cycles();
        }

        // Check execution time
        $maxExecutionTime = 30; // 30 seconds max
        set_time_limit($maxExecutionTime);

        return $next($request);
    }

    /**
     * Set resource limits for shared hosting
     */
    private function setResourceLimits()
    {
        // Set memory limit based on available memory
        $memoryLimit = $this->getMemoryLimit();
        if ($memoryLimit > 0) {
            $safeLimit = min($memoryLimit * 0.7, 256 * 1024 * 1024); // 70% of limit or 256MB max
//            ini_set('memory_limit', $safeLimit);
        }

        // Set execution time limit
        ini_set('max_execution_time', 30);

        // Set other limits
        ini_set('max_input_time', 30);
        ini_set('default_socket_timeout', 30);
    }

    /**
     * Get memory limit in bytes
     */
    private function getMemoryLimit()
    {
        $limit = ini_get('memory_limit');
        if ($limit == -1) {
            return 0; // No limit
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }
}
