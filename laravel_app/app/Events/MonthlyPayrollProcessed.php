<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MonthlyPayrollProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $month;
    public int $year;
    public int $processed;
    public int $triggeredBy;

    public function __construct(int $month, int $year, int $processed, int $triggeredBy)
    {
        $this->month = $month;
        $this->year = $year;
        $this->processed = $processed;
        $this->triggeredBy = $triggeredBy;
    }
}
