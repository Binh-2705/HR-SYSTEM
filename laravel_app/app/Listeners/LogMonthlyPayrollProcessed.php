<?php

namespace App\Listeners;

use App\Events\MonthlyPayrollProcessed;
use Illuminate\Support\Facades\Log;

class LogMonthlyPayrollProcessed
{
    public function handle(MonthlyPayrollProcessed $event): void
    {
        Log::channel('audit')->info('Monthly payroll processed', [
            'month' => $event->month,
            'year' => $event->year,
            'processed' => $event->processed,
            'triggered_by' => $event->triggeredBy,
        ]);
    }
}
