<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentCampaign;
use App\Models\RecruitmentCandidate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;

class RecruitmentBizController extends Controller
{
    private function conn(): string
    {
        return (string) config('service_registry.services.recruitment.connection', config('database.default'));
    }

    private function hrConn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    // ─── Campaigns ───────────────────────────────────────────────────────────

    public function paginate(Request $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'viewAny', RecruitmentCampaign::class);

        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('perPage', 12), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $query = RecruitmentCampaign::query()
            ->withApplicationCount()
            ->applyFilters($filters)
            ->sortDefault();

        $total = DB::connection($this->conn())->table('dottuyendung')->count();
        $data  = $query->forPage($page, $perPage)->get()->map(fn ($r) => $r->toArray())->all();

        return response()->json(['ok' => true, 'data' => $data, 'total' => $total, 'per_page' => $perPage, 'current_page' => $page]);
    }

    public function showCampaign(int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated(request(), 'viewAny', RecruitmentCampaign::class);

        $item = DB::connection($this->conn())->table('dottuyendung')->where('MaDTD', $id)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Đợt tuyển không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (array) $item]);
    }

    // Route /api/biz/recruitment/{id}
    public function show(int $id): JsonResponse
    {
        return $this->showCampaign($id);
    }

    public function storeCampaign(Request $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'create', RecruitmentCampaign::class);

        $payload = (array) $request->json()->all();
        $id = (int) DB::connection($this->conn())->table('dottuyendung')->insertGetId($payload, 'MaDTD');
        return response()->json(['ok' => true, 'id' => $id], 201);
    }

    // Route /api/biz/recruitment
    public function store(Request $request): JsonResponse
    {
        return $this->storeCampaign($request);
    }

    public function updateCampaign(Request $request, int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'update', RecruitmentCampaign::class);

        DB::connection($this->conn())->table('dottuyendung')->where('MaDTD', $id)->update((array) $request->json()->all());
        return response()->json(['ok' => true]);
    }

    // Route /api/biz/recruitment/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        return $this->updateCampaign($request, $id);
    }

    public function destroyCampaign(Request $request, int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'delete', RecruitmentCampaign::class);

        DB::connection($this->conn())->table('dottuyendung')->where('MaDTD', $id)->delete();
        return response()->json(['ok' => true]);
    }

    // Route /api/biz/recruitment/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        return $this->destroyCampaign($request, $id);
    }

    public function campaignOptions(Request $request): JsonResponse
    {
        $openOnly = (bool) $request->query('open_only', false);
        $rows = RecruitmentCampaign::query()
            ->when($openOnly, fn ($q) => $q->where('TrangThai', 'Đang tuyển'))
            ->orderByDesc('TuNgay')->get();
        return response()->json(['ok' => true, 'data' => $rows]);
    }

    // ─── Candidates ──────────────────────────────────────────────────────────

    public function paginateCandidates(Request $request): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'them_ung_vien');

        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('perPage', 12), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $query = RecruitmentCandidate::query()
            ->select([
                'ungvien.MaUV', 'ungvien.HoTen', 'ungvien.NgaySinh', 'ungvien.GioiTinh',
                'ungvien.Email', 'ungvien.DienThoai', 'ungvien.TrinhDo', 'ungvien.KinhNghiem',
                'ungvien.FileCV', 'ungvien.DiemCV',
                DB::raw('(SELECT COUNT(*) FROM hosoungtuyen hs WHERE hs.MaUV = ungvien.MaUV) as SoHoSo'),
            ])
            ->applyFilters($filters)
            ->sortDefault();

        $total = DB::connection($this->conn())->table('ungvien')->count();
        $data  = $query->forPage($page, $perPage)->get()->map(fn ($r) => $r->toArray())->all();

        return response()->json(['ok' => true, 'data' => $data, 'total' => $total, 'per_page' => $perPage, 'current_page' => $page]);
    }

    public function showCandidate(int $id): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated(request(), 'them_ung_vien');

        $item = DB::connection($this->conn())->table('ungvien as uv')
            ->select(['uv.MaUV', 'uv.HoTen', 'uv.NgaySinh', 'uv.GioiTinh', 'uv.Email', 'uv.DienThoai', 'uv.TrinhDo', 'uv.KinhNghiem', 'uv.FileCV', 'uv.DiemCV'])
            ->where('uv.MaUV', $id)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Ứng viên không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (array) $item]);
    }

    public function storeCandidate(Request $request): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'them_ung_vien');

        $payload = (array) $request->json()->all();
        $id = (int) DB::connection($this->conn())->table('ungvien')->insertGetId([
            'HoTen'      => $payload['HoTen'],
            'NgaySinh'   => $payload['NgaySinh'] ?? null,
            'GioiTinh'   => $payload['GioiTinh'] ?? null,
            'Email'      => $payload['Email'] ?? null,
            'DienThoai'  => $payload['DienThoai'] ?? null,
            'TrinhDo'    => $payload['TrinhDo'] ?? null,
            'KinhNghiem' => $payload['KinhNghiem'] ?? null,
            'FileCV'     => $payload['FileCV'] ?? null,
            'DiemCV'     => $payload['DiemCV'] ?? 0,
        ], 'MaUV');
        return response()->json(['ok' => true, 'id' => $id], 201);
    }

    // ─── Applications ────────────────────────────────────────────────────────

    public function paginateApplications(Request $request, int $campaignId): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'capnhat_trangthai');

        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('perPage', 12), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $query = RecruitmentApplication::query()
            ->withCandidateCampaignContext()
            ->select([
                'hs.MaHS', 'hs.MaUV', 'hs.MaDTD', 'hs.TrangThai', 'hs.NgayNop', 'hs.GhiChu',
                'uv.HoTen', 'uv.Email', 'uv.DienThoai', 'uv.FileCV', 'uv.DiemCV',
                'dt.TenDotTuyenDung', 'dt.ViTriTuyenDung',
                DB::raw('(SELECT COUNT(*) FROM lichphongvan lpv WHERE lpv.MaHS = hs.MaHS) as SoLichPhongVan'),
            ])
            ->where('hs.MaDTD', $campaignId)
            ->applyFilters($filters)
            ->sortDefault();

        $total = DB::connection($this->conn())->table('hosoungtuyen')->where('MaDTD', $campaignId)->count();
        $data  = $query->forPage($page, $perPage)->get()->map(fn ($r) => $r->toArray())->all();

        return response()->json(['ok' => true, 'data' => $data, 'total' => $total, 'per_page' => $perPage, 'current_page' => $page]);
    }

    public function attachCandidate(Request $request): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'them_ho_so');

        $payload     = (array) $request->json()->all();
        $campaignId  = (int) ($payload['campaign_id'] ?? 0);
        $candidateId = (int) ($payload['candidate_id'] ?? 0);
        $note        = $payload['note'] ?? null;

        $conn       = DB::connection($this->conn());
        $existingId = $conn->table('hosoungtuyen')->where('MaDTD', $campaignId)->where('MaUV', $candidateId)->value('MaHS');

        if ($existingId) {
            return response()->json(['ok' => true, 'id' => (int) $existingId]);
        }

        $id = (int) $conn->table('hosoungtuyen')->insertGetId([
            'MaUV'      => $candidateId,
            'MaDTD'     => $campaignId,
            'TrangThai' => 'Nộp hồ sơ',
            'NgayNop'   => now()->toDateString(),
            'GhiChu'    => $note,
        ], 'MaHS');

        return response()->json(['ok' => true, 'id' => $id], 201);
    }

    public function showApplication(int $id): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated(request(), 'capnhat_trangthai');

        $item = RecruitmentApplication::query()
            ->withCandidateCampaignContext()
            ->where('hs.MaHS', $id)
            ->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Hồ sơ không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (array) $item]);
    }

    public function updateApplicationStatus(Request $request, int $id): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'capnhat_trangthai');

        $payload = (array) $request->json()->all();

        DB::connection($this->conn())->transaction(function () use ($id, $payload) {
            DB::connection($this->conn())->table('hosoungtuyen')->where('MaHS', $id)->update([
                'TrangThai' => $payload['TrangThai'],
                'GhiChu'    => $payload['GhiChu'] ?? null,
            ]);

            if (($payload['TrangThai'] ?? '') === 'Nhận việc') {
                $this->ensureEmployeeCreated($id);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function updateKanban(Request $request): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'capnhat_trangthai');

        $items = (array) $request->input('items', []);

        DB::connection($this->conn())->transaction(function () use ($items) {
            foreach ($items as $item) {
                DB::connection($this->conn())->table('hosoungtuyen')
                    ->where('MaHS', (int) $item['MaHS'])
                    ->update(['TrangThai' => $item['TrangThai']]);
            }
        });

        return response()->json(['ok' => true]);
    }

    // ─── Interviews / Reviews ────────────────────────────────────────────────

    public function listInterviews(int $applicationId): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated(request(), 'them_lich_phong_van');

        $rows = DB::connection($this->conn())->table('lichphongvan')
            ->where('MaHS', $applicationId)->orderByDesc('NgayPhongVan')->orderByDesc('GioPhongVan')
            ->get()->map(fn ($r) => (array) $r)->all();
        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function listReviews(int $applicationId): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated(request(), 'them_danh_gia');

        $rows = DB::connection($this->conn())->table('danhgiaphongvan')
            ->where('MaHS', $applicationId)->orderByDesc('MaDG')
            ->get()->map(fn ($r) => (array) $r)->all();
        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function storeInterview(Request $request, int $applicationId): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'them_lich_phong_van');

        $payload = (array) $request->json()->all();
        $id = (int) DB::connection($this->conn())->table('lichphongvan')->insertGetId([
            'MaHS'         => $applicationId,
            'NgayPhongVan' => $payload['NgayPhongVan'],
            'GioPhongVan'  => $payload['GioPhongVan'],
            'DiaDiem'      => $payload['DiaDiem'] ?? null,
            'GhiChu'       => $payload['GhiChu'] ?? null,
            'KetQua'       => $payload['KetQua'] ?? null,
        ], 'MaPV');
        return response()->json(['ok' => true, 'id' => $id], 201);
    }

    public function storeReview(Request $request, int $applicationId): JsonResponse
    {
        $this->authorizePermissionIfAuthenticated($request, 'them_danh_gia');

        $payload = (array) $request->json()->all();
        $id = (int) DB::connection($this->conn())->table('danhgiaphongvan')->insertGetId([
            'MaHS'           => $applicationId,
            'DiemKyNang'     => $payload['DiemKyNang'],
            'DiemKinhNghiem' => $payload['DiemKinhNghiem'],
            'DiemThaiDo'     => $payload['DiemThaiDo'],
            'NhanXet'        => $payload['NhanXet'] ?? null,
        ], 'MaDG');
        return response()->json(['ok' => true, 'id' => $id], 201);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private function ensureEmployeeCreated(int $applicationId): void
    {
        $hrConn = DB::connection($this->hrConn());
        if ($hrConn->table('nhanvien')->where('MaHS', $applicationId)->exists()) {
            return;
        }

        $candidate = DB::connection($this->conn())
            ->table('hosoungtuyen as hs')->join('ungvien as uv', 'uv.MaUV', '=', 'hs.MaUV')
            ->select(['uv.HoTen', 'uv.GioiTinh', 'uv.NgaySinh', 'uv.Email', 'uv.DienThoai'])
            ->where('hs.MaHS', $applicationId)->first();

        if ($candidate) {
            $hrConn->table('nhanvien')->insert([
                'HoTen' => $candidate->HoTen, 'GioiTinh' => $candidate->GioiTinh,
                'NgaySinh' => $candidate->NgaySinh, 'Email' => $candidate->Email,
                'DienThoai' => $candidate->DienThoai, 'TrangThai' => 'Đang làm',
                'MaBac' => null, 'MaHS' => $applicationId,
            ]);
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
}
