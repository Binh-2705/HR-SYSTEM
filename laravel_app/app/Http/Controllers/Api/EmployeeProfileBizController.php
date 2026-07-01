<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeProfileBizController extends Controller
{
    private function conn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    public function show(int $id): JsonResponse
    {
        $row = DB::connection($this->conn())
            ->table('hosonhanvien as hs')
            ->leftJoin('nhanvien as nv', 'hs.MaNV', '=', 'nv.MaNV')
            ->leftJoin('phongban as pb', 'hs.MaPB', '=', 'pb.MaPB')
            ->leftJoin('chucvu as cv', 'hs.MaCV', '=', 'cv.MaCV')
            ->where('hs.MaHoSo', $id)
            ->select('hs.*', 'nv.HoTen', 'pb.TenPB', 'cv.TenCV')
            ->first();

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Hồ sơ không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (array) $row]);
    }

    public function pendingRequests(): JsonResponse
    {
        $rows = DB::connection($this->conn())
            ->table('hoso_update_requests as r')
            ->leftJoin('nhanvien as nv', 'r.MaNV', '=', 'nv.MaNV')
            ->where('r.status_name', 'pending')
            ->orderByDesc('r.created_at')
            ->select('r.*', 'nv.HoTen', 'nv.Email', 'nv.DienThoai')
            ->get()
            ->map(function ($row) {
                $record = (array) $row;
                $record['payload'] = json_decode((string) ($record['payload_json'] ?? '{}'), true) ?: [];
                return $record;
            })->all();

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function resolveRequest(Request $request, int $requestId): JsonResponse
    {
        $payload    = (array) $request->json()->all();
        $decision   = (string) ($payload['decision'] ?? '');
        $reviewedBy = (int) ($payload['reviewed_by'] ?? 0);
        $reviewNote = (string) ($payload['review_note'] ?? '');

        if (!in_array($decision, ['approve', 'reject'], true)) {
            return response()->json(['ok' => false, 'message' => 'Quyết định không hợp lệ.'], 422);
        }

        DB::connection($this->conn())->transaction(function () use ($requestId, $decision, $reviewedBy, $reviewNote) {
            $req = DB::connection($this->conn())->table('hoso_update_requests')
                ->where('id', $requestId)->lockForUpdate()->first();

            if (!$req || (string) $req->status_name !== 'pending') {
                throw new \LogicException('Yêu cầu không tồn tại hoặc đã xử lý.');
            }

            if ($decision === 'approve') {
                $p = json_decode((string) ($req->payload_json ?? '{}'), true) ?: [];
                DB::connection($this->conn())->table('hosonhanvien')->where('MaNV', (int) $req->MaNV)->update([
                    'CCCD'              => $p['CCCD'] ?? null,
                    'NoiCap'            => $p['NoiCap'] ?? null,
                    'NgayCap'           => $p['NgayCap'] ?? null,
                    'DiaChi'            => $p['DiaChi'] ?? null,
                    'DanToc'            => $p['DanToc'] ?? null,
                    'TonGiao'           => $p['TonGiao'] ?? null,
                    'TrinhDo'           => $p['TrinhDo'] ?? null,
                    'ChuyenMon'         => $p['ChuyenMon'] ?? null,
                    'TrangThaiHonNhan'  => $p['TrangThaiHonNhan'] ?? null,
                ]);
            }

            DB::connection($this->conn())->table('hoso_update_requests')->where('id', $requestId)->update([
                'status_name' => $decision === 'approve' ? 'approved' : 'rejected',
                'reviewed_by' => $reviewedBy,
                'review_note' => $reviewNote,
                'reviewed_at' => now(),
                'updated_at'  => now(),
            ]);
        });

        return response()->json(['ok' => true]);
    }

    public function employeeInfo(int $employeeId): JsonResponse
    {
        $row = DB::connection($this->conn())
            ->table('phancong as pc')
            ->leftJoin('phongban as pb', 'pc.MaPB', '=', 'pb.MaPB')
            ->leftJoin('chucvu as cv', 'pc.MaCV', '=', 'cv.MaCV')
            ->where('pc.MaNV', $employeeId)
            ->orderByDesc('pc.NgayBatDau')
            ->select('pc.MaNV', 'pb.MaPB', 'pb.TenPB', 'cv.MaCV', 'cv.TenCV')
            ->first();

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Nhân viên không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (array) $row]);
    }
}
