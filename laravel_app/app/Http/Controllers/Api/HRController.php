<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Hr\StoreEmployeeRequest;
use App\Http\Requests\Api\Hr\UpdateEmployeeRequest;
use App\Http\Resources\Api\Hr\EmployeeResource;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HRController extends Controller
{
    private function conn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ─── EMPLOYEES ──────────────────────────────────────────────────────────
    // ──────────────────────────────────────────────────────────────────────────

    private function employeeBaseQuery(): Builder
    {
        return DB::connection($this->conn())
            ->table('nhanvien as nv')
            ->leftJoin('bacluong as bl', 'nv.MaBac', '=', 'bl.MaBac')
            ->leftJoin('ngachluong as nl', 'bl.MaNgach', '=', 'nl.MaNgach')
            ->leftJoin('hosonhanvien as hs', 'nv.MaNV', '=', 'hs.MaNV')
            ->leftJoinSub(
                DB::connection($this->conn())->table('phancong as pc1')
                    ->select('pc1.MaNV', DB::raw('MAX(pc1.MaQT) as LatestAssignmentId'))
                    ->groupBy('pc1.MaNV'),
                'latest_pc',
                fn ($join) => $join->on('latest_pc.MaNV', '=', 'nv.MaNV')
            )
            ->leftJoin('phancong as pc', 'pc.MaQT', '=', 'latest_pc.LatestAssignmentId')
            ->leftJoin('phongban as pb', 'pb.MaPB', '=', 'pc.MaPB')
            ->leftJoin('chucvu as cv', 'cv.MaCV', '=', 'pc.MaCV')
            ->select([
                'nv.MaNV', 'nv.HoTen', 'nv.GioiTinh', 'nv.NgaySinh',
                'nv.Email', 'nv.DienThoai', 'nv.TrangThai', 'nv.MaBac',
                'bl.TenBac', 'nl.TenNgach', 'hs.DiaChi', 'hs.NgayVaoLam',
                'pb.MaPB as CurrentMaPB', 'pb.TenPB', 'cv.MaCV as CurrentMaCV', 'cv.TenCV',
            ]);
    }

    /**
     * GET /api/hr/employees
     */
    public function indexEmployees(Request $request): JsonResponse
    {
        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $query = $this->employeeBaseQuery()
            ->when(!empty($filters['ma_nv']), fn (Builder $q) => $q->where('nv.MaNV', (int) $filters['ma_nv']))
            ->when(!empty($filters['q']), function (Builder $q) use ($filters) {
                $kw = trim((string) $filters['q']);
                $q->where(fn (Builder $i) => $i
                    ->where('nv.HoTen', 'like', "%{$kw}%")
                    ->orWhere('nv.Email', 'like', "%{$kw}%")
                    ->orWhere('nv.DienThoai', 'like', "%{$kw}%")
                    ->orWhere('nv.MaNV', 'like', "%{$kw}%")
                );
            })
            ->when(!empty($filters['status']), fn (Builder $q) => $q->where('nv.TrangThai', $filters['status']))
            ->when(!empty($filters['department']), fn (Builder $q) => $q->where('pb.MaPB', (int) $filters['department']))
            ->orderBy('nv.MaNV');

        $total = (clone $query)->count();
        $rows = $query->forPage($page, $perPage)->get();
        $data = EmployeeResource::collection($rows)->resolve();

        return response()->json([
            'ok' => true,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
            ]
        ]);
    }

    /**
     * GET /api/hr/employees/{id}
     */
    public function showEmployee(int $id): JsonResponse
    {
        $item = $this->employeeBaseQuery()->where('nv.MaNV', $id)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Nhân viên không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (new EmployeeResource($item))->resolve()]);
    }

    /**
     * POST /api/hr/employees
     */
    public function storeEmployee(StoreEmployeeRequest $request): JsonResponse
    {
        $payload = $request->validated();

        try {
            $employeeId = DB::connection($this->conn())->transaction(function () use ($payload) {
                $conn = DB::connection($this->conn());
                $id = (int) $conn->table('nhanvien')->insertGetId([
                    'HoTen'      => $payload['HoTen'],
                    'GioiTinh'   => $payload['GioiTinh'] ?? null,
                    'NgaySinh'   => $payload['NgaySinh'] ?? null,
                    'Email'      => $payload['Email'] ?? null,
                    'DienThoai'  => $payload['DienThoai'] ?? null,
                    'TrangThai'  => $payload['TrangThai'],
                    'MaBac'      => $payload['MaBac'] ?? null,
                ], 'MaNV');

                if (!empty($payload['profile'])) {
                    $conn->table('hosonhanvien')->insert(array_merge(
                        $payload['profile'],
                        ['MaNV' => $id]
                    ));
                }

                if (!empty($payload['assignment'])) {
                    $conn->table('phancong')->insert(array_merge(
                        $payload['assignment'],
                        ['MaNV' => $id]
                    ));
                }

                return $id;
            });

            return response()->json(['ok' => true, 'id' => $employeeId, 'message' => 'Tạo nhân viên thành công.'], 201);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * PUT /api/hr/employees/{id}
     */
    public function updateEmployee(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        $payload = $request->validated();

        try {
            DB::connection($this->conn())->transaction(function () use ($id, $payload) {
                $conn = DB::connection($this->conn());
                $conn->table('nhanvien')->where('MaNV', $id)->update([
                    'HoTen'     => $payload['HoTen'],
                    'GioiTinh'  => $payload['GioiTinh'] ?? null,
                    'NgaySinh'  => $payload['NgaySinh'] ?? null,
                    'Email'     => $payload['Email'] ?? null,
                    'DienThoai' => $payload['DienThoai'] ?? null,
                    'TrangThai' => $payload['TrangThai'],
                    'MaBac'     => $payload['MaBac'] ?? null,
                ]);
            });

            return response()->json(['ok' => true, 'message' => 'Cập nhật nhân viên thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /api/hr/employees/{id}
     */
    public function destroyEmployee(int $id): JsonResponse
    {
        try {
            $affected = DB::connection($this->conn())
                ->table('nhanvien')
                ->where('MaNV', $id)
                ->delete();

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Nhân viên không tồn tại.'], 404);
            }

            return response()->json(['ok' => true, 'message' => 'Xóa nhân viên thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ─── ACCOUNTS ───────────────────────────────────────────────────────────
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/hr/accounts/{id}
     */
    public function showAccount(int $id): JsonResponse
    {
        $row = DB::connection($this->conn())
            ->table('taikhoan as tk')
            ->leftJoin('taikhoanvaitro as tkvt', 'tk.MaTK', '=', 'tkvt.MaTK')
            ->leftJoin('vaitro as vt', 'tkvt.MaVaiTro', '=', 'vt.MaVaiTro')
            ->select('tk.*', DB::raw("COALESCE(vt.TenVaiTro, 'NhanVien') as VaiTro"))
            ->where('tk.MaTK', $id)->first();

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Tài khoản không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (array) $row]);
    }

    /**
     * GET /api/hr/accounts/by-username/{username}
     */
    public function showAccountByUsername(string $username): JsonResponse
    {
        $row = DB::connection($this->conn())
            ->table('taikhoan as tk')
            ->leftJoin('taikhoanvaitro as tkvt', 'tk.MaTK', '=', 'tkvt.MaTK')
            ->leftJoin('vaitro as vt', 'tkvt.MaVaiTro', '=', 'vt.MaVaiTro')
            ->select('tk.*', DB::raw("COALESCE(vt.TenVaiTro, 'NhanVien') as VaiTro"))
            ->where('tk.TenDangNhap', trim($username))->first();

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Tài khoản không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (array) $row]);
    }

    /**
     * PATCH /api/hr/accounts/{id}/username
     */
    public function updateAccountUsername(Request $request, int $id): JsonResponse
    {
        $username = trim((string) $request->input('TenDangNhap', ''));
        
        try {
            $affected = DB::connection($this->conn())
                ->table('taikhoan')
                ->where('MaTK', $id)
                ->update(['TenDangNhap' => $username]);
            
            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Tài khoản không tồn tại.'], 404);
            }

            return response()->json(['ok' => true, 'message' => 'Cập nhật tên đăng nhập thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * PATCH /api/hr/accounts/{id}/password
     */
    public function updateAccountPassword(Request $request, int $id): JsonResponse
    {
        $payload = (array) $request->json()->all();

        try {
            $affected = DB::connection($this->conn())
                ->table('taikhoan')
                ->where('MaTK', $id)
                ->update([
                    'MatKhau'            => $payload['MatKhau'],
                    'BuocDoiMatKhau'     => (bool) ($payload['BuocDoiMatKhau'] ?? false) ? 1 : 0,
                    'NgayCapMatKhauTam'  => !empty($payload['BuocDoiMatKhau']) ? now() : null,
                ]);

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Tài khoản không tồn tại.'], 404);
            }

            return response()->json(['ok' => true, 'message' => 'Cập nhật mật khẩu thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ─── DEPARTMENTS ────────────────────────────────────────────────────────
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/hr/departments
     */
    public function indexDepartments(): JsonResponse
    {
        $data = DB::connection($this->conn())
            ->table('phongban')
            ->orderBy('MaPB')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /**
     * GET /api/hr/departments/{id}
     */
    public function showDepartment(int $id): JsonResponse
    {
        $item = DB::connection($this->conn())
            ->table('phongban')
            ->where('MaPB', $id)
            ->first();

        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Phòng ban không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (array) $item]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ─── ROLES & PERMISSIONS ───────────────────────────────────────────────
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/hr/roles
     */
    public function indexRoles(): JsonResponse
    {
        $data = DB::connection($this->conn())
            ->table('vaitro')
            ->orderBy('MaVaiTro')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /**
     * GET /api/hr/roles/{id}
     */
    public function showRole(int $id): JsonResponse
    {
        $item = DB::connection($this->conn())
            ->table('vaitro')
            ->where('MaVaiTro', $id)
            ->first();

        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Vai trò không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (array) $item]);
    }

    /**
     * GET /api/hr/features
     */
    public function indexFeatures(): JsonResponse
    {
        $data = DB::connection($this->conn())
            ->table('chucnang')
            ->orderBy('MaCN')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /**
     * GET /api/hr/role-permissions
     */
    public function indexRolePermissions(): JsonResponse
    {
        $data = DB::connection($this->conn())
            ->table('phanquyen as pq')
            ->join('vaitro as vt', 'pq.MaVaiTro', '=', 'vt.MaVaiTro')
            ->join('chucnang as cn', 'pq.MaCN', '=', 'cn.MaCN')
            ->select('pq.*', 'vt.TenVaiTro', 'cn.TenCN')
            ->orderBy('pq.MaVaiTro')
            ->orderBy('pq.MaCN')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /**
     * GET /api/hr/account-roles
     */
    public function indexAccountRoles(): JsonResponse
    {
        $data = DB::connection($this->conn())
            ->table('taikhoanvaitro as tkvt')
            ->join('taikhoan as tk', 'tkvt.MaTK', '=', 'tk.MaTK')
            ->join('vaitro as vt', 'tkvt.MaVaiTro', '=', 'vt.MaVaiTro')
            ->select('tkvt.*', 'tk.TenDangNhap', 'vt.TenVaiTro')
            ->orderBy('tkvt.MaTK')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /**
     * POST /api/hr/role-permissions/{role_id}/{feature_id}
     */
    public function assignRolePermission(int $roleId, int $featureId): JsonResponse
    {
        try {
            DB::connection($this->conn())
                ->table('phanquyen')
                ->insertOrIgnore(['MaVaiTro' => $roleId, 'MaCN' => $featureId]);

            return response()->json(['ok' => true, 'message' => 'Gán quyền thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /api/hr/role-permissions/{role_id}/{feature_id}
     */
    public function revokeRolePermission(int $roleId, int $featureId): JsonResponse
    {
        try {
            $affected = DB::connection($this->conn())
                ->table('phanquyen')
                ->where('MaVaiTro', $roleId)
                ->where('MaCN', $featureId)
                ->delete();

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Quyền không tồn tại.'], 404);
            }

            return response()->json(['ok' => true, 'message' => 'Thu hồi quyền thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ─── OPTIONS & UTILITIES ───────────────────────────────────────────────
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/hr/options
     * Get all reference data (departments, positions, roles, etc.)
     */
    public function options(): JsonResponse
    {
        $conn = DB::connection($this->conn());
        return response()->json([
            'ok' => true,
            'departments'  => $conn->table('phongban')->orderBy('TenPB')->get(),
            'positions'    => $conn->table('chucvu')->orderBy('TenCV')->get(),
            'roles'        => $conn->table('vaitro')->orderBy('TenVaiTro')->get(),
            'features'     => $conn->table('chucnang')->orderBy('TenCN')->get(),
            'salaryGrades' => $conn->table('bacluong as b')
                ->leftJoin('ngachluong as n', 'b.MaNgach', '=', 'n.MaNgach')
                ->select('b.MaBac', 'b.TenBac', 'b.HeSoLuong', 'b.LuongCoSo', 'n.TenNgach')
                ->orderBy('n.TenNgach')
                ->orderBy('b.HeSoLuong')
                ->get(),
        ]);
    }
}
