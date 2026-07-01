<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Recruitment\StoreCampaignRequest;
use App\Http\Requests\Api\Recruitment\StoreCandidateRequest;
use App\Http\Requests\Api\Recruitment\UpdateCampaignRequest;
use App\Http\Requests\Api\Recruitment\UpdateCandidateRequest;
use App\Http\Resources\Api\Recruitment\CampaignResource;
use App\Http\Resources\Api\Recruitment\CandidateResource;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentCampaign;
use App\Models\RecruitmentCandidate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecruitmentController extends Controller
{
    private function conn(): string
    {
        return (string) config('service_registry.services.recruitment.connection', config('database.default'));
    }

    private function hrConn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ─── CAMPAIGNS ──────────────────────────────────────────────────────────
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/recruitment/campaigns
     */
    public function indexCampaigns(Request $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'viewAny', RecruitmentCampaign::class);

        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $payload = $this->rememberListCache(
            $this->listCacheKey('campaigns', compact('filters', 'perPage', 'page')),
            function () use ($filters, $perPage, $page): array {
                $query = RecruitmentCampaign::query()
                    ->withApplicationCount()
                    ->applyFilters($filters)
                    ->sortDefault();

                $total = DB::connection($this->conn())->table('dottuyendung')->count();
                $rows = $query->forPage($page, $perPage)->get();
                $data = CampaignResource::collection($rows)->resolve();

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
            }
        );

        return response()->json($payload);
    }

    /**
     * GET /api/recruitment/campaigns/{id}
     */
    public function showCampaign(int $id): JsonResponse
    {
        $item = DB::connection($this->conn())->table('dottuyendung')->where('MaDTD', $id)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Đợt tuyển dụng không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (new CampaignResource($item))->resolve()]);
    }

    /**
     * POST /api/recruitment/campaigns
     */
    public function storeCampaign(StoreCampaignRequest $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'create', RecruitmentCampaign::class);

        $payload = $request->validated();
        
        try {
            $id = (int) DB::connection($this->conn())->table('dottuyendung')->insertGetId($payload, 'MaDTD');
            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'id' => $id, 'message' => 'Tạo đợt tuyển dụng thành công.'], 201);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * PUT /api/recruitment/campaigns/{id}
     */
    public function updateCampaign(UpdateCampaignRequest $request, int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'update', RecruitmentCampaign::class);

        $payload = $request->validated();
        
        try {
            $affected = DB::connection($this->conn())
                ->table('dottuyendung')
                ->where('MaDTD', $id)
                ->update($payload);

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Đợt tuyển dụng không tồn tại.'], 404);
            }

            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'message' => 'Cập nhật đợt tuyển dụng thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /api/recruitment/campaigns/{id}
     */
    public function destroyCampaign(Request $request, int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'delete', RecruitmentCampaign::class);

        try {
            $affected = DB::connection($this->conn())
                ->table('dottuyendung')
                ->where('MaDTD', $id)
                ->delete();

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Đợt tuyển dụng không tồn tại.'], 404);
            }

            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'message' => 'Xóa đợt tuyển dụng thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ─── CANDIDATES ─────────────────────────────────────────────────────────
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/recruitment/candidates
     */
    public function indexCandidates(Request $request): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'them_ung_vien');

        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $payload = $this->rememberListCache(
            $this->listCacheKey('candidates', compact('filters', 'perPage', 'page')),
            function () use ($filters, $perPage, $page): array {
                $query = RecruitmentCandidate::query()
                    ->applyFilters($filters)
                    ->sortDefault();

                $total = (clone $query)->count();
                $rows = $query->forPage($page, $perPage)->get();
                $data = CandidateResource::collection($rows)->resolve();

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
            }
        );

        return response()->json($payload);
    }

    /**
     * GET /api/recruitment/candidates/{id}
     */
    public function showCandidate(int $id): JsonResponse
    {
        $item = DB::connection($this->conn())->table('ungvien')->where('MaUV', $id)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Ứng viên không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (new CandidateResource($item))->resolve()]);
    }

    /**
     * POST /api/recruitment/candidates
     */
    public function storeCandidate(StoreCandidateRequest $request): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'them_ung_vien');

        $payload = $request->validated();
        
        try {
            $id = (int) DB::connection($this->conn())->table('ungvien')->insertGetId($payload, 'MaUV');
            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'id' => $id, 'message' => 'Tạo ứng viên thành công.'], 201);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * PUT /api/recruitment/candidates/{id}
     */
    public function updateCandidate(UpdateCandidateRequest $request, int $id): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'them_ung_vien');

        $payload = $request->validated();
        
        try {
            $affected = DB::connection($this->conn())
                ->table('ungvien')
                ->where('MaUV', $id)
                ->update($payload);

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Ứng viên không tồn tại.'], 404);
            }

            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'message' => 'Cập nhật ứng viên thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /api/recruitment/candidates/{id}
     */
    public function destroyCandidate(Request $request, int $id): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'them_ung_vien');

        try {
            $affected = DB::connection($this->conn())
                ->table('ungvien')
                ->where('MaUV', $id)
                ->delete();

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Ứng viên không tồn tại.'], 404);
            }

            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'message' => 'Xóa ứng viên thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ─── APPLICATIONS ───────────────────────────────────────────────────────
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/recruitment/applications
     */
    public function indexApplications(Request $request): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'capnhat_trangthai');

        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $payload = $this->rememberListCache(
            $this->listCacheKey('applications', compact('filters', 'perPage', 'page')),
            function () use ($filters, $perPage, $page): array {
                $query = RecruitmentApplication::query()
                    ->withCandidateCampaignContext()
                    ->applyFilters($filters)
                    ->sortDefault();

                $total = (clone $query)->count();
                $data  = $query->forPage($page, $perPage)->get()->map(fn ($r) => (array) $r)->all();

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
            }
        );

        return response()->json($payload);
    }

    /**
     * GET /api/recruitment/applications/{id}
     */
    public function showApplication(int $id): JsonResponse
    {
        $item = RecruitmentApplication::query()
            ->withCandidateCampaignContext()
            ->where('hs.MaHS', $id)
            ->first();

        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Hồ sơ ứng tuyển không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (array) $item]);
    }

    /**
     * PUT /api/recruitment/applications/{id}/status
     */
    public function updateApplicationStatus(Request $request, int $id): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'capnhat_trangthai');

        $status = trim((string) $request->input('status', ''));
        
        try {
            $affected = DB::connection($this->conn())
                ->table('hosoungtuyen')
                ->where('MaHS', $id)
                ->update(['TrangThai' => $status]);

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Hồ sơ ứng tuyển không tồn tại.'], 404);
            }

            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'message' => 'Cập nhật trạng thái hồ sơ thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/recruitment/campaign-options
     */
    public function campaignOptions(): JsonResponse
    {
        $options = RecruitmentCampaign::query()
            ->where('TrangThai', 'Mở')
            ->orderBy('TenDotTuyenDung')
            ->get(['MaDTD', 'TenDotTuyenDung', 'ViTriTuyenDung']);

        return response()->json(['ok' => true, 'data' => $options]);
    }

    private function authorizeIfAuthenticated(Request $request, string $ability, mixed $arguments): void
    {
        if ($request->user() !== null) {
            $this->authorize($ability, $arguments);
        }
    }

    private function authorizePermissionIfAuthenticated(Request $request, string $permission): void
    {
        if ($request->user() !== null) {
            Gate::forUser($request->user())->authorize('permission', $permission);
        }
    }

    private function listCacheTtlSeconds(): int
    {
        return max(1, (int) env('API_LIST_CACHE_TTL', 120));
    }

    private function listCacheKey(string $segment, array $payload): string
    {
        return 'api:recruitment:' . $segment . ':v' . $this->listCacheVersion() . ':' . md5(json_encode($payload));
    }

    private function listCacheVersion(): int
    {
        try {
            return (int) Cache::get('api:recruitment:list:version', 1);
        } catch (Throwable $e) {
            Log::warning('Primary cache read failed for recruitment list version', ['error' => $e->getMessage()]);
            return (int) Cache::store($this->fallbackCacheStore())->get('api:recruitment:list:version', 1);
        }
    }

    private function bumpListCacheVersion(): void
    {
        try {
            if (!Cache::has('api:recruitment:list:version')) {
                Cache::forever('api:recruitment:list:version', 1);
            }

            Cache::increment('api:recruitment:list:version');
            return;
        } catch (Throwable $e) {
            Log::warning('Primary cache increment failed for recruitment list version', ['error' => $e->getMessage()]);
        }

        $fallback = Cache::store($this->fallbackCacheStore());
        if (!$fallback->has('api:recruitment:list:version')) {
            $fallback->forever('api:recruitment:list:version', 1);
        }

        $fallback->increment('api:recruitment:list:version');
    }

    private function rememberListCache(string $key, callable $resolver): array
    {
        $ttl = $this->listCacheTtlSeconds();

        try {
            return Cache::remember($key, $ttl, $resolver);
        } catch (Throwable $e) {
            Log::warning('Primary cache remember failed for recruitment list', ['error' => $e->getMessage()]);
        }

        try {
            return Cache::store($this->fallbackCacheStore())->remember($key, $ttl, $resolver);
        } catch (Throwable $e) {
            Log::warning('Fallback cache remember failed for recruitment list', ['error' => $e->getMessage()]);
            return $resolver();
        }
    }

    private function fallbackCacheStore(): string
    {
        return (string) env('API_LIST_CACHE_FALLBACK_STORE', 'file');
    }
}
