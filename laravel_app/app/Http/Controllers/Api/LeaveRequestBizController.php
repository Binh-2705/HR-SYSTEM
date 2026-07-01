<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaveRequestApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveRequestBizController extends Controller
{
    public function __construct(
        private LeaveRequestApprovalService $approvalService,
    ) {}

    private function conn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->approvalService->approve($id, $request);

            return response()->json($result, (int) ($result['http_status'] ?? (($result['ok'] ?? false) ? 200 : 422)));
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'Không thể duyệt đơn nghỉ phép.', 'http_status' => 500], 500);
        }
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->approvalService->reject($id, $request);

            return response()->json($result, (int) ($result['http_status'] ?? (($result['ok'] ?? false) ? 200 : 422)));
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'Không thể từ chối đơn nghỉ phép.', 'http_status' => 500], 500);
        }
    }

    public function approvalProgress(int $id): JsonResponse
    {
        return response()->json($this->approvalService->progress($id));
    }

    public function create(int $employeeId, string $startDate, string $endDate, string $reason, string $leaveType): JsonResponse
    {
        try {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'Ngày nghỉ phép không hợp lệ.'], 422);
        }

        if ($end < $start) {
            return response()->json(['ok' => false, 'message' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.'], 422);
        }

        $days = (int) $start->diff($end)->days + 1;
        $newId = DB::connection($this->conn())->table('nghiphep')->insertGetId([
            'MaNV' => $employeeId,
            'TuNgay' => $startDate,
            'DenNgay' => $endDate,
            'SoNgayNghi' => $days,
            'LyDo' => $reason,
            'LoaiNghi' => $leaveType,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Đã tạo đơn nghỉ phép.',
            'id' => $newId,
            'state_diff' => 'Mã đơn mới: #' . $newId . ' | Trạng thái: Chờ duyệt',
        ]);
    }
}
