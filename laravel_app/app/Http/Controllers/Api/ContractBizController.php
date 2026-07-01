<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractBizController extends Controller
{
    private function payrollConn(): string
    {
        return (string) config('service_registry.services.payroll.connection', config('database.default'));
    }

    private function hrConn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    public function show(int $id): JsonResponse
    {
        $contract = DB::connection($this->payrollConn())->table('hopdong')->where('MaHopDong', $id)->first();

        if (!$contract) {
            return response()->json(['ok' => false, 'message' => 'Hợp đồng không tồn tại.'], 404);
        }

        $row = (array) $contract;

        $employee = DB::connection($this->hrConn())->table('nhanvien')->where('MaNV', (int) $contract->MaNV)->first(['MaNV', 'HoTen']);
        $salaryGrade = DB::connection($this->payrollConn())->table('bacluong')->where('MaBac', (int) $contract->MaBac)->first(['MaBac', 'TenBac', 'HeSoLuong', 'LuongCoSo']);

        $row['HoTen'] = (string) ($employee->HoTen ?? '');
        $row['TenBac'] = (string) ($salaryGrade->TenBac ?? '');
        $row['HeSoLuong'] = (float) ($salaryGrade->HeSoLuong ?? 0);
        $row['LuongCoSo'] = (float) ($salaryGrade->LuongCoSo ?? 0);
        $row['LuongThucTe'] = $row['HeSoLuong'] * $row['LuongCoSo'];

        return response()->json(['ok' => true, 'data' => $row]);
    }

    public function salaryHistory(int $id): JsonResponse
    {
        $rows = DB::connection($this->payrollConn())
            ->table('lichsu_luong')
            ->where('MaHopDong', $id)
            ->orderByDesc('NgayApDung')
            ->get()->map(fn ($r) => (array) $r)->all();

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function renew(Request $request, int $id): JsonResponse
    {
        $payload = (array) $request->json()->all();

        // Validate uniqueness of SoHopDong
        $exists = DB::connection($this->payrollConn())->table('hopdong')->where('SoHopDong', $payload['SoHopDong'])->exists();
        if ($exists) {
            return response()->json(['ok' => false, 'message' => 'Số hợp đồng đã tồn tại.'], 422);
        }

        $current = DB::connection($this->payrollConn())->table('hopdong')->where('MaHopDong', $id)->first();
        if (!$current) {
            return response()->json(['ok' => false, 'message' => 'Hợp đồng không tồn tại.'], 404);
        }

        if (empty($payload['NgayBatDau']) || !strtotime((string) $payload['NgayBatDau'])) {
            return response()->json(['ok' => false, 'message' => 'Ngày bắt đầu không hợp lệ.'], 422);
        }

        if (!empty($payload['NgayKetThuc'])) {
            if (!strtotime((string) $payload['NgayKetThuc'])) {
                return response()->json(['ok' => false, 'message' => 'Ngày kết thúc không hợp lệ.'], 422);
            }
            if (strtotime((string) $payload['NgayKetThuc']) < strtotime((string) $payload['NgayBatDau'])) {
                return response()->json(['ok' => false, 'message' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.'], 422);
            }
        }

        DB::connection($this->payrollConn())->transaction(function () use ($id, $current, $payload) {
            $conn = DB::connection($this->payrollConn());

            $conn->table('hopdong')->where('MaHopDong', $id)->update([
                'TrangThai'   => 'Hết hiệu lực',
                'NgayKetThuc' => now()->toDateString(),
            ]);

            $conn->table('hopdong')->insert([
                'HopDongGoc'  => $id,
                'SoHopDong'   => $payload['SoHopDong'],
                'MaNV'        => (int) $current->MaNV,
                'MaBac'       => (int) $current->MaBac,
                'LoaiHopDong' => $payload['LoaiHopDong'],
                'NgayKy'      => now()->toDateString(),
                'NgayBatDau'  => $payload['NgayBatDau'],
                'NgayKetThuc' => $payload['NgayKetThuc'] ?? null,
                'TrangThai'   => 'Còn hiệu lực',
                'GhiChu'      => $payload['GhiChu'] ?? null,
            ]);
        });

        return response()->json(['ok' => true]);
    }

    public function terminate(int $id): JsonResponse
    {
        $affected = DB::connection($this->payrollConn())
            ->table('hopdong')
            ->where('MaHopDong', $id)
            ->where('TrangThai', '<>', 'Hết hiệu lực')
            ->update([
                'TrangThai'   => 'Hết hiệu lực',
                'NgayKetThuc' => now()->toDateString(),
            ]);

        return response()->json(['ok' => true, 'affected' => $affected]);
    }
}
