<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SystemHealthCheckCommand extends Command
{
    protected $signature = 'app:health-check';

    protected $description = 'Run lightweight health checks for database and cache connectivity';

    public function handle(): int
    {
        $result = [
            'database' => 'down',
            'cache' => 'down',
            'checked_at' => now()->toDateTimeString(),
        ];

        try {
            DB::select('SELECT 1');
            $result['database'] = 'up';
        } catch (\Throwable $e) {
            Log::channel('security')->warning('Health check DB failed', ['error' => $e->getMessage()]);
        }

        try {
            $probeKey = 'healthcheck:probe';
            Cache::put($probeKey, 'ok', 60);
            $result['cache'] = Cache::get($probeKey) === 'ok' ? 'up' : 'down';
        } catch (\Throwable $e) {
            Log::channel('security')->warning('Health check cache failed', ['error' => $e->getMessage()]);
        }

        Log::channel('audit')->info('System health check executed', $result);
        $this->info(sprintf('Health check => DB: %s, CACHE: %s', $result['database'], $result['cache']));

        return ($result['database'] === 'up' && $result['cache'] === 'up') ? self::SUCCESS : self::FAILURE;
    }
}
