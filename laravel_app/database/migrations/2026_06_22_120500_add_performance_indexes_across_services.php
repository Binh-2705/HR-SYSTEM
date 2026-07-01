<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, array{connection:string, table:string, name:string, columns:array<int, string>}>
     */
    private array $indexes = [
        ['connection' => 'hr_service', 'table' => 'nhanvien', 'name' => 'idx_nhanvien_trangthai_manv', 'columns' => ['TrangThai', 'MaNV']],
        ['connection' => 'hr_service', 'table' => 'hosonhanvien', 'name' => 'idx_hosonhanvien_manv', 'columns' => ['MaNV']],
        ['connection' => 'hr_service', 'table' => 'phancong', 'name' => 'idx_phancong_manv_ngaybd', 'columns' => ['MaNV', 'NgayBatDau']],
        ['connection' => 'hr_service', 'table' => 'phancong', 'name' => 'idx_phancong_mapb_macv', 'columns' => ['MaPB', 'MaCV']],
        ['connection' => 'hr_service', 'table' => 'nghiphep', 'name' => 'idx_nghiphep_manv_tungay', 'columns' => ['MaNV', 'TuNgay']],
        ['connection' => 'hr_service', 'table' => 'nghiphep', 'name' => 'idx_nghiphep_trangthai_dengay', 'columns' => ['TrangThai', 'DenNgay']],
        ['connection' => 'hr_service', 'table' => 'baohiem', 'name' => 'idx_baohiem_manv_ngaythamgia', 'columns' => ['MaNV', 'NgayThamGia']],
        ['connection' => 'hr_service', 'table' => 'session_audit', 'name' => 'idx_session_audit_matk_created', 'columns' => ['MaTK', 'created_at']],
        ['connection' => 'hr_service', 'table' => 'session_audit', 'name' => 'idx_session_audit_last_activity', 'columns' => ['last_activity']],
        ['connection' => 'hr_service', 'table' => 'session_audit', 'name' => 'idx_session_audit_marker', 'columns' => ['session_marker']],

        ['connection' => 'payroll_service', 'table' => 'bangluong', 'name' => 'idx_bangluong_manv_thang_nam', 'columns' => ['MaNV', 'Thang', 'Nam']],
        ['connection' => 'payroll_service', 'table' => 'bangluong', 'name' => 'idx_bangluong_thang_nam', 'columns' => ['Thang', 'Nam']],
        ['connection' => 'payroll_service', 'table' => 'hopdong', 'name' => 'idx_hopdong_manv_ngaybd', 'columns' => ['MaNV', 'NgayBatDau']],
        ['connection' => 'payroll_service', 'table' => 'hopdong', 'name' => 'idx_hopdong_trangthai_ngaykt', 'columns' => ['TrangThai', 'NgayKetThuc']],

        ['connection' => 'attendance_service', 'table' => 'chamcong', 'name' => 'idx_chamcong_manv_ngay', 'columns' => ['MaNV', 'Ngay']],
        ['connection' => 'attendance_service', 'table' => 'chamcong', 'name' => 'idx_chamcong_manv_thang_nam', 'columns' => ['MaNV', 'Thang', 'Nam']],
        ['connection' => 'attendance_service', 'table' => 'chamcong', 'name' => 'idx_chamcong_trangthai', 'columns' => ['TrangThai']],

        ['connection' => 'recruitment_service', 'table' => 'dottuyendung', 'name' => 'idx_dottuyendung_trangthai_tungay', 'columns' => ['TrangThai', 'TuNgay']],
        ['connection' => 'recruitment_service', 'table' => 'hosoungtuyen', 'name' => 'idx_hosoungtuyen_madtd_mauv', 'columns' => ['MaDTD', 'MaUV']],

        ['connection' => 'training_service', 'table' => 'khoadaotao', 'name' => 'idx_khoadaotao_trangthai_tungay', 'columns' => ['TrangThai', 'TuNgay']],
        ['connection' => 'training_service', 'table' => 'thamgiadaotao', 'name' => 'idx_thamgiadaotao_makdt_manv', 'columns' => ['MaKDT', 'MaNV']],

        ['connection' => 'reporting_service', 'table' => 'baocao', 'name' => 'idx_baocao_thoidiemtao', 'columns' => ['ThoiDiemTao']],

        ['connection' => 'chatbot_service', 'table' => 'chatbot_sessions', 'name' => 'idx_chatbot_sessions_last_interaction', 'columns' => ['last_interaction_at']],
        ['connection' => 'chatbot_service', 'table' => 'chatbot_messages', 'name' => 'idx_chatbot_messages_created', 'columns' => ['created_at']],
        ['connection' => 'chatbot_service', 'table' => 'chatbot_action_drafts', 'name' => 'idx_chatbot_drafts_status_created', 'columns' => ['status', 'created_at']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $index) {
            $this->createIndexIfPossible(
                $index['connection'],
                $index['table'],
                $index['name'],
                $index['columns']
            );
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $index) {
            $this->dropIndexIfExists(
                $index['connection'],
                $index['table'],
                $index['name']
            );
        }
    }

    /**
     * @param array<int, string> $columns
     */
    private function createIndexIfPossible(string $connection, string $table, string $indexName, array $columns): void
    {
        if (!array_key_exists($connection, config('database.connections', []))) {
            return;
        }

        if (!Schema::connection($connection)->hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (!Schema::connection($connection)->hasColumn($table, $column)) {
                return;
            }
        }

        if ($this->indexExists($connection, $table, $indexName)) {
            return;
        }

        $wrappedColumns = implode(', ', array_map(fn ($column) => "`{$column}`", $columns));
        DB::connection($connection)->statement("CREATE INDEX `{$indexName}` ON `{$table}` ({$wrappedColumns})");
    }

    private function dropIndexIfExists(string $connection, string $table, string $indexName): void
    {
        if (!array_key_exists($connection, config('database.connections', []))) {
            return;
        }

        if (!Schema::connection($connection)->hasTable($table)) {
            return;
        }

        if (!$this->indexExists($connection, $table, $indexName)) {
            return;
        }

        DB::connection($connection)->statement("DROP INDEX `{$indexName}` ON `{$table}`");
    }

    private function indexExists(string $connection, string $table, string $indexName): bool
    {
        $databaseName = DB::connection($connection)->getDatabaseName();

        if ($databaseName === null || $databaseName === '') {
            return false;
        }

        return DB::connection($connection)
            ->table('information_schema.statistics')
            ->where('table_schema', $databaseName)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
