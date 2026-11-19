<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    public function index(Request $request): View
    {
        $roles = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', (int) $request->user()->id)
            ->where('ur.status', 'active')
            ->pluck('r.code')
            ->unique();

        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'IT_ADMIN'])->isNotEmpty(), 403);

        $dbStarted = hrtime(true);
        DB::select('SELECT 1 AS health_check');
        $databaseMs = round((hrtime(true) - $dbStarted) / 1_000_000, 2);

        $logPath = storage_path('logs/laravel.log');
        $slowRequestCount = null;
        $logReadable = is_file($logPath) && is_readable($logPath);
        if ($logReadable) {
            $log = @file_get_contents($logPath);
            if (is_string($log)) {
                $slowRequestCount = substr_count($log, 'Slow Tagore request');
            }
        }

        return view('tagore.performance.index', [
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'environment' => app()->environment(),
            'debugEnabled' => (bool) config('app.debug'),
            'databaseMs' => $databaseMs,
            'memoryMb' => round(memory_get_peak_usage(true) / 1048576, 1),
            'memoryLimit' => (string) ini_get('memory_limit'),
            'slowRequestCount' => $slowRequestCount,
            'slowRequestLogging' => (bool) config('tagore.performance.log_slow_requests'),
            'slowRequestThreshold' => (int) config('tagore.performance.slow_request_ms'),
            'checkedAt' => now(),
        ]);
    }
}
