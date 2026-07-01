<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PayrollBizController extends Controller
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

    public function paginate(Request $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'viewAny', PayrollRecord::class);

        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('perPage', 15), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $query = $this->baseQuery()
            ->applyFilters($filters)
            ->sortDefault();

        $total = (clone $query)->count();
        $data  = $query->forPage($page, $perPage)->get()->map(fn ($r) => $r->toArray())->all();

        return response()->json(['ok' => true, 'data' => $data, 'total' => $total, 'per_page' => $perPage, 'current_page' => $page]);
    }

    public function show(int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated(request(), 'viewAny', PayrollRecord::class);

        $item = $this->baseQuery()->where('bl.MaBL', $id)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Bảng lương không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => $item->toArray()]);
    }

    public function employeeOptions(): JsonResponse
    {
        $this->authorizeIfAuthenticated(request(), 'viewAny', PayrollRecord::class);

        $opts = DB::connection($this->conn())->table('nhanvien')->orderBy('HoTen')->get(['MaNV', 'HoTen']);
        return response()->json(['ok' => true, 'data' => $opts]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'create', PayrollRecord::class);

        $payload = (array) $request->json()->all();
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
                return response()->json(['ok' => true, 'id' => (int) $existing->MaBL, 'updated' => true]);
            }
        }

        $id = (int) $db->table('bangluong')->insertGetId($payload, 'MaBL');
        return response()->json(['ok' => true, 'id' => $id], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'update', PayrollRecord::class);

        $payload = (array) $request->json()->all();
        if (array_key_exists('TongLuong', $payload) && $payload['TongLuong'] !== null && $payload['TongLuong'] !== '') {
            $payload['TongLuong'] = round((float) $payload['TongLuong'], 0);
        }

        DB::connection($this->conn())->table('bangluong')->where('MaBL', $id)->update($payload);
        return response()->json(['ok' => true]);
    }

    public function runMonthly(Request $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'runMonthly', PayrollRecord::class);

        $month = (int) $request->input('month', date('n'));
        $year  = (int) $request->input('year',  date('Y'));

        $employeeIds = DB::connection($this->conn())
            ->table('nhanvien')->where('TrangThai', 'Đang làm')->pluck('MaNV')
            ->merge(DB::connection($this->attendanceConn())->table('chamcong')->distinct()->pluck('MaNV'))
            ->unique()->filter()->values();

        foreach ($employeeIds as $empId) {
            $this->upsertMonthlyPayroll((int) $empId, $month, $year);
        }

        return response()->json(['ok' => true, 'processed' => $employeeIds->count(), 'month' => $month, 'year' => $year]);
    }

    public function salaryComponents(Request $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'viewAny', PayrollRecord::class);

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

    // ─── Helpers ─────────────────────────────────────────────────────────────

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

    public function export(Request $request): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'viewAny', PayrollRecord::class);

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

        return response()->json(['data' => $rows]);
    }

    public function lock(Request $request, int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'lock', PayrollRecord::class);

        $affected = DB::connection($this->conn())
            ->table('bangluong')
            ->where('MaBL', $id)
            ->update(['TrangThai' => 'Đã chốt']);

        if ($affected === 0) {
            return response()->json(['message' => 'Không tìm thấy bảng lương.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    public function unlock(Request $request, int $id): JsonResponse
    {
        $this->authorizeIfAuthenticated($request, 'unlock', PayrollRecord::class);

        $affected = DB::connection($this->conn())
            ->table('bangluong')
            ->where('MaBL', $id)
            ->update(['TrangThai' => 'Chưa chốt']);

        if ($affected === 0) {
            return response()->json(['message' => 'Không tìm thấy bảng lương.'], 404);
        }

        return response()->json(['ok' => true]);
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
