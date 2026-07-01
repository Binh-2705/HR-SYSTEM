<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

use App\Jobs\ProcessMonthlyPayrollJob;
use App\Services\InternalApiClient;
use App\Services\PayrollService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function __construct(
        private PayrollService $payrollService,
        private InternalApiClient $client,
    ) {}

    public function index(Request $request): View
    {
        $account  = (array) session('taikhoan', []);
        $role     = strtolower(trim((string) ($account['VaiTro'] ?? '')));
        $ownMaNV  = (int) ($account['MaNV'] ?? 0);
        $isSelfView = $role === 'nhanvien' && $ownMaNV > 0;

        $filters = $isSelfView
            ? ['ma_nv' => $ownMaNV]
            : $request->only(['q', 'month', 'year', 'status']);

        $records = $this->payrollService->paginate($filters);
        /** @var LengthAwarePaginator $records */
        $records->setCollection(
            $records->getCollection()->map(fn ($record) => (object) $this->normalizePayrollListRow((array) $record))
        );
        $records->appends($request->query());

        return view('luong.index', [
            'records'    => $records,
            'filters'    => $isSelfView ? [] : $request->only(['q', 'month', 'year', 'status']),
            'isSelfView' => $isSelfView,
        ]);
    }

    public function create(): View
    {
        return view('luong.form', [
            'mode' => 'create',
            'record' => null,
            'employees' => $this->payrollService->employeeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->normalizePayload($this->validatePayload($request));

        try {
            $payrollId = $this->payrollService->create($payload);

            return redirect()->route('luong.edit', ['payroll' => $payrollId])
                ->with('success', 'Đã tạo bảng lương thành công.');
        } catch (QueryException $exception) {
            return back()->withInput()->withErrors(['form' => 'Không thể tạo bảng lương: ' . $exception->getMessage()]);
        }
    }

    public function edit(int $payroll): View
    {
        $item = $this->payrollService->find($payroll);
        abort_if($item === null, 404);

        return view('luong.form', [
            'mode' => 'edit',
            'record' => $item,
            'employees' => $this->payrollService->employeeOptions(),
        ]);
    }

    public function show(int $payroll): View
    {
        $item = $this->payrollService->find($payroll);
        abort_if($item === null, 404);

        return view('luong.show', [
            'record' => $item,
        ]);
    }

    public function update(Request $request, int $payroll): RedirectResponse
    {
        $payload = $this->normalizePayload($this->validatePayload($request));

        try {
            $this->payrollService->update($payroll, $payload);

            return redirect()->route('luong.edit', ['payroll' => $payroll])
                ->with('success', 'Đã cập nhật bảng lương thành công.');
        } catch (QueryException $exception) {
            return back()->withInput()->withErrors(['form' => 'Không thể cập nhật bảng lương: ' . $exception->getMessage()]);
        }
    }

    public function runMonthly(Request $request): JsonResponse|RedirectResponse
    {
        $payload = $request->validate([
            'thang' => ['required', 'integer', 'between:1,12'],
            'nam'   => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $month  = (int) $payload['thang'];
        $year   = (int) $payload['nam'];
        $maTK   = (int) session('MaTK', 0);

        // Nếu queue là database/redis → dispatch async
        $isAsync = config('queue.default') !== 'sync';

        if ($isAsync) {
            ProcessMonthlyPayrollJob::dispatch($month, $year, $maTK)->onQueue('payroll');

            if (!$request->expectsJson() && !$request->ajax()) {
                return redirect()->route('luong.index', ['month' => $month, 'year' => $year])
                    ->with('success', "Đã gửi job tính lương tháng {$month}/{$year} vào hàng đợi. Kết quả sẽ sẵn sàng sau ít phút.");
            }

            return response()->json([
                'ok'         => true,
                'queued'     => true,
                'status_url' => route('luong.job-status', ['month' => $month, 'year' => $year]),
                'message'    => "Job tính lương tháng {$month}/{$year} đã được đưa vào hàng đợi.",
            ]);
        }

        // Fallback: sync (chạy ngay, dùng khi QUEUE_CONNECTION=sync)
        try {
            $count = $this->payrollService->processMonthlyPayroll($month, $year);

            if (!$request->expectsJson() && !$request->ajax()) {
                return redirect()->route('luong.index', ['month' => $month, 'year' => $year])
                    ->with('success', "Đã tính lương tháng {$month}/{$year} thành công. Số bảng lương đã xử lý: {$count}.");
            }

            return response()->json([
                'ok'        => true,
                'queued'    => false,
                'processed' => $count,
                'message'   => 'Đã tính lương thành công.',
            ]);
        } catch (\Throwable) {
            if (!$request->expectsJson() && !$request->ajax()) {
                return redirect()->route('luong.index')
                    ->withErrors(['form' => 'Không thể tính lương. Vui lòng kiểm tra dữ liệu chấm công.']);
            }

            return response()->json([
                'ok'      => false,
                'message' => 'Không thể tính lương. Vui lòng kiểm tra dữ liệu chấm công.',
            ], 422);
        }
    }

    public function jobStatus(Request $request): JsonResponse
    {
        $month     = (int) $request->input('month', date('n'));
        $year      = (int) $request->input('year', date('Y'));
        $cacheKey  = "payroll_job_status_{$month}_{$year}";
        $status    = Cache::get($cacheKey);

        if ($status === null) {
            return response()->json(['ok' => true, 'status' => 'not_started', 'month' => $month, 'year' => $year]);
        }

        return response()->json(['ok' => true] + (array) $status);
    }

    public function salaryComponents(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'MaNV' => ['required', 'integer', 'min:1'],
            'Thang' => ['required', 'integer', 'between:1,12'],
            'Nam' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $maNV = (int) $payload['MaNV'];
        $month = (int) $payload['Thang'];
        $year = (int) $payload['Nam'];

        try {
            $response = (array) $this->payrollService->salaryComponents($maNV, $month, $year);
            if (isset($response['data']) && is_array($response['data']) && array_key_exists('TongLuong', $response['data']) && $response['data']['TongLuong'] !== null && $response['data']['TongLuong'] !== '') {
                $response['data']['TongLuong'] = round((float) $response['data']['TongLuong'], 0);
            }

            return response()->json($response);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => true,
                'data' => $this->salaryComponentsFallback($maNV, $month, $year),
            ]);
        }
    }

    private function salaryComponentsFallback(int $maNV, int $month, int $year): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $payrollConn = (string) config('service_registry.services.payroll.connection', config('database.default'));
        $hrConn = (string) config('service_registry.services.hr.connection', config('database.default'));
        $attendanceConn = (string) config('service_registry.services.attendance.connection', config('database.default'));

        $contract = DB::connection($payrollConn)
            ->table('hopdong as hd')
            ->join('bacluong as bl', 'hd.MaBac', '=', 'bl.MaBac')
            ->where('hd.MaNV', $maNV)
            ->where('hd.NgayBatDau', '<=', $endDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('hd.NgayKetThuc')
                    ->orWhere('hd.NgayKetThuc', '>=', $startDate);
            })
            ->orderByDesc('hd.NgayBatDau')
            ->first(['hd.MaHopDong', 'hd.MaBac', 'bl.LuongCoSo', 'bl.HeSoLuong']);

        $assignment = DB::connection($hrConn)
            ->table('phancong')
            ->where('MaNV', $maNV)
            ->where('NgayBatDau', '<=', $endDate)
            ->orderByDesc('NgayBatDau')
            ->first(['MaCV']);

        $position = null;
        if ($assignment && !empty($assignment->MaCV)) {
            $position = DB::connection($hrConn)
                ->table('chucvu')
                ->where('MaCV', $assignment->MaCV)
                ->first(['HeSoChucVu', 'PhuCap']);
        }

        $luongCoSo = (float) ($contract->LuongCoSo ?? 5310000);
        $heSoLuong = (float) ($contract->HeSoLuong ?? 1);
        $heSoChucVu = (float) ($position->HeSoChucVu ?? 1);
        $phuCap = (float) ($position->PhuCap ?? 0);

        $soNgayCong = (float) DB::connection($attendanceConn)
            ->table('chamcong')
            ->where('MaNV', $maNV)
            ->whereMonth('Ngay', $month)
            ->whereYear('Ngay', $year)
            ->whereIn('TrangThai', ['Di lam', 'Di muon', 'Nghi phep', 'Cong tac', 'Le'])
            ->count();

        $gioOT = 0.0;
        $standardDays = 26.0;
        $baseSalary = $luongCoSo * $heSoLuong;
        $monthlySalary = $baseSalary + $phuCap;
        $dailySalary = $standardDays > 0 ? ($monthlySalary / $standardDays) : 0;
        $salaryByAttendance = $soNgayCong < $standardDays ? ($dailySalary * $soNgayCong) : $monthlySalary;
        $overtimeDays = $soNgayCong > $standardDays ? ($soNgayCong - $standardDays) : 0;
        $overtimeByDay = $overtimeDays * $dailySalary * 1.5;
        $overtimeByHour = $gioOT * ($dailySalary / 8) * 1.5;

        $fmt = sprintf('%04d-%02d', $year, $month);
        $rewardDiscipline = DB::connection($hrConn)
            ->table('khenthuongkyluat as k')
            ->join('loaikhenthuongkyluat as l', 'k.MaLoai', '=', 'l.MaLoai')
            ->where('k.MaNV', $maNV)
            ->whereRaw("DATE_FORMAT(k.NgayQuyetDinh, '%Y-%m') = ?", [$fmt])
            ->selectRaw("SUM(CASE WHEN l.Loai='Khen thưởng' THEN k.SoTien ELSE 0 END) AS Thuong")
            ->selectRaw("SUM(CASE WHEN l.Loai='Kỷ luật' THEN k.SoTien ELSE 0 END) AS Phat")
            ->first();

        $thuong = (float) ($rewardDiscipline->Thuong ?? 0);
        $phat = (float) ($rewardDiscipline->Phat ?? 0);

        $baoHiem = (float) DB::connection($hrConn)
            ->table('baohiem')
            ->where('MaNV', $maNV)
            ->where(function ($query) {
                $query->whereNull('NgayThamGia')
                    ->orWhereDate('NgayThamGia', '<=', now()->toDateString());
            })
            ->sum('NhanVienDong');

        $tongLuong = round($salaryByAttendance + $overtimeByDay + $overtimeByHour + $thuong - $phat - $baoHiem, 0);

        $optionRows = DB::connection($payrollConn)
            ->table('hopdong as hd')
            ->join('bacluong as bl', 'hd.MaBac', '=', 'bl.MaBac')
            ->where('hd.MaNV', $maNV)
            ->where('hd.NgayBatDau', '<=', $endDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('hd.NgayKetThuc')
                    ->orWhere('hd.NgayKetThuc', '>=', $startDate);
            })
            ->orderByDesc('hd.NgayBatDau')
            ->get(['hd.MaHopDong', 'hd.MaBac', 'bl.LuongCoSo']);

        $optionMap = [];
        foreach ($optionRows as $row) {
            $value = (float) ($row->LuongCoSo ?? 0);
            $key = (string) $value;
            if (!isset($optionMap[$key])) {
                $optionMap[$key] = [
                    'value' => $value,
                    'label' => sprintf('HD #%s - Bac %s - %s', (string) ($row->MaHopDong ?? ''), (string) ($row->MaBac ?? ''), number_format($value, 0, ',', '.')),
                ];
            }
        }

        if ($optionMap === []) {
            $optionMap[(string) $luongCoSo] = [
                'value' => $luongCoSo,
                'label' => number_format($luongCoSo, 0, ',', '.') . ' VNĐ',
            ];
        }

        return [
            'LuongCoSo' => $luongCoSo,
            'HeSoLuong' => $heSoLuong,
            'HeSoChucVu' => $heSoChucVu,
            'PhuCap' => $phuCap,
            'Thuong' => $thuong,
            'Phat' => $phat,
            'BaoHiem' => $baoHiem,
            'TongLuong' => $tongLuong,
            'LuongCoSoOptions' => array_values($optionMap),
        ];
    }


    public function exportExcel(Request $request): StreamedResponse
    {
        $rows = $this->client->get('biz/payroll/export', $request->only(['month', 'year', 'thang', 'nam']))['data'] ?? [];

        return response()->streamDownload(function () use ($rows) {
            echo "\xEF\xBB\xBF";
            echo "<table border='1'><tr><th>Mã BL</th><th>Mã NV</th><th>Nhân viên</th><th>Tháng</th><th>Năm</th><th>Tổng lương</th><th>Trạng thái</th></tr>";
            foreach ($rows as $row) {
                $row = (array) $row;
                echo '<tr>';
                echo '<td>' . e((string) ($row['MaBL'] ?? '')) . '</td>';
                echo '<td>' . e((string) ($row['MaNV'] ?? '')) . '</td>';
                echo '<td>' . e((string) ($row['HoTen'] ?? '')) . '</td>';
                echo '<td>' . e((string) ($row['Thang'] ?? '')) . '</td>';
                echo '<td>' . e((string) ($row['Nam'] ?? '')) . '</td>';
                echo '<td>' . e((string) ($row['TongLuong'] ?? '')) . '</td>';
                echo '<td>' . e((string) ($row['TrangThai'] ?? '')) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }, 'bang_luong_' . now()->format('Ymd-His') . '.xls', [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    public function lock(int $payroll): RedirectResponse
    {
        $this->client->post("biz/payroll/{$payroll}/lock");
        return redirect()->route('luong.index')->with('success', 'Đã chốt lương thành công.');
    }

    public function unlock(int $payroll): RedirectResponse
    {
        $this->client->post("biz/payroll/{$payroll}/unlock");
        return redirect()->route('luong.index')->with('success', 'Đã mở chốt lương thành công.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'MaNV' => ['required', 'integer'],
            'Thang' => ['required', 'integer', 'between:1,12'],
            'Nam' => ['required', 'integer', 'min:2000', 'max:2100'],
            'LuongCoSo' => ['nullable', 'numeric'],
            'HeSoLuong' => ['nullable', 'numeric'],
            'HeSoChucVu' => ['nullable', 'numeric'],
            'PhuCap' => ['nullable', 'numeric'],
            'Thuong' => ['nullable', 'numeric'],
            'Phat' => ['nullable', 'numeric'],
            'BaoHiem' => ['nullable', 'numeric'],
            'TongLuong' => ['nullable', 'numeric'],
            'TrangThai' => ['required', 'string', 'max:20'],
            'NgayTinh' => ['nullable', 'date'],
        ]);
    }

    private function normalizePayload(array $payload): array
    {
        if (array_key_exists('TongLuong', $payload) && $payload['TongLuong'] !== null && $payload['TongLuong'] !== '') {
            $payload['TongLuong'] = round((float) $payload['TongLuong'], 0);
        }

        return $payload;
    }

    private function normalizePayrollListRow(array $row): array
    {
        $totalSalary = (float) ($row['TongLuong'] ?? 0);

        return [
            'MaBL' => (int) ($row['MaBL'] ?? 0),
            'MaNV' => (int) ($row['MaNV'] ?? 0),
            'HoTen' => (string) ($row['HoTen'] ?? ''),
            'Thang' => (int) ($row['Thang'] ?? 0),
            'Nam' => (int) ($row['Nam'] ?? 0),
            'TongLuong' => $totalSalary,
            'ThucNhan' => $totalSalary,
            'TrangThai' => (string) ($row['TrangThai'] ?? 'Chưa chốt'),
        ];
    }
}