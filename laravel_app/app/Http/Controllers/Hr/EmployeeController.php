<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

use App\Services\HrEmployeeService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function __construct(private HrEmployeeService $employeeService)
    {
    }

    public function index(Request $request): View
    {
        $account = (array) session('taikhoan', []);
        $role = strtolower(trim((string) ($account['VaiTro'] ?? '')));
        $ownMaNV = (int) ($account['MaNV'] ?? 0);

        // Nhân viên chỉ có thể xem hồ sơ của chính mình
        if ($role === 'nhanvien' && $ownMaNV > 0) {
            $employees = $this->employeeService->paginate(['ma_nv' => $ownMaNV]);
        } else {
            $employees = $this->employeeService->paginate($request->only(['q', 'status', 'department']));
        }
        /** @var LengthAwarePaginator $employees */
        $employees->setCollection(
            $employees->getCollection()->map(fn ($employee) => (object) $this->normalizeEmployeeListRow((array) $employee))
        );
        $employees->appends($request->query());

        return view('nhanvien.index', [
            'employees' => $employees,
            'filters' => $request->only(['q', 'status', 'department']),
            'options' => $this->employeeService->options(),
            'isSelfView' => $role === 'nhanvien',
        ]);
    }

    public function create(): View
    {
        return view('nhanvien.form', [
            'mode' => 'create',
            'employee' => null,
            'options' => $this->employeeService->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatePayload($request);

        try {
            $employeeData = [
                'HoTen' => $payload['HoTen'],
                'GioiTinh' => $payload['GioiTinh'] ?? null,
                'NgaySinh' => $payload['NgaySinh'] ?? null,
                'Email' => $payload['Email'] ?? null,
                'DienThoai' => $payload['DienThoai'] ?? null,
                'TrangThai' => $payload['TrangThai'],
                'MaBac' => $payload['MaBac'] ?? null,
                'MaHS' => null,
            ];

            $profileData = [
                'MaPB' => $payload['MaPB'] ?? null,
                'MaCV' => $payload['MaCV'] ?? null,
                'NgayVaoLam' => $payload['NgayVaoLam'] ?? null,
                'DiaChi' => $payload['DiaChi'] ?? null,
            ];

            $employeeId = 0;
            DB::transaction(function () use ($employeeData, $profileData, &$employeeId): void {
                $conn = config('service_registry.services.hr.connection', config('database.default'));

                $employeeId = (int) DB::connection($conn)
                    ->table('nhanvien')
                    ->insertGetId($employeeData, 'MaNV');

                $profilePayload = array_filter([
                    'MaNV' => $employeeId,
                    'MaPB' => $profileData['MaPB'],
                    'MaCV' => $profileData['MaCV'],
                    'NgayVaoLam' => $profileData['NgayVaoLam'],
                    'DiaChi' => $profileData['DiaChi'],
                ], fn($value) => $value !== null && $value !== '');

                if (count($profilePayload) > 1) {
                    $profileId = (int) DB::connection($conn)
                        ->table('hosonhanvien')
                        ->insertGetId($profilePayload, 'MaHoSo');

                    DB::connection($conn)
                        ->table('nhanvien')
                        ->where('MaNV', $employeeId)
                        ->update(['MaHS' => $profileId]);
                }
            });

            return redirect()->route('nhanvien.edit', ['employee' => $employeeId])
                ->with('success', 'Đã tạo nhân viên thành công.');
        } catch (QueryException $exception) {
            return back()->withInput()->withErrors(['form' => 'Không thể tạo nhân viên: ' . $exception->getMessage()]);
        }
    }

    public function edit(int $employee): View
    {
        $account = (array) session('taikhoan', []);
        $role = strtolower(trim((string) ($account['VaiTro'] ?? '')));
        $ownMaNV = (int) ($account['MaNV'] ?? 0);

        // Nhân viên chỉ có thể chỉnh sửa thông tin của chính mình
        if ($role === 'nhanvien' && $ownMaNV !== $employee) {
            abort(403, 'Ban chi co the chinh sua thong tin cua chinh minh.');
        }

        $item = $this->employeeService->find($employee);
        abort_if($item === null, 404);

        return view('nhanvien.form', [
            'mode' => 'edit',
            'employee' => $item,
            'options' => $this->employeeService->options(),
        ]);
    }

    public function update(Request $request, int $employee): RedirectResponse
    {
        $account = (array) session('taikhoan', []);
        $role = strtolower(trim((string) ($account['VaiTro'] ?? '')));
        $ownMaNV = (int) ($account['MaNV'] ?? 0);

        if ($role === 'nhanvien' && $ownMaNV !== $employee) {
            abort(403);
        }

        $payload = $this->validatePayload($request);

        try {
            $this->employeeService->update($employee, $payload);

            return redirect()->route('nhanvien.edit', ['employee' => $employee])
                ->with('success', 'Đã cập nhật nhân viên thành công.');
        } catch (QueryException $exception) {
            return back()->withInput()->withErrors(['form' => 'Không thể cập nhật nhân viên: ' . $exception->getMessage()]);
        }
    }

    public function destroy(int $employee): RedirectResponse
    {
        try {
            $this->employeeService->delete($employee);

            return redirect()->route('nhanvien.index')
                ->with('success', 'Đã xóa nhân viên thành công.');
        } catch (\RuntimeException $exception) {
            $message = 'Không thể xóa nhân viên do còn dữ liệu liên quan.';

            if (preg_match('/HTTP \d+: (.+)$/', $exception->getMessage(), $matches)) {
                $message = (string) $matches[1];
            }

            return back()->withErrors(['form' => $message]);
        } catch (QueryException $exception) {
            return back()->withErrors(['form' => 'Không thể xóa nhân viên do còn dữ liệu liên quan.']);
        }
    }

    public function destroyLegacy(int $employee): RedirectResponse
    {
        return $this->destroy($employee);
    }

    public function salaryGradesByBand(Request $request): Response
    {
        $bandId = $request->query('ma_ngach');
        $grades = $bandId !== null && $bandId !== ''
            ? $this->employeeService->salaryGradesByBand($bandId)
            : [];

        $html = '<option value="">-- Chọn bậc lương --</option>';

        if (empty($grades)) {
            $html .= '<option value="">Chưa có bậc lương cho ngạch này</option>';
        } else {
            foreach ($grades as $grade) {
                $grade = is_array($grade) ? (object) $grade : $grade;
                $html .= '<option value="' . e((string) $grade->MaBac) . '">'
                    . e((string) $grade->TenBac) . ' (HS: ' . e((string) $grade->HeSoLuong) . ')'
                    . '</option>';
            }
        }

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'HoTen' => ['required', 'string', 'max:100'],
            'GioiTinh' => ['nullable', 'in:Nam,Nữ'],
            'NgaySinh' => ['nullable', 'date'],
            'Email' => ['nullable', 'email', 'max:100'],
            'DienThoai' => ['nullable', 'string', 'max:20'],
            'TrangThai' => ['required', 'in:Đang làm,Nghỉ'],
            'MaBac' => ['nullable', 'integer'],
            'MaPB' => ['nullable', 'integer'],
            'MaCV' => ['nullable', 'integer'],
            'NgayVaoLam' => ['nullable', 'date'],
            'DiaChi' => ['nullable', 'string'],
        ]);
    }

    private function normalizeEmployeeListRow(array $row): array
    {
        return [
            'MaNV' => (int) ($row['MaNV'] ?? 0),
            'HoTen' => (string) ($row['HoTen'] ?? ''),
            'GioiTinh' => (string) ($row['GioiTinh'] ?? ''),
            'NgaySinh' => $row['NgaySinh'] ?? null,
            'Email' => (string) ($row['Email'] ?? ''),
            'DienThoai' => (string) ($row['DienThoai'] ?? ''),
            'TenBac' => (string) ($row['TenBac'] ?? ''),
            'TenPB' => (string) ($row['TenPB'] ?? ''),
            'TrangThai' => (string) ($row['TrangThai'] ?? ''),
        ];
    }
}