<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeaveBalanceService
{
    /**
     * @param array<string, mixed> $leave
     * @return array{ok: bool, deducted: bool, remaining_days: int|null, message?: string}
     */
    public function deductForApprovedLeave($connection, array $leave): array
    {
        $leaveType = trim((string) ($leave['LoaiNghi'] ?? ''));
        if (!$this->isDeductibleType($leaveType)) {
            return [
                'ok' => true,
                'deducted' => false,
                'remaining_days' => null,
            ];
        }

        $employeeId = (int) ($leave['MaNV'] ?? 0);
        if ($employeeId <= 0) {
            return [
                'ok' => false,
                'deducted' => false,
                'remaining_days' => null,
                'message' => 'Không thể trừ quỹ phép: thiếu mã nhân viên.',
            ];
        }

        $leaveDays = $this->resolveLeaveDays($leave);
        if ($leaveDays <= 0) {
            return [
                'ok' => false,
                'deducted' => false,
                'remaining_days' => null,
                'message' => 'Không thể trừ quỹ phép: số ngày nghỉ không hợp lệ.',
            ];
        }

        if (!Schema::connection((string) $connection->getName())->hasTable('leave_balances')) {
            return [
                'ok' => false,
                'deducted' => false,
                'remaining_days' => null,
                'message' => 'Không thể trừ quỹ phép: thiếu bảng leave_balances, vui lòng chạy migrate.',
            ];
        }

        $year = $this->resolveYear($leave);
        $defaultEntitled = max(0, (int) config('approval_workflows.leave_requests.leave_balance.default_entitled_days', 12));

        if ($connection->table('leave_balances')->where(['MaNV' => $employeeId, 'Nam' => $year])->doesntExist()) {
            $connection->table('leave_balances')->insert([
                'MaNV' => $employeeId,
                'Nam' => $year,
                'EntitledDays' => $defaultEntitled,
                'UsedDays' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $balance = $connection->table('leave_balances')
            ->where('MaNV', $employeeId)
            ->where('Nam', $year)
            ->lockForUpdate()
            ->first();

        if ($balance === null) {
            return [
                'ok' => false,
                'deducted' => false,
                'remaining_days' => null,
                'message' => 'Không thể trừ quỹ phép: chưa thiết lập số dư phép.',
            ];
        }

        $entitled = max(0, (int) ($balance->EntitledDays ?? 0));
        $used = max(0, (int) ($balance->UsedDays ?? 0));
        $remaining = max(0, $entitled - $used);

        if ($leaveDays > $remaining) {
            return [
                'ok' => false,
                'deducted' => false,
                'remaining_days' => $remaining,
                'message' => "Số ngày phép năm còn lại không đủ. Còn {$remaining} ngày, cần {$leaveDays} ngày.",
            ];
        }

        $newUsed = $used + $leaveDays;
        $connection->table('leave_balances')
            ->where('MaNV', $employeeId)
            ->where('Nam', $year)
            ->update([
                'UsedDays' => $newUsed,
                'updated_at' => now(),
            ]);

        return [
            'ok' => true,
            'deducted' => true,
            'remaining_days' => max(0, $entitled - $newUsed),
        ];
    }

    public function isDeductibleType(string $leaveType): bool
    {
        $configured = (array) config('approval_workflows.leave_requests.leave_balance.deductible_types', ['Nghỉ phép năm']);
        $configured = array_map(fn ($item) => $this->normalizeText((string) $item), $configured);

        return in_array($this->normalizeText($leaveType), $configured, true);
    }

    /**
     * @param array<string, mixed> $leave
     */
    private function resolveLeaveDays(array $leave): int
    {
        $days = (int) ($leave['SoNgayNghi'] ?? 0);
        if ($days > 0) {
            return $days;
        }

        $start = strtotime((string) ($leave['TuNgay'] ?? ''));
        $end = strtotime((string) ($leave['DenNgay'] ?? ''));

        if ($start === false || $end === false || $end < $start) {
            return 0;
        }

        return (int) floor(($end - $start) / 86400) + 1;
    }

    /**
     * @param array<string, mixed> $leave
     */
    private function resolveYear(array $leave): int
    {
        $start = (string) ($leave['TuNgay'] ?? '');
        $timestamp = strtotime($start);

        return (int) ($timestamp ? date('Y', $timestamp) : date('Y'));
    }

    private function normalizeText(string $value): string
    {
        $ascii = Str::ascii($value);
        $collapsed = preg_replace('/[^a-z0-9]+/i', '', strtolower($ascii));

        return $collapsed === null ? '' : $collapsed;
    }
}
