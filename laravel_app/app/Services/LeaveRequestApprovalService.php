<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeaveRequestApprovalService
{
    public function __construct(
        private LeaveBalanceService $leaveBalanceService,
    ) {}

    private function conn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    /**
     * @return array{ok: bool, message: string, http_status: int, finalized?: bool, pending?: bool, approvals_count?: int, required_approvals?: int, state_diff?: string}
     */
    public function approve(int $leaveId, Request $request): array
    {
        $connection = DB::connection($this->conn());

        return $connection->transaction(function () use ($connection, $leaveId, $request) {
            $leave = $connection->table('nghiphep')->where('MaNP', $leaveId)->lockForUpdate()->first();
            if ($leave === null) {
                return $this->fail('Không tìm thấy đơn nghỉ phép.', 404);
            }

            $status = trim((string) ($leave->TrangThai ?? ''));
            if ($status !== 'Chờ duyệt') {
                return $this->fail('Đơn nghỉ phép không còn ở trạng thái Chờ duyệt.', 422);
            }

            [$actorId, $actorRole] = $this->resolveActor($request);
            if ($actorId <= 0) {
                return $this->fail('Thiếu thông tin người duyệt.', 422);
            }

            if (!$this->isRoleAllowed($actorRole)) {
                return $this->fail('Vai trò hiện tại không có quyền duyệt đa cấp cho đơn nghỉ phép.', 403);
            }

            $alreadyApproved = $connection->table('leave_request_approval_actions')
                ->where('MaNP', $leaveId)
                ->where('MaTK', $actorId)
                ->where('ActionName', 'approved')
                ->exists();

            if (!$alreadyApproved) {
                $connection->table('leave_request_approval_actions')->insert([
                    'MaNP' => $leaveId,
                    'MaTK' => $actorId,
                    'ApproverRole' => $actorRole !== '' ? $actorRole : null,
                    'ActionName' => 'approved',
                    'Note' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $approvalCount = $this->approvedCount($connection, $leaveId);
            $requiredApprovals = $this->requiredApprovals();

            if ($approvalCount < $requiredApprovals) {
                return [
                    'ok' => true,
                    'pending' => true,
                    'finalized' => false,
                    'approvals_count' => $approvalCount,
                    'required_approvals' => $requiredApprovals,
                    'message' => "Đã ghi nhận phê duyệt {$approvalCount}/{$requiredApprovals}. Đơn sẽ được duyệt khi đủ số bước.",
                    'http_status' => 200,
                    'state_diff' => 'Trạng thái: Chờ duyệt (đang chờ đủ phê duyệt)',
                ];
            }

            $leaveRow = (array) $leave;
            $balanceResult = $this->leaveBalanceService->deductForApprovedLeave($connection, $leaveRow);
            if (!($balanceResult['ok'] ?? false)) {
                return $this->fail((string) ($balanceResult['message'] ?? 'Không thể cập nhật quỹ phép năm.'), 422);
            }

            $connection->table('nghiphep')->where('MaNP', $leaveId)->update([
                'TrangThai' => 'Đã duyệt',
                'NgayDuyet' => now()->toDateString(),
            ]);

            $cursor = strtotime((string) $leave->TuNgay);
            $end = strtotime((string) $leave->DenNgay);
            while ($cursor !== false && $end !== false && $cursor <= $end) {
                $date = date('Y-m-d', $cursor);
                $connection->table('chamcong')->updateOrInsert(
                    ['MaNV' => (int) $leave->MaNV, 'Ngay' => $date],
                    ['TrangThai' => 'Nghi phep', 'GioVao' => null, 'GioRa' => null]
                );
                $cursor = strtotime('+1 day', $cursor);
            }

            return [
                'ok' => true,
                'pending' => false,
                'finalized' => true,
                'approvals_count' => $approvalCount,
                'required_approvals' => $requiredApprovals,
                'message' => $this->buildApprovalSuccessMessage($balanceResult),
                'http_status' => 200,
                'state_diff' => 'Trạng thái: Chờ duyệt -> Đã duyệt',
            ];
        });
    }

    /**
     * @return array{ok: bool, message: string, http_status: int, state_diff?: string}
     */
    public function reject(int $leaveId, Request $request): array
    {
        $connection = DB::connection($this->conn());

        return $connection->transaction(function () use ($connection, $leaveId, $request) {
            $leave = $connection->table('nghiphep')->where('MaNP', $leaveId)->lockForUpdate()->first();
            if ($leave === null) {
                return $this->fail('Không tìm thấy đơn nghỉ phép.', 404);
            }

            $status = trim((string) ($leave->TrangThai ?? ''));
            if ($status !== 'Chờ duyệt') {
                return $this->fail('Đơn nghỉ phép không còn ở trạng thái Chờ duyệt.', 422);
            }

            [$actorId, $actorRole] = $this->resolveActor($request);
            if ($actorId <= 0) {
                return $this->fail('Thiếu thông tin người từ chối.', 422);
            }

            if (!$this->isRoleAllowed($actorRole)) {
                return $this->fail('Vai trò hiện tại không có quyền từ chối đơn nghỉ phép.', 403);
            }

            $connection->table('leave_request_approval_actions')->insert([
                'MaNP' => $leaveId,
                'MaTK' => $actorId,
                'ApproverRole' => $actorRole !== '' ? $actorRole : null,
                'ActionName' => 'rejected',
                'Note' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $connection->table('nghiphep')->where('MaNP', $leaveId)->update([
                'TrangThai' => 'Từ chối',
                'NgayDuyet' => now()->toDateString(),
            ]);

            return [
                'ok' => true,
                'message' => 'Đã từ chối đơn nghỉ phép.',
                'http_status' => 200,
                'state_diff' => 'Trạng thái: Chờ duyệt -> Từ chối',
            ];
        });
    }

    /**
     * @return array{ok: bool, approvals_count: int, required_approvals: int}
     */
    public function progress(int $leaveId): array
    {
        $connection = DB::connection($this->conn());

        return [
            'ok' => true,
            'approvals_count' => $this->approvedCount($connection, $leaveId),
            'required_approvals' => $this->requiredApprovals(),
        ];
    }

    private function requiredApprovals(): int
    {
        return max(1, (int) config('approval_workflows.leave_requests.required_approvals', 2));
    }

    private function isRoleAllowed(string $role): bool
    {
        $allowed = (array) config('approval_workflows.leave_requests.allowed_roles', ['admin', 'quanly']);
        $allowed = array_map(fn ($item) => $this->normalizeRole((string) $item), $allowed);

        return in_array($this->normalizeRole($role), $allowed, true);
    }

    private function normalizeRole(string $role): string
    {
        $ascii = Str::ascii($role);

        return strtolower((string) preg_replace('/[^a-z0-9]/', '', $ascii));
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function resolveActor(Request $request): array
    {
        $actorId = (int) ($request->header('X-Account-Id') ?? $request->input('actor_id', 0));
        $actorRole = trim((string) ($request->header('X-Account-Role') ?? $request->input('actor_role', '')));

        return [$actorId, $actorRole];
    }

    private function approvedCount($connection, int $leaveId): int
    {
        return (int) $connection->table('leave_request_approval_actions')
            ->where('MaNP', $leaveId)
            ->where('ActionName', 'approved')
            ->distinct('MaTK')
            ->count('MaTK');
    }

    /**
     * @param array{deducted?: bool, remaining_days?: int|null} $balanceResult
     */
    private function buildApprovalSuccessMessage(array $balanceResult): string
    {
        if (!($balanceResult['deducted'] ?? false)) {
            return 'Đã duyệt đơn nghỉ phép.';
        }

        $remaining = $balanceResult['remaining_days'] ?? null;
        if (!is_int($remaining)) {
            return 'Đã duyệt đơn nghỉ phép và cập nhật quỹ phép năm.';
        }

        return "Đã duyệt đơn nghỉ phép và trừ quỹ phép năm. Còn lại {$remaining} ngày.";
    }

    /**
     * @return array{ok: false, message: string, http_status: int}
     */
    private function fail(string $message, int $status): array
    {
        return ['ok' => false, 'message' => $message, 'http_status' => $status];
    }
}
