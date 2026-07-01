<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payroll\StorePayrollRequest;
use App\Http\Requests\Api\Payroll\UpdatePayrollRequest;
use App\Http\Resources\Api\Payroll\PayrollResource;
use App\Models\PayrollRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PayrollController extends Controller
{
    private function conn(): string
    {
        return (string) config('service_registry.services.payroll.connection', config('database.default'));
    }

    private function attendanceConn(): string
    {
        return (string) config('service_registry.services.attendance.connection', config('database.default'));
    }

    private function hrConn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    private function baseQuery(): Builder
    {
        return PayrollRecord::query()->withEmployeeContext();
    }

    // ─── CRUD Operations ────────────────────────────────────────────────────────

    /**
     * GET /api/payroll
     * List all payroll records with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'viewAny', PayrollRecord::class);

        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $payload = $this->rememberListCache(
            $this->listCacheKey('index', compact('filters', 'perPage', 'page')),
            function () use ($filters, $perPage, $page): array {
                $query = $this->baseQuery()
                    ->applyFilters($filters)
                    ->sortDefault();

                $total = (clone $query)->count();
                $rows = $query->forPage($page, $perPage)->get();
                $data = PayrollResource::collection($rows)->resolve();

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
     * GET /api/payroll/{id}
     * Get single payroll record
     */
    public function show(int $id): JsonResponse
    {
        $item = $this->baseQuery()->where('bl.MaBL', $id)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Bảng lương không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (new PayrollResource($item))->resolve()]);
    }

    /**
     * POST /api/payroll
     * Create or upsert payroll record
     */
    public function store(StorePayrollRequest $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'create', PayrollRecord::class);

        $payload = $request->validated();
        if (array_key_exists('TongLuong', $payload) && $payload['TongLuong'] !== null && $payload['TongLuong'] !== '') {
            $payload['TongLuong'] = round((float) $payload['TongLuong'], 0);
        }

        $db = DB::connection($this->conn());

        // If a record for the same employee/month/year already exists, update it and return its ID.
        $maNV  = $payload['MaNV'] ?? null;
        $thang = $payload['Thang'] ?? null;
        $nam   = $payload['Nam'] ?? null;

        if ($maNV !== null && $thang !== null && $nam !== null) {
            $existing = $db->table('bangluong')
                ->where('MaNV', $maNV)
                ->where('Thang', $thang)
                ->where('Nam', $nam)
                ->first(['MaBL']);

            if ($existing) {
                $updatePayload = array_diff_key($payload, array_flip(['MaNV', 'Thang', 'Nam']));
                $db->table('bangluong')->where('MaBL', $existing->MaBL)->update($updatePayload);
                $this->bumpListCacheVersion();
                return response()->json(['ok' => true, 'id' => (int) $existing->MaBL, 'updated' => true]);
            }
        }

        try {
            $id = (int) $db->table('bangluong')->insertGetId($payload, 'MaBL');
            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'id' => $id, 'message' => 'Tạo bảng lương thành công.'], 201);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * PUT /api/payroll/{id}
     * Update payroll record
     */
    public function update(UpdatePayrollRequest $request, int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'update', PayrollRecord::class);

        $payload = $request->validated();
        if (array_key_exists('TongLuong', $payload) && $payload['TongLuong'] !== null && $payload['TongLuong'] !== '') {
            $payload['TongLuong'] = round((float) $payload['TongLuong'], 0);
        }

        try {
            $affected = DB::connection($this->conn())
                ->table('bangluong')
                ->where('MaBL', $id)
                ->update($payload);

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Bảng lương không tồn tại.'], 404);
            }

            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'message' => 'Cập nhật bảng lương thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /api/payroll/{id}
     * Delete payroll record
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'delete', PayrollRecord::class);

        try {
            $affected = DB::connection($this->conn())
                ->table('bangluong')
                ->where('MaBL', $id)
                ->delete();

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Bảng lương không tồn tại.'], 404);
            }

            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'message' => 'Xóa bảng lương thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // ─── Business Logic Operations ──────────────────────────────────────────────

    /**
     * GET /api/payroll/employees/options
     * Get list of employees for dropdown
     */
    public function employeeOptions(): JsonResponse
    {
        $opts = DB::connection($this->hrConn())
            ->table('nhanvien')
            ->orderBy('HoTen')
            ->get(['MaNV', 'HoTen']);
        return response()->json(['ok' => true, 'data' => $opts]);
    }

    /**
     * POST /api/payroll/paginate
     * Advanced pagination (legacy compatibility)
     */
    public function paginate(Request $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'viewAny', PayrollRecord::class);

        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('perPage', 15), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $payload = $this->rememberListCache(
            $this->listCacheKey('paginate', compact('filters', 'perPage', 'page')),
            function () use ($filters, $perPage, $page): array {
                $query = $this->baseQuery()
                    ->applyFilters($filters)
                    ->sortDefault();

                $total = (clone $query)->count();
                $rows = $query->forPage($page, $perPage)->get();
                $data = PayrollResource::collection($rows)->resolve();

                return [
                    'ok' => true,
                    'data' => $data,
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                ];
            }
        );

        return response()->json($payload);
    }

    /**
     * POST /api/payroll/run-monthly
     * Run monthly payroll calculation for all employees
     */
    public function runMonthly(Request $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'runMonthly', PayrollRecord::class);

        $month = (int) $request->input('month', date('n'));
        $year  = (int) $request->input('year',  date('Y'));

        $employeeIds = DB::connection($this->hrConn())
            ->table('nhanvien')->where('TrangThai', 'Đang làm')->pluck('MaNV')
            ->merge(DB::connection($this->attendanceConn())->table('chamcong')->distinct()->pluck('MaNV'))
            ->unique()->filter()->values();

        foreach ($employeeIds as $empId) {
            $this->upsertMonthlyPayroll((int) $empId, $month, $year);
        }

        $this->bumpListCacheVersion();

        return response()->json([
            'ok' => true,
            'processed' => $employeeIds->count(),
            'month' => $month,
            'year' => $year,
            'message' => sprintf('Tính lương cho %d nhân viên', $employeeIds->count())
        ]);
    }

    /**
     * GET /api/payroll/salary-components
     * Get calculated salary components for employee
     */
    public function salaryComponents(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'ma_nv' => ['required', 'integer', 'min:1'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $empId = (int) $payload['ma_nv'];
        $month = (int) $payload['month'];
        $year = (int) $payload['year'];

        $components = $this->calculatePayroll($empId, $month, $year);
        $salaryBaseOptions = $this->salaryBaseOptions($empId, $month, $year);

        return response()->json([
            'ok' => true,
            'data' => [
                'LuongCoSo' => (float) ($components['LuongCoSo'] ?? 0),
                'HeSoLuong' => (float) ($components['HeSoLuong'] ?? 0),
                'HeSoChucVu' => (float) ($components['HeSoChucVu'] ?? 0),
                'PhuCap' => (float) ($components['PhuCap'] ?? 0),
                'Thuong' => (float) ($components['Thuong'] ?? 0),
                'Phat' => (float) ($components['Phat'] ?? 0),
                'BaoHiem' => (float) ($components['BaoHiem'] ?? 0),
                'TongLuong' => (float) ($components['TongLuong'] ?? 0),
                'LuongCoSoOptions' => $salaryBaseOptions,
            ],
        ]);
    }

    /**
     * GET /api/payroll/export
     * Export payroll data
     */
    public function export(Request $request): JsonResponse
    {
        $rows = DB::connection($this->conn())
            ->table('bangluong as bl')
            ->join('nhanvien as nv', 'nv.MaNV', '=', 'bl.MaNV')
            ->select(['bl.MaBL', 'bl.MaNV', 'nv.HoTen', 'bl.Thang', 'bl.Nam', 'bl.TongLuong', 'bl.TrangThai'])
            ->when($request->filled('month') || $request->filled('thang'), function ($query) use ($request) {
                $query->where('bl.Thang', (int) $request->input('month', $request->input('thang')));
            })
            ->when($request->filled('year') || $request->filled('nam'), function ($query) use ($request) {
                $query->where('bl.Nam', (int) $request->input('year', $request->input('nam')));
            })
            ->orderByDesc('bl.Nam')
            ->orderByDesc('bl.Thang')
            ->orderBy('bl.MaNV')
            ->get()
            ->toArray();

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    /**
     * POST /api/payroll/{id}/lock
     * Lock payroll record (set status to "Đã chốt")
     */
    public function lock(Request $request, int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'lock', PayrollRecord::class);

        try {
            $affected = DB::connection($this->conn())
                ->table('bangluong')
                ->where('MaBL', $id)
                ->update(['TrangThai' => 'Đã chốt']);

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Bảng lương không tồn tại.'], 404);
            }

            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'message' => 'Khóa bảng lương thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/payroll/{id}/unlock
     * Unlock payroll record (set status to "Chưa chốt")
     */
    public function unlock(Request $request, int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'unlock', PayrollRecord::class);

        try {
            $affected = DB::connection($this->conn())
                ->table('bangluong')
                ->where('MaBL', $id)
                ->update(['TrangThai' => 'Chưa chốt']);

            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Bảng lương không tồn tại.'], 404);
            }

            $this->bumpListCacheVersion();
            return response()->json(['ok' => true, 'message' => 'Mở khóa bảng lương thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function upsertMonthlyPayroll(int $empId, int $month, int $year): void
    {
        $salary = $this->calculatePayroll($empId, $month, $year);

        DB::connection($this->conn())->table('bangluong')->updateOrInsert(
            ['MaNV' => $empId, 'Thang' => $month, 'Nam' => $year],
            array_merge($salary, ['TrangThai' => 'Chưa chốt', 'NgayTinh' => now()])
        );
    }

    private function calculatePayroll(int $empId, int $month, int $year): array
    {
        $insurance   = $this->insuranceAmount($empId, $month, $year);
        $contract    = $this->contractSalaryInfo($empId, $month, $year);
        $attendance  = $this->attendanceSummary($empId, $month, $year);
        $bonusPenalty = $this->bonusPenalty($empId, $month, $year);

        $standardDays   = 26;
        $baseSalary     = (float) $contract['LuongCoSo'] * (float) $contract['HeSoLuong'];
        $allowance      = (float) $contract['PhuCap'];
        $monthlySalary  = $baseSalary + $allowance;
        $dailySalary    = $standardDays > 0 ? $monthlySalary / $standardDays : 0;
        $actualDays     = (float) ($attendance['SoNgayCong'] ?? 0);

        $salaryByAttendance = $actualDays < $standardDays ? $dailySalary * $actualDays : $monthlySalary;
        $overtimeDays       = $actualDays > $standardDays ? $actualDays - $standardDays : 0;
        $overtimeByDay      = $overtimeDays * $dailySalary * 1.5;
        $overtimeByHour     = (float) ($attendance['GioOT'] ?? 0) * ($dailySalary / 8) * 1.5;
        $latePenalty        = $this->latePenalty($empId, $month, $year, $dailySalary);
        $tongLuong = $salaryByAttendance + $overtimeByDay + $overtimeByHour
            + (float) $bonusPenalty['Thuong']
            - (float) $bonusPenalty['Phat']
            - $latePenalty - $insurance;

        return [
            'LuongCoSo'   => (float) $contract['LuongCoSo'],
            'HeSoLuong'   => (float) $contract['HeSoLuong'],
            'HeSoChucVu'  => (float) $contract['HeSoChucVu'],
            'PhuCap'      => $allowance,
            'Thuong'      => (float) $bonusPenalty['Thuong'],
            'Phat'        => (float) $bonusPenalty['Phat'] + $latePenalty,
            'BaoHiem'     => $insurance,
            'TongLuong'   => round($tongLuong, 0),
        ];
    }

    private function contractSalaryInfo(int $empId, int $month, int $year): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = date('Y-m-t', strtotime($startDate));

        $contract = DB::connection($this->conn())
            ->table('hopdong as hd')
            ->join('bacluong as bl', 'hd.MaBac', '=', 'bl.MaBac')
            ->where('hd.MaNV', $empId)
            ->where('hd.NgayBatDau', '<=', $endDate)
            ->where(fn ($q) => $q->whereNull('hd.NgayKetThuc')->orWhere('hd.NgayKetThuc', '>=', $startDate))
            ->orderByDesc('hd.NgayBatDau')
            ->first(['hd.MaHopDong', 'bl.HeSoLuong', 'bl.LuongCoSo']);

        $assignment = DB::connection($this->hrConn())
            ->table('phancong')
            ->where('MaNV', $empId)
            ->where('NgayBatDau', '<=', $endDate)
            ->orderByDesc('NgayBatDau')
            ->first(['MaCV']);

        $position = null;
        if ($assignment && !empty($assignment->MaCV)) {
            $position = DB::connection($this->hrConn())
                ->table('chucvu')
                ->where('MaCV', $assignment->MaCV)
                ->first(['HeSoChucVu', 'PhuCap']);
        }

        if (!$contract) {
            return [
                'MaHopDong' => 0,
                'HeSoLuong' => 1.0,
                'LuongCoSo' => 5310000,
                'HeSoChucVu' => (float) ($position->HeSoChucVu ?? 1.0),
                'PhuCap' => (float) ($position->PhuCap ?? 0),
            ];
        }

        return [
            'MaHopDong' => (int) ($contract->MaHopDong ?? 0),
            'HeSoLuong' => (float) ($contract->HeSoLuong ?? 1.0),
            'LuongCoSo' => (float) ($contract->LuongCoSo ?? 5310000),
            'HeSoChucVu' => (float) ($position->HeSoChucVu ?? 1.0),
            'PhuCap' => (float) ($position->PhuCap ?? 0),
        ];
    }

    private function attendanceSummary(int $empId, int $month, int $year): array
    {
        $item = DB::connection($this->attendanceConn())->table('v_tonghopcong')
            ->where('MaNV', $empId)->where('Thang', $month)->where('Nam', $year)->first(['SoNgayCong', 'GioOT']);
        return $item ? (array) $item : ['SoNgayCong' => 0, 'GioOT' => 0];
    }

    private function bonusPenalty(int $empId, int $month, int $year): array
    {
        $fmt  = sprintf('%04d-%02d', $year, $month);
        $item = DB::connection($this->hrConn())->table('khenthuongkyluat as k')
            ->join('loaikhenthuongkyluat as l', 'k.MaLoai', '=', 'l.MaLoai')
            ->where('k.MaNV', $empId)
            ->whereRaw("DATE_FORMAT(k.NgayQuyetDinh, '%Y-%m') = ?", [$fmt])
            ->selectRaw("SUM(CASE WHEN l.Loai='Khen thưởng' THEN k.SoTien ELSE 0 END) AS Thuong")
            ->selectRaw("SUM(CASE WHEN l.Loai='Kỷ luật' THEN k.SoTien ELSE 0 END) AS Phat")
            ->first();
        return ['Thuong' => (float) ($item->Thuong ?? 0), 'Phat' => (float) ($item->Phat ?? 0)];
    }

    private function insuranceAmount(int $empId, int $month, int $year): float
    {
        $endDate = sprintf('%04d-%02d-31', $year, $month);

        return (float) DB::connection($this->hrConn())
            ->table('baohiem')
            ->where('MaNV', $empId)
            ->where(function ($query) {
                $query->whereNull('TrangThai')
                    ->orWhere('TrangThai', 'like', '%đóng%')
                    ->orWhere('TrangThai', 'like', '%ong%');
            })
            ->where(function ($query) use ($endDate) {
                $query->whereNull('NgayThamGia')
                    ->orWhereDate('NgayThamGia', '<=', $endDate);
            })
            ->sum('NhanVienDong');
    }

    private function latePenalty(int $empId, int $month, int $year, float $dailySalary): float
    {
        $count = DB::connection($this->attendanceConn())->table('chamcong')
            ->where('MaNV', $empId)->whereMonth('Ngay', $month)->whereYear('Ngay', $year)
            ->where('TrangThai', 'M')->count();
        return $count * ($dailySalary * 0.1);
    }

    private function salaryBaseOptions(int $empId, int $month, int $year): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = date('Y-m-t', strtotime($startDate));

        $rows = DB::connection($this->conn())
            ->table('hopdong as hd')
            ->join('bacluong as bl', 'hd.MaBac', '=', 'bl.MaBac')
            ->where('hd.MaNV', $empId)
            ->where('hd.NgayBatDau', '<=', $endDate)
            ->where(fn ($q) => $q->whereNull('hd.NgayKetThuc')->orWhere('hd.NgayKetThuc', '>=', $startDate))
            ->orderByDesc('hd.NgayBatDau')
            ->get(['hd.MaHopDong', 'hd.MaBac', 'bl.LuongCoSo']);

        if ($rows->isEmpty()) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            $value = (float) ($row->LuongCoSo ?? 0);
            $label = sprintf('HD #%s - Bac %s - %s', (string) ($row->MaHopDong ?? ''), (string) ($row->MaBac ?? ''), number_format($value, 0, ',', '.'));
            $key = (string) $value;

            if (!isset($options[$key])) {
                $options[$key] = ['value' => $value, 'label' => $label];
            }
        }

        return array_values($options);
    }

    private function authorizeIfAuthenticated(Request $request, string $ability, mixed $arguments): void
    {
        if ($request->user() !== null) {
            $this->authorize($ability, $arguments);
        }
    }

    private function listCacheTtlSeconds(): int
    {
        return max(1, (int) env('API_LIST_CACHE_TTL', 120));
    }

    private function listCacheKey(string $segment, array $payload): string
    {
        return 'api:payroll:' . $segment . ':v' . $this->listCacheVersion() . ':' . md5(json_encode($payload));
    }

    private function listCacheVersion(): int
    {
        try {
            return (int) Cache::get('api:payroll:list:version', 1);
        } catch (Throwable $e) {
            Log::warning('Primary cache read failed for payroll list version', ['error' => $e->getMessage()]);
            return (int) Cache::store($this->fallbackCacheStore())->get('api:payroll:list:version', 1);
        }
    }

    private function bumpListCacheVersion(): void
    {
        try {
            if (!Cache::has('api:payroll:list:version')) {
                Cache::forever('api:payroll:list:version', 1);
            }

            Cache::increment('api:payroll:list:version');
            return;
        } catch (Throwable $e) {
            Log::warning('Primary cache increment failed for payroll list version', ['error' => $e->getMessage()]);
        }

        $fallback = Cache::store($this->fallbackCacheStore());
        if (!$fallback->has('api:payroll:list:version')) {
            $fallback->forever('api:payroll:list:version', 1);
        }

        $fallback->increment('api:payroll:list:version');
    }

    private function rememberListCache(string $key, callable $resolver): array
    {
        $ttl = $this->listCacheTtlSeconds();

        try {
            return Cache::remember($key, $ttl, $resolver);
        } catch (Throwable $e) {
            Log::warning('Primary cache remember failed for payroll list', ['error' => $e->getMessage()]);
        }

        try {
            return Cache::store($this->fallbackCacheStore())->remember($key, $ttl, $resolver);
        } catch (Throwable $e) {
            Log::warning('Fallback cache remember failed for payroll list', ['error' => $e->getMessage()]);
            return $resolver();
        }
    }

    private function fallbackCacheStore(): string
    {
        return (string) env('API_LIST_CACHE_FALLBACK_STORE', 'file');
    }
}
