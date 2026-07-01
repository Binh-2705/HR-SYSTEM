<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hrConnection(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    public function up(): void
    {
        $connection = $this->hrConnection();

        if (Schema::connection($connection)->hasTable('leave_balances')) {
            return;
        }

        Schema::connection($connection)->create('leave_balances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('MaNV');
            $table->unsignedSmallInteger('Nam');
            $table->unsignedSmallInteger('EntitledDays')->default(12);
            $table->unsignedSmallInteger('UsedDays')->default(0);
            $table->timestamps();

            $table->unique(['MaNV', 'Nam'], 'uq_leave_balances_employee_year');
            $table->index(['Nam', 'MaNV'], 'idx_leave_balances_year_employee');
        });
    }

    public function down(): void
    {
        $connection = $this->hrConnection();

        Schema::connection($connection)->dropIfExists('leave_balances');
    }
};
