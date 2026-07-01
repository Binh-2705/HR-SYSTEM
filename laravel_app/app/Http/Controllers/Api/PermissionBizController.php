<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionBizController extends Controller
{
    private function conn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    /**
     * Returns array of permission names for an account.
     * GET /api/biz/permissions?ma_tk={id}
     */
    public function byAccount(Request $request): JsonResponse
    {
        $maTK = (int) $request->query('ma_tk', 0);

        $permissions = DB::connection($this->conn())
            ->table('taikhoanvaitro as tkvt')
            ->join('vaitro as vt', 'tkvt.MaVaiTro', '=', 'vt.MaVaiTro')
            ->join('phanquyen as pq', 'vt.MaVaiTro', '=', 'pq.MaVaiTro')
            ->join('chucnang as cn', 'pq.MaCN', '=', 'cn.MaCN')
            ->where('tkvt.MaTK', $maTK)
            ->pluck('cn.TenChucNang')
            ->toArray();

        return response()->json(['ok' => true, 'permissions' => $permissions]);
    }
}
