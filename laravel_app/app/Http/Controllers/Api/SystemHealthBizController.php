<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SystemHealthBizController extends Controller
{
    public function status(): JsonResponse
    {
        $ttlSeconds = max(5, (int) env('API_SYSTEM_HEALTH_CACHE_TTL', 20));
        $payload = $this->rememberCache(
            'api:system-health:status',
            fn (): array => $this->collectStatuses(),
            $ttlSeconds
        );

        return response()->json([
            'ok' => true,
            'statuses' => $payload['statuses'],
            'botStatus' => $payload['bot_status'],
            'botUrl' => $payload['bot_url'],
            'failed' => $payload['failed'],
        ]);
    }

    public function runChecks(): JsonResponse
    {
        $payload = $this->collectStatuses();

        return response()->json([
            'ok' => true,
            'failed' => $payload['failed'],
            'message' => $payload['failed'] > 0
                ? 'Health check completed with ' . $payload['failed'] . ' errors.'
                : 'Health check completed successfully.',
        ]);
    }

    private function collectStatuses(): array
    {
        $services = config('service_registry.services', []);
        $statuses = [];
        $failed = 0;

        foreach ($services as $name => $service) {
            try {
                DB::connection((string) $service['connection'])->select('SELECT 1');
                $statuses[$name] = ['status' => 'ok', 'detail' => (string) $service['connection']];
            } catch (\Throwable $exception) {
                $statuses[$name] = ['status' => 'error', 'detail' => $exception->getMessage()];
                $failed++;
            }
        }

        $botUrl = (string) (env('BOT_SERVICE_URL') ?: 'http://127.0.0.1:8001/health');
        try {
            $response = Http::timeout(2)->get($botUrl);
            $botStatus = ['status' => $response->successful() ? 'ok' : 'error', 'detail' => 'HTTP ' . $response->status()];
            if (!$response->successful()) {
                $failed++;
            }
        } catch (\Throwable $exception) {
            $botStatus = ['status' => 'error', 'detail' => $exception->getMessage()];
            $failed++;
        }

        return [
            'statuses' => $statuses,
            'bot_status' => $botStatus,
            'bot_url' => $botUrl,
            'failed' => $failed,
        ];
    }

    private function rememberCache(string $key, callable $resolver, int $ttlSeconds): array
    {
        try {
            return Cache::remember($key, $ttlSeconds, $resolver);
        } catch (Throwable $exception) {
            Log::warning('System health cache remember failed', ['key' => $key, 'error' => $exception->getMessage()]);
        }

        try {
            return Cache::store((string) env('API_LIST_CACHE_FALLBACK_STORE', 'file'))->remember($key, $ttlSeconds, $resolver);
        } catch (Throwable $exception) {
            Log::warning('System health fallback cache remember failed', ['key' => $key, 'error' => $exception->getMessage()]);
            return $resolver();
        }
    }
}
