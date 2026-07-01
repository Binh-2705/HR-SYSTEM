<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SearchBizController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));

        if ($keyword === '') {
            return response()->json(['ok' => true, 'results' => []]);
        }

        $results = $this->rememberCache('api:search:' . md5($keyword), function () use ($keyword): array {
            $like     = '%' . $keyword . '%';
            $hr       = (string) config('service_registry.services.hr.connection', config('database.default'));
            $recruit  = (string) config('service_registry.services.recruitment.connection', config('database.default'));
            $report   = (string) config('service_registry.services.reporting.connection', config('database.default'));

            return [
                'employees'   => DB::connection($hr)->table('nhanvien')->where('HoTen', 'like', $like)->limit(10)->get()->map(fn ($r) => (array) $r)->all(),
                'departments' => DB::connection($hr)->table('phongban')->where('TenPB', 'like', $like)->limit(10)->get()->map(fn ($r) => (array) $r)->all(),
                'positions'   => DB::connection($hr)->table('chucvu')->where('TenCV', 'like', $like)->limit(10)->get()->map(fn ($r) => (array) $r)->all(),
                'campaigns'   => DB::connection($recruit)->table('dottuyendung')->where('TenDotTuyenDung', 'like', $like)->limit(10)->get()->map(fn ($r) => (array) $r)->all(),
                'reports'     => DB::connection($report)->table('baocao')->where('TenBaoCao', 'like', $like)->limit(10)->get()->map(fn ($r) => (array) $r)->all(),
            ];
        }, 120);

        return response()->json(['ok' => true, 'keyword' => $keyword, 'results' => $results]);
    }

    private function rememberCache(string $key, callable $resolver, int $ttlSeconds): array
    {
        try {
            return Cache::remember($key, $ttlSeconds, $resolver);
        } catch (Throwable $exception) {
            Log::warning('Search cache remember failed', ['key' => $key, 'error' => $exception->getMessage()]);
        }

        try {
            return Cache::store((string) env('API_LIST_CACHE_FALLBACK_STORE', 'file'))->remember($key, $ttlSeconds, $resolver);
        } catch (Throwable $exception) {
            Log::warning('Search fallback cache remember failed', ['key' => $key, 'error' => $exception->getMessage()]);
            return $resolver();
        }
    }
}
