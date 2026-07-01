<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Api\Attendance\UpdateAttendanceRequest;
use App\Http\Resources\Api\Attendance\AttendanceResource;
use App\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    private function conn(): string
    {
        return (string) config('service_registry.services.attendance.connection', config('database.default'));
    }

    private function baseQuery(): Builder
    {
        return AttendanceRecord::query()->withEmployeeContext();
    }

    // ─── CRUD Operations ────────────────────────────────────────────────────────
    
    /**
     * GET /api/attendance
     * List all attendance records with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $query = $this->baseQuery()
            ->applyFilters($filters)
            ->sortDefault();

        $total = (clone $query)->count();
        $rows = $query->forPage($page, $perPage)->get();
        $data = AttendanceResource::collection($rows)->resolve();

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
     * GET /api/attendance/{id}
     * Get single attendance record
     */
    public function show(int $id): JsonResponse
    {
        $item = $this->baseQuery()->where('cc.MaCC', $id)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Chấm công không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (new AttendanceResource($item))->resolve()]);
    }

    /**
     * POST /api/attendance
     * Create new attendance record
     */
    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $payload = $request->validated();
        
        try {
            $id = (int) DB::connection($this->conn())
                ->table('chamcong')
                ->insertGetId($payload, 'MaCC');
            
            return response()->json(['ok' => true, 'id' => $id, 'message' => 'Tạo chấm công thành công.'], 201);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * PUT /api/attendance/{id}
     * Update attendance record
     */
    public function update(UpdateAttendanceRequest $request, int $id): JsonResponse
    {
        $payload = $request->validated();
        
        try {
            $affected = DB::connection($this->conn())
                ->table('chamcong')
                ->where('MaCC', $id)
                ->update($payload);
            
            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Chấm công không tồn tại.'], 404);
            }
            
            return response()->json(['ok' => true, 'message' => 'Cập nhật chấm công thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /api/attendance/{id}
     * Delete attendance record
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $affected = DB::connection($this->conn())
                ->table('chamcong')
                ->where('MaCC', $id)
                ->delete();
            
            if ($affected === 0) {
                return response()->json(['ok' => false, 'message' => 'Chấm công không tồn tại.'], 404);
            }
            
            return response()->json(['ok' => true, 'message' => 'Xóa chấm công thành công.']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // ─── Business Logic Operations ──────────────────────────────────────────────

    /**
     * GET /api/attendance/employees/options
     * Get list of employees for dropdown
     */
    public function employeeOptions(): JsonResponse
    {
        $options = DB::connection($this->conn())
            ->table('nhanvien')
            ->orderBy('HoTen')
            ->get(['MaNV', 'HoTen']);
        
        return response()->json(['ok' => true, 'data' => $options]);
    }

    /**
     * POST /api/attendance/paginate
     * Advanced pagination with filters (for legacy compatibility)
     */
    public function paginate(Request $request): JsonResponse
    {
        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('perPage', 15), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $query = $this->baseQuery()
            ->applyFilters($filters)
            ->sortDefault();

        $total = (clone $query)->count();
        $rows = $query->forPage($page, $perPage)->get();
        $data = AttendanceResource::collection($rows)->resolve();

        return response()->json([
            'ok' => true,
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page
        ]);
    }

    /**
     * GET /api/attendance/worked-days
     * Get worked days summary for specific employee/month/year
     */
    public function workedDays(Request $request): JsonResponse
    {
        $employeeId = (int) $request->query('employee_id', 0);
        $month      = (int) $request->query('month', date('n'));
        $year       = (int) $request->query('year', date('Y'));

        $summary = DB::connection($this->conn())
            ->table('v_tonghopcong')
            ->where('MaNV', $employeeId)
            ->where('Thang', $month)
            ->where('Nam', $year)
            ->first(['SoNgayCong', 'GioOT']);

        return response()->json([
            'ok'         => true,
            'SoNgayLam'  => (float) ($summary->SoNgayCong ?? 0),
            'GioOT'      => (float) ($summary->GioOT ?? 0),
            'Thang'      => $month,
            'Nam'        => $year,
        ]);
    }

    /**
     * GET /api/attendance/export-rows
     * Export attendance records
     */
    public function exportRows(Request $request): JsonResponse
    {
        $filters = (array) $request->input('filters', []);
        $rows = $this->baseQuery()
            ->when(!empty($filters['q']), function (Builder $q) use ($filters) {
                $kw = trim((string) $filters['q']);
                $q->where(fn (Builder $i) => $i->where('nv.HoTen', 'like', "%{$kw}%")->orWhere('cc.MaNV', 'like', "%{$kw}%"));
            })
            ->when(!empty($filters['month']), function (Builder $q) use ($filters) {
                $q->whereMonth('cc.Ngay', (int) $filters['month']);
            })
            ->when(!empty($filters['year']), function (Builder $q) use ($filters) {
                $q->whereYear('cc.Ngay', (int) $filters['year']);
            })
            ->orderByDesc('cc.Ngay')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    /**
     * GET /api/attendance/monthly-matrix
     * Get attendance matrix by month (showing status for each day)
     */
    public function monthlyMatrix(Request $request): JsonResponse
    {
        $month = max(1, min(12, (int) $request->query('month', now()->month)));
        $year  = max(2000, min(2100, (int) $request->query('year', now()->year)));
        $maNV  = $request->query('ma_nv') !== null ? (int) $request->query('ma_nv') : null;

        $conn = DB::connection($this->conn());

        // Lấy tất cả nhân viên theo phòng ban (có thể lọc theo từng nhân viên cụ thể)
        $employees = $conn->table('nhanvien as nv')
            ->leftJoin('hosonhanvien as hs', 'hs.MaNV', '=', 'nv.MaNV')
            ->leftJoin('phongban as pb', 'pb.MaPB', '=', 'hs.MaPB')
            ->select(['nv.MaNV', 'nv.HoTen', 'pb.TenPB'])
            ->when($maNV !== null, fn ($q) => $q->where('nv.MaNV', $maNV))
            ->orderBy('pb.TenPB')
            ->orderBy('nv.HoTen')
            ->get();

        // Lấy các bản ghi chấm công trong tháng
        $records = $conn->table('chamcong')
            ->whereMonth('Ngay', $month)
            ->whereYear('Ngay', $year)
            ->get()
            ->groupBy('MaNV');

        $matrix = [];
        foreach ($employees as $emp) {
            $dept = (string) ($emp->TenPB ?? 'Chưa phân công');
            $empRecords = $records->get((string) $emp->MaNV, collect());
            $days = [];
            foreach ($empRecords as $rec) {
                $day = date('d', strtotime((string) $rec->Ngay));
                $days[$day] = ['s' => (string) $rec->TrangThai, 'id' => (int) $rec->MaCC];
            }
            $matrix[$dept][] = [
                'MaNV'  => (int) $emp->MaNV,
                'HoTen' => (string) $emp->HoTen,
                'Ngay'  => $days,
            ];
        }

        return response()->json(['ok' => true, 'data' => $matrix]);
    }
}
