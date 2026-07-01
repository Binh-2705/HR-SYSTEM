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

        if (Schema::connection($connection)->hasTable('leave_request_approval_actions')) {
            return;
        }

        Schema::connection($connection)->create('leave_request_approval_actions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('MaNP');
            $table->unsignedBigInteger('MaTK');
            $table->string('ApproverRole', 80)->nullable();
            $table->string('ActionName', 20); // approved | rejected
            $table->string('Note', 255)->nullable();
            $table->timestamps();

            $table->index(['MaNP', 'ActionName'], 'idx_leave_approval_mapn_action');
            $table->index(['MaNP', 'created_at'], 'idx_leave_approval_mapn_created');
            $table->unique(['MaNP', 'MaTK', 'ActionName'], 'uq_leave_approval_actor_action');
        });
    }

    public function down(): void
    {
        $connection = $this->hrConnection();

        Schema::connection($connection)->dropIfExists('leave_request_approval_actions');
    }
};
