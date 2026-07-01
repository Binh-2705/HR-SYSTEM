<?php

namespace Tests\Unit;

use App\Events\MonthlyPayrollProcessed;
use App\Jobs\ProcessMonthlyPayrollJob;
use App\Services\PayrollService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessMonthlyPayrollJobTest extends TestCase
{
    public function test_job_dispatches_to_payroll_queue(): void
    {
        Queue::fake();

        ProcessMonthlyPayrollJob::dispatch(4, 2026, 1)->onQueue('payroll');

        Queue::assertPushedOn('payroll', ProcessMonthlyPayrollJob::class, function ($job) {
            return $job->month === 4 && $job->year === 2026 && $job->triggeredBy === 1;
        });
    }

    public function test_job_handle_updates_cache_on_success(): void
    {
        Event::fake();

        Cache::shouldReceive('put')->twice(); // processing + done

        $payrollService = $this->createMock(PayrollService::class);
        $payrollService->expects($this->once())
            ->method('processMonthlyPayroll')
            ->with(4, 2026)
            ->willReturn(100);

        Log::shouldReceive('info')->once();

        $job = new ProcessMonthlyPayrollJob(4, 2026, 1);
        $job->handle($payrollService);

        Event::assertDispatched(MonthlyPayrollProcessed::class, function (MonthlyPayrollProcessed $event): bool {
            return $event->month === 4
                && $event->year === 2026
                && $event->processed === 100
                && $event->triggeredBy === 1;
        });
    }

    public function test_job_handle_updates_cache_on_failure(): void
    {
        Cache::shouldReceive('put')->twice(); // processing + failed

        $payrollService = $this->createMock(PayrollService::class);
        $payrollService->expects($this->once())
            ->method('processMonthlyPayroll')
            ->willThrowException(new \RuntimeException('DB error'));

        Log::shouldReceive('error')->once();

        $job = new ProcessMonthlyPayrollJob(4, 2026, 0);
        // $this->fail($e) não lança exceção — apenas marca o job como falho.
        // Verificamos apenas que o método não lança exceção não tratada.
        $job->handle($payrollService);
        $this->assertTrue(true); // chegou aqui sem exception = comportamento correto
    }

    public function test_job_has_correct_retry_and_timeout(): void
    {
        $job = new ProcessMonthlyPayrollJob(4, 2026);
        $this->assertEquals(2, $job->tries);
        $this->assertEquals(300, $job->timeout);
    }

    public function test_job_stores_triggered_by(): void
    {
        $job = new ProcessMonthlyPayrollJob(4, 2026, 42);
        $this->assertEquals(42, $job->triggeredBy);
    }
}
