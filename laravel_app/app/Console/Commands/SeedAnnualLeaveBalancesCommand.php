<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedAnnualLeaveBalancesCommand extends Command
{
    protected $signature = 'leave:seed-annual-balances
        {--year= : Year to seed balances for (default: current year)}
        {--default-days= : Default entitled leave days (override config)}
        {--force-reset : Reset EntitledDays to default for existing rows (UsedDays is preserved)}';

    protected $description = 'Create annual leave balance rows for employees who do not have one yet';

    public function handle(): int
    {
        $connection = (string) config('service_registry.services.hr.connection', config('database.default'));
        $year = (int) ($this->option('year') ?: date('Y'));
        $defaultDays = (int) ($this->option('default-days') ?: config('approval_workflows.leave_requests.leave_balance.default_entitled_days', 12));
        $forceReset = (bool) $this->option('force-reset');

        if ($year < 2000 || $year > 2100) {
            $this->error('Năm không hợp lệ. Vui lòng dùng khoảng 2000-2100.');

            return self::INVALID;
        }

        if ($defaultDays < 0) {
            $this->error('Số ngày phép mặc định phải lớn hơn hoặc bằng 0.');

            return self::INVALID;
        }

        if (!Schema::connection($connection)->hasTable('leave_balances')) {
            $this->error('Không tìm thấy bảng leave_balances. Vui lòng chạy migrate trước.');

            return self::FAILURE;
        }

        if (!Schema::connection($connection)->hasTable('nhanvien')) {
            $this->error('Không tìm thấy bảng nhanvien trong HR service.');

            return self::FAILURE;
        }

        $employeeIds = DB::connection($connection)
            ->table('nhanvien')
            ->pluck('MaNV')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        if ($employeeIds->isEmpty()) {
            $this->warn('Không có nhân viên nào để khởi tạo quỹ phép.');

            return self::SUCCESS;
        }

        $existingRows = DB::connection($connection)
            ->table('leave_balances')
            ->where('Nam', $year)
            ->whereIn('MaNV', $employeeIds->all())
            ->get(['id', 'MaNV', 'EntitledDays', 'UsedDays']);

        $existingByEmployee = $existingRows->keyBy(fn ($row) => (int) $row->MaNV);

        $rowsToInsert = [];
        foreach ($employeeIds as $employeeId) {
            if ($existingByEmployee->has($employeeId)) {
                continue;
            }

            $rowsToInsert[] = [
                'MaNV' => $employeeId,
                'Nam' => $year,
                'EntitledDays' => $defaultDays,
                'UsedDays' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $inserted = 0;
        if ($rowsToInsert !== []) {
            DB::connection($connection)->table('leave_balances')->insert($rowsToInsert);
            $inserted = count($rowsToInsert);
        }

        $updated = 0;
        if ($forceReset && $existingRows->isNotEmpty()) {
            foreach ($existingRows as $row) {
                $used = max(0, (int) ($row->UsedDays ?? 0));
                $entitled = max($defaultDays, $used);

                if ((int) ($row->EntitledDays ?? 0) === $entitled) {
                    continue;
                }

                DB::connection($connection)
                    ->table('leave_balances')
                    ->where('id', (int) $row->id)
                    ->update([
                        'EntitledDays' => $entitled,
                        'updated_at' => now(),
                    ]);
                $updated++;
            }
        }

        $total = DB::connection($connection)
            ->table('leave_balances')
            ->where('Nam', $year)
            ->count();

        $this->info("Đã khởi tạo quỹ phép năm {$year}: thêm mới {$inserted}, cập nhật {$updated}, tổng bản ghi {$total}.");

        return self::SUCCESS;
    }
}
