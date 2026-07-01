<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\StoreCourseRequest;
use App\Http\Requests\Api\Training\StoreParticipantRequest;
use App\Http\Requests\Api\Training\UpdateCourseRequest;
use App\Http\Requests\Api\Training\UpdateParticipantRequest;
use App\Http\Resources\Api\Training\CourseResource;
use App\Http\Resources\Api\Training\ParticipantResource;
use App\Models\TrainingCourse;
use App\Models\TrainingParticipant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class TrainingController extends Controller
{
    private function conn(): string
    {
        return (string) config('service_registry.services.training.connection', config('database.default'));
    }

    private function hrConn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ─── COURSES ────────────────────────────────────────────────────────────
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/training/courses
     */
    public function indexCourses(Request $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'viewAny', TrainingCourse::class);

        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $payload = $this->rememberListCache(
            $this->listCacheKey('courses', compact('filters', 'perPage', 'page')),
            function () use ($filters, $perPage, $page): array {
                $query = TrainingCourse::query()
                    ->withParticipantCount()
                    ->applyFilters($filters)
                    ->sortDefault();

                $total = DB::connection($this->conn())->table('khoadaotao')->count();
                $rows = $query->forPage($page, $perPage)->get();
                $data = CourseResource::collection($rows)->resolve();

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
     * GET /api/training/courses/{id}
     */
    public function showCourse(int $id): JsonResponse
    {
        $item = DB::connection($this->conn())->table('khoadaotao')->where('MaKDT', $id)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Khóa đào tạo không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (new CourseResource($item))->resolve()]);
    }

    /**
     * POST /api/training/courses
     */
    public function storeCourse(StoreCourseRequest $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'create', TrainingCourse::class);

        $payload = $request->validated();
        
        try {
            $id = (int) DB::connection($this->conn())->table('khoadaotao')->insertGetId($payload, 'MaKDT');
            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'id' => $id, 'message' => 'Tạo khóa đào tạo thành công.'], 201);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * PUT /api/training/courses/{id}
     */
    public function updateCourse(UpdateCourseRequest $request, int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'update', TrainingCourse::class);

        $payload = $request->validated();
        
        try {
            $affected = DB::connection($this->conn())
                ->table('khoadaotao')
                ->where('MaKDT', $id)
                ->update($payload);

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Khóa đào tạo không tồn tại.'], 404);
            }

            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'message' => 'Cập nhật khóa đào tạo thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /api/training/courses/{id}
     */
    public function destroyCourse(Request $request, int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'delete', TrainingCourse::class);

        try {
            $deleted = DB::connection($this->conn())->transaction(function () use ($id) {
                DB::connection($this->conn())
                    ->table('thamgiadaotao')
                    ->where('MaKDT', $id)
                    ->delete();

                return DB::connection($this->conn())
                    ->table('khoadaotao')
                    ->where('MaKDT', $id)
                    ->delete();
            });

            if ($deleted === 0) {
                return response()->json(['ok' => false, 'message' => 'Khóa đào tạo không tồn tại.'], 404);
            }

            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'message' => 'Xóa khóa đào tạo thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ─── PARTICIPANTS ───────────────────────────────────────────────────────
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/training/courses/{id}/participants
     */
    public function indexParticipants(int $courseId, Request $request): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'them_tham_gia_dao_tao');

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $payload = $this->rememberListCache(
            $this->listCacheKey('participants', compact('courseId', 'perPage', 'page')),
            function () use ($courseId, $perPage, $page): array {
                $query = TrainingParticipant::query()
                    ->withEmployeeContext()
                    ->where('tg.MaKDT', $courseId)
                    ->orderBy('nv.HoTen');

                $total = (clone $query)->count();
                $rows = $query->forPage($page, $perPage)->get();
                $data = ParticipantResource::collection($rows)->resolve();

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
     * POST /api/training/courses/{id}/participants
     */
    public function addParticipant(StoreParticipantRequest $request, int $courseId): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'them_tham_gia_dao_tao');

        $payload = $request->validated();
        $payload['MaKDT'] = $courseId;
        
        try {
            $id = (int) DB::connection($this->conn())->table('thamgiadaotao')->insertGetId($payload, 'MaTGDT');
            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'id' => $id, 'message' => 'Thêm học viên thành công.'], 201);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /api/training/participants/{id}
     */
    public function removeParticipant(Request $request, int $id): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'them_tham_gia_dao_tao');

        try {
            $affected = DB::connection($this->conn())
                ->table('thamgiadaotao')
                ->where('MaTGDT', $id)
                ->delete();

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Học viên không tồn tại.'], 404);
            }

            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'message' => 'Xóa học viên thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * PUT /api/training/participants/{id}
     */
    public function updateParticipant(UpdateParticipantRequest $request, int $id): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'capnhat_ketqua_dao_tao');

        $payload = $request->validated();
        
        try {
            $affected = DB::connection($this->conn())
                ->table('thamgiadaotao')
                ->where('MaTGDT', $id)
                ->update($payload);

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Học viên không tồn tại.'], 404);
            }

            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'message' => 'Cập nhật học viên thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
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
        return 'api:training:' . $segment . ':v' . $this->listCacheVersion() . ':' . md5(json_encode($payload));
    }

    private function listCacheVersion(): int
    {
        try {
            return (int) Cache::get('api:training:list:version', 1);
        } catch (Throwable $e) {
            Log::warning('Primary cache read failed for training list version', ['error' => $e->getMessage()]);
            return (int) Cache::store($this->fallbackCacheStore())->get('api:training:list:version', 1);
        }
    }

    private function bumpListCacheVersion(): void
    {
        try {
            if (!Cache::has('api:training:list:version')) {
                Cache::forever('api:training:list:version', 1);
            }

            Cache::increment('api:training:list:version');
            return;
        } catch (Throwable $e) {
            Log::warning('Primary cache increment failed for training list version', ['error' => $e->getMessage()]);
        }

        $fallback = Cache::store($this->fallbackCacheStore());
        if (!$fallback->has('api:training:list:version')) {
            $fallback->forever('api:training:list:version', 1);
        }

        $fallback->increment('api:training:list:version');
    }

    private function rememberListCache(string $key, callable $resolver): array
    {
        $ttl = $this->listCacheTtlSeconds();

        try {
            return Cache::remember($key, $ttl, $resolver);
        } catch (Throwable $e) {
            Log::warning('Primary cache remember failed for training list', ['error' => $e->getMessage()]);
        }

        try {
            return Cache::store($this->fallbackCacheStore())->remember($key, $ttl, $resolver);
        } catch (Throwable $e) {
            Log::warning('Fallback cache remember failed for training list', ['error' => $e->getMessage()]);
            return $resolver();
        }
    }

    private function fallbackCacheStore(): string
    {
        return (string) env('API_LIST_CACHE_FALLBACK_STORE', 'file');
    }
}
