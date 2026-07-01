<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        $conn = DB::connection($connection);

        if (!Schema::connection($connection)->hasTable('chucnang') || !Schema::connection($connection)->hasTable('vaitro') || !Schema::connection($connection)->hasTable('phanquyen')) {
            return;
        }

        $features = [
            ['TenChucNang' => 'xem_quyphep', 'MoTa' => 'Xem quỹ phép năm'],
            ['TenChucNang' => 'them_quyphep', 'MoTa' => 'Thêm quỹ phép năm'],
            ['TenChucNang' => 'sua_quyphep', 'MoTa' => 'Sửa quỹ phép năm'],
            ['TenChucNang' => 'xoa_quyphep', 'MoTa' => 'Xóa quỹ phép năm'],
        ];

        $featureIds = [];
        foreach ($features as $feature) {
            $existing = $conn->table('chucnang')
                ->where('TenChucNang', $feature['TenChucNang'])
                ->first(['MaCN']);

            if ($existing !== null) {
                $featureIds[$feature['TenChucNang']] = (int) $existing->MaCN;
                continue;
            }

            $featureIds[$feature['TenChucNang']] = (int) $conn->table('chucnang')->insertGetId($feature, 'MaCN');
        }

        $roleIds = $conn->table('vaitro')
            ->whereIn('TenVaiTro', ['Admin', 'HR', 'QuanLy'])
            ->pluck('MaVaiTro', 'TenVaiTro')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($roleIds as $roleName => $roleId) {
            foreach ($featureIds as $featureId) {
                $conn->table('phanquyen')->updateOrInsert(
                    ['MaVaiTro' => $roleId, 'MaCN' => $featureId],
                    ['MaVaiTro' => $roleId, 'MaCN' => $featureId]
                );
            }
        }
    }

    public function down(): void
    {
        $connection = $this->hrConnection();
        $conn = DB::connection($connection);

        if (!Schema::connection($connection)->hasTable('chucnang') || !Schema::connection($connection)->hasTable('phanquyen')) {
            return;
        }

        $featureIds = $conn->table('chucnang')
            ->whereIn('TenChucNang', ['xem_quyphep', 'them_quyphep', 'sua_quyphep', 'xoa_quyphep'])
            ->pluck('MaCN')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($featureIds !== []) {
            $conn->table('phanquyen')->whereIn('MaCN', $featureIds)->delete();
            $conn->table('chucnang')->whereIn('MaCN', $featureIds)->delete();
        }
    }
};
