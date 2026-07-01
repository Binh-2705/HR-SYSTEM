<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrainingCourse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TrainingBizController extends Controller
{
    private function conn(): string
    {
        return (string) config('service_registry.services.training.connection', config('database.default'));
    }

    private function hrConn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    public function paginate(Request $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'viewAny', TrainingCourse::class);

        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('perPage', 12), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $query = TrainingCourse::query()
            ->withParticipantCount()
            ->applyFilters($filters)
            ->sortDefault();

        $total = DB::connection($this->conn())->table('khoadaotao')->count();
        $data  = $query->forPage($page, $perPage)->get()->map(fn ($r) => $r->toArray())->all();

        return response()->json(['ok' => true, 'data' => $data, 'total' => $total, 'per_page' => $perPage, 'current_page' => $page]);
    }

    public function show(int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated(request(), 'viewAny', TrainingCourse::class);

        $item = DB::connection($this->conn())->table('khoadaotao')->where('MaKDT', $id)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Khóa đào tạo không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (array) $item]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'create', TrainingCourse::class);

        $payload = (array) $request->json()->all();
        $id = (int) DB::connection($this->conn())->table('khoadaotao')->insertGetId($payload, 'MaKDT');
        return response()->json(['ok' => true, 'id' => $id], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'update', TrainingCourse::class);

        DB::connection($this->conn())->table('khoadaotao')->where('MaKDT', $id)->update((array) $request->json()->all());
        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'delete', TrainingCourse::class);

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

        return response()->json(['ok' => true]);
    }

    public function participantsPageData(int $courseId): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated(request(), 'them_tham_gia_dao_tao');

        $course = DB::connection($this->conn())->table('khoadaotao')->where('MaKDT', $courseId)->first();
        if (!$course) {
            return response()->json(['ok' => false, 'message' => 'Khóa học không tồn tại.'], 404);
        }

        $participants = DB::connection($this->conn())->table('thamgiadaotao')
            ->where('MaKDT', $courseId)->orderBy('MaTGDT')
            ->get(['MaTGDT', 'MaNV', 'MaKDT', 'KetQua', 'DiemDanhGia', 'GhiChu']);

        $employeeIds = array_values(array_unique($participants->pluck('MaNV')->map(fn ($id) => (int) $id)->all()));
        $employeesById = $this->employeesById($employeeIds);

        $participantData = $participants->map(function ($p) use ($employeesById) {
            return [
                'MaTGDT'      => (int) $p->MaTGDT,
                'MaNV'        => (int) $p->MaNV,
                'KetQua'      => (string) ($p->KetQua ?? 'Chua danh gia'),
                'DiemDanhGia' => $p->DiemDanhGia !== null ? (float) $p->DiemDanhGia : null,
                'GhiChu'      => (string) ($p->GhiChu ?? ''),
                'HoTen'       => (string) ($employeesById[(int) $p->MaNV]['HoTen'] ?? ('NV #' . $p->MaNV)),
            ];
        })->all();

        $assignedIds = array_column($participantData, 'MaNV');
        $available   = DB::connection($this->hrConn())->table('nhanvien')
            ->when($assignedIds !== [], fn ($q) => $q->whereNotIn('MaNV', $assignedIds))
            ->where('TrangThai', 'Đang làm')->orderBy('HoTen')
            ->get(['MaNV', 'HoTen'])->map(fn ($e) => ['MaNV' => (int) $e->MaNV, 'HoTen' => (string) $e->HoTen])->all();

        return response()->json([
            'ok'           => true,
            'course'       => (array) $course,
            'participants' => $participantData,
            'employees'    => $available,
            'canEvaluate'  => isset($course->DenNgay) && strtotime((string) $course->DenNgay) <= strtotime(date('Y-m-d')),
        ]);
    }

    public function addParticipant(Request $request, int $courseId): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'them_tham_gia_dao_tao');

        $payload    = (array) $request->json()->all();
        $employeeId = (int) ($payload['MaNV'] ?? 0);

        $exists = DB::connection($this->conn())->table('thamgiadaotao')
            ->where('MaKDT', $courseId)->where('MaNV', $employeeId)->exists();

        if ($exists) {
            return response()->json(['ok' => false, 'message' => 'Nhân viên đã tham gia khóa học này.'], 409);
        }

        DB::connection($this->conn())->table('thamgiadaotao')->insert([
            'MaNV' => $employeeId, 'MaKDT' => $courseId,
            'KetQua' => 'Chưa đánh giá', 'DiemDanhGia' => null, 'GhiChu' => null,
        ]);

        return response()->json(['ok' => true], 201);
    }

    public function updateParticipantResult(Request $request, int $participantId): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'capnhat_ketqua_dao_tao');

        $payload = (array) $request->json()->all();
        DB::connection($this->conn())->table('thamgiadaotao')->where('MaTGDT', $participantId)->update($payload);
        return response()->json(['ok' => true]);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private function employeesById(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::connection($this->hrConn())->table('nhanvien')->whereIn('MaNV', $ids)
            ->get(['MaNV', 'HoTen'])
            ->mapWithKeys(fn ($e) => [(int) $e->MaNV => ['MaNV' => (int) $e->MaNV, 'HoTen' => (string) $e->HoTen]])
            ->all();
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
}
