<?php

namespace App\Jobs;

use App\Events\MonthlyPayrollProcessed;
use App\Services\PayrollService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessMonthlyPayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Số lần thử lại nếu job thất bại.
     */
    public int $tries = 2;

    /**
     * Timeout tối đa (giây) — tính lương 500 NV mất khoảng 30s.
     */
    public int $timeout = 300;

    public int $month;
    public int $year;
    public int $triggeredBy;

    public function __construct(int $month, int $year, int $triggeredBy = 0)
    {
        $this->month       = $month;
        $this->year        = $year;
        $this->triggeredBy = $triggeredBy;
    }

    public function handle(PayrollService $payrollService): void
    {
        $cacheKey = "payroll_job_status_{$this->month}_{$this->year}";

        Cache::put($cacheKey, [
            'status'       => 'processing',
            'month'        => $this->month,
            'year'         => $this->year,
            'started_at'   => now()->toDateTimeString(),
            'triggered_by' => $this->triggeredBy,
        ], 3600);

        try {
            $count = $payrollService->processMonthlyPayroll($this->month, $this->year);

            Cache::put($cacheKey, [
                'status'       => 'done',
                'month'        => $this->month,
                'year'         => $this->year,
                'processed'    => $count,
                'finished_at'  => now()->toDateTimeString(),
                'triggered_by' => $this->triggeredBy,
            ], 3600);

            Log::info('ProcessMonthlyPayrollJob: xong', [
                'month'     => $this->month,
                'year'      => $this->year,
                'processed' => $count,
            ]);

            event(new MonthlyPayrollProcessed(
                $this->month,
                $this->year,
                $count,
                $this->triggeredBy
            ));
        } catch (\Throwable $e) {
            Cache::put($cacheKey, [
                'status'    => 'failed',
                'month'     => $this->month,
                'year'      => $this->year,
                'error'     => $e->getMessage(),
                'failed_at' => now()->toDateTimeString(),
            ], 3600);

            Log::error('ProcessMonthlyPayrollJob: lỗi', [
                'month' => $this->month,
                'year'  => $this->year,
                'error' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessMonthlyPayrollJob: thất bại sau ' . $this->tries . ' lần thử', [
            'month' => $this->month,
            'year'  => $this->year,
            'error' => $e->getMessage(),
        ]);
    }
}
