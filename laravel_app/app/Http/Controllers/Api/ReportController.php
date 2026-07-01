<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Report\StoreReportRequest;
use App\Http\Requests\Api\Report\UpdateReportRequest;
use App\Http\Resources\Api\Report\ReportResource;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReportController extends Controller
{
    private function conn(): string
    {
        return (string) config('service_registry.services.reporting.connection', config('database.default'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ─── REPORTS ────────────────────────────────────────────────────────────
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/reports
     */
    public function index(Request $request): JsonResponse
    {
        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $payload = $this->rememberCache(
            'api:reports:index:v' . $this->listCacheVersion() . ':' . md5(json_encode(compact('filters', 'perPage', 'page'))),
            function () use ($filters, $perPage, $page): array {
            $query = DB::connection($this->conn())
                ->table('baocao')
                ->when(!empty($filters['q']), function (Builder $q) use ($filters) {
                    $kw = trim((string) $filters['q']);
                    $q->where(fn (Builder $i) => $i->where('TenBaoCao', 'like', "%{$kw}%")->orWhere('NguoiTao', 'like', "%{$kw}%"));
                })
                ->when(!empty($filters['type']), fn (Builder $q) => $q->where('LoaiBaoCao', $filters['type']))
                ->orderByDesc('MaBC');

            $total = (clone $query)->count();
            $rows = $query->forPage($page, $perPage)->get();
            $data = ReportResource::collection($rows)->resolve();

            return [
                'ok' => true,
                'data' => $data,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($total / $perPage),
                ],
            ];
            },
            120
        );

        return response()->json($payload);
    }

    /**
     * GET /api/reports/{id}
     */
    public function show(int $id): JsonResponse
    {
        $item = DB::connection($this->conn())->table('baocao')->where('MaBC', $id)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Báo cáo không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (new ReportResource($item))->resolve()]);
    }

    /**
     * POST /api/reports
     */
    public function store(StoreReportRequest $request): JsonResponse
    {
        $payload = $request->validated();
        
        try {
            $id = (int) DB::connection($this->conn())->table('baocao')->insertGetId($payload, 'MaBC');
            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'id' => $id, 'message' => 'Tạo báo cáo thành công.'], 201);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * PUT /api/reports/{id}
     */
    public function update(UpdateReportRequest $request, int $id): JsonResponse
    {
        $payload = $request->validated();
        
        try {
            $affected = DB::connection($this->conn())
                ->table('baocao')
                ->where('MaBC', $id)
                ->update($payload);

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Báo cáo không tồn tại.'], 404);
            }

            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'message' => 'Cập nhật báo cáo thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /api/reports/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $affected = DB::connection($this->conn())
                ->table('baocao')
                ->where('MaBC', $id)
                ->delete();

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Báo cáo không tồn tại.'], 404);
            }

            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'message' => 'Xóa báo cáo thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/reports/export
     */
    public function export(Request $request): JsonResponse
    {
        $filters = (array) $request->input('filters', []);
        $payload = $this->rememberCache(
            'api:reports:export:v' . $this->listCacheVersion() . ':' . md5(json_encode($filters)),
            function () use ($filters): array {
            $rows = DB::connection($this->conn())
                ->table('baocao')
                ->when(!empty($filters['q']), function (Builder $q) use ($filters) {
                    $kw = trim((string) $filters['q']);
                    $q->where(fn (Builder $i) => $i->where('TenBaoCao', 'like', "%{$kw}%")->orWhere('NguoiTao', 'like', "%{$kw}%"));
                })
                ->when(!empty($filters['type']), fn (Builder $q) => $q->where('LoaiBaoCao', $filters['type']))
                ->orderByDesc('MaBC')
                ->get();

            $data = ReportResource::collection($rows)->resolve();

            return ['ok' => true, 'data' => $data];
            },
            120
        );

        return response()->json($payload);
    }

    private function bumpListCacheVersion(): void
    {
        $this->bumpCounter('api:reports:list:version');
    }

    private function listCacheVersion(): int
    {
        try {
            return (int) Cache::get('api:reports:list:version', 1);
        } catch (Throwable $exception) {
            Log::warning('Report cache version read failed', ['error' => $exception->getMessage()]);
            return (int) Cache::store((string) env('API_LIST_CACHE_FALLBACK_STORE', 'file'))->get('api:reports:list:version', 1);
        }
    }

    private function bumpCounter(string $key): void
    {
        try {
            if (!Cache::has($key)) {
                Cache::forever($key, 1);
            }

            Cache::increment($key);
            return;
        } catch (Throwable $exception) {
            Log::warning('Report cache bump failed', ['key' => $key, 'error' => $exception->getMessage()]);
        }

        $fallback = Cache::store((string) env('API_LIST_CACHE_FALLBACK_STORE', 'file'));
        if (!$fallback->has($key)) {
            $fallback->forever($key, 1);
        }

        $fallback->increment($key);
    }

    private function rememberCache(string $key, callable $resolver, int $ttlSeconds): array
    {
        try {
            return Cache::remember($key, $ttlSeconds, $resolver);
        } catch (Throwable $exception) {
            Log::warning('Report cache remember failed', ['key' => $key, 'error' => $exception->getMessage()]);
        }

        try {
            return Cache::store((string) env('API_LIST_CACHE_FALLBACK_STORE', 'file'))->remember($key, $ttlSeconds, $resolver);
        } catch (Throwable $exception) {
            Log::warning('Report fallback cache remember failed', ['key' => $key, 'error' => $exception->getMessage()]);
            return $resolver();
        }
    }
}
