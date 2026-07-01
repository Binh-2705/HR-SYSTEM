<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolePermissionBizController extends Controller
{
    private function conn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    public function indexData(): JsonResponse
    {
        $conn = DB::connection($this->conn());

        $roles = $conn->table('vaitro')->orderBy('TenVaiTro')->get(['MaVaiTro', 'TenVaiTro'])
            ->map(fn ($r) => ['MaVaiTro' => (int) $r->MaVaiTro, 'TenVaiTro' => (string) $r->TenVaiTro])->all();

        $functions = $conn->table('chucnang')->orderBy('TenChucNang')->get(['MaCN', 'TenChucNang'])
            ->map(fn ($f) => ['MaCN' => (int) $f->MaCN, 'TenChucNang' => (string) $f->TenChucNang])->all();

        $permissions = $conn->table('phanquyen')->get(['MaVaiTro', 'MaCN'])
            ->groupBy('MaVaiTro')
            ->map(fn ($rows) => $rows->pluck('MaCN')->map(fn ($id) => (int) $id)->all())
            ->all();

        return response()->json([
            'ok' => true,
            'roles'            => $roles,
            'functions'        => $functions,
            'permissionsByRole' => $permissions,
        ]);
    }

    public function accountDetail(int $accountId): JsonResponse
    {
        $conn = DB::connection($this->conn());

        $roles = $conn->table('taikhoanvaitro as tkvt')
            ->join('vaitro as vt', 'tkvt.MaVaiTro', '=', 'vt.MaVaiTro')
            ->where('tkvt.MaTK', $accountId)->orderBy('vt.TenVaiTro')
            ->pluck('vt.TenVaiTro')->map(fn ($n) => (string) $n)->all();

        $permissions = $conn->table('taikhoanvaitro as tkvt')
            ->join('phanquyen as pq', 'tkvt.MaVaiTro', '=', 'pq.MaVaiTro')
            ->join('chucnang as cn', 'pq.MaCN', '=', 'cn.MaCN')
            ->where('tkvt.MaTK', $accountId)->distinct()->orderBy('cn.TenChucNang')
            ->pluck('cn.TenChucNang')->map(fn ($n) => (string) $n)->all();

        return response()->json(['ok' => true, 'roles' => $roles, 'permissions' => $permissions, 'accountId' => $accountId]);
    }

    public function updateRolePermissions(Request $request, int $roleId): JsonResponse
    {
        $functionIds = array_values(array_unique(array_map('intval', (array) $request->input('function_ids', []))));

        DB::connection($this->conn())->transaction(function () use ($roleId, $functionIds) {
            $conn = DB::connection($this->conn());
            $conn->table('phanquyen')->where('MaVaiTro', $roleId)->delete();
            if ($functionIds !== []) {
                $conn->table('phanquyen')->insert(
                    array_map(fn (int $fId) => ['MaVaiTro' => $roleId, 'MaCN' => $fId], $functionIds)
                );
            }
        });

        return response()->json(['ok' => true]);
    }

    public function listRoles(): JsonResponse
    {
        $rows = DB::connection($this->conn())->table('vaitro')->orderBy('TenVaiTro')->get();
        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $payload = (array) $request->json()->all();
        $id = DB::connection($this->conn())->table('vaitro')->insertGetId(['TenVaiTro' => $payload['TenVaiTro']], 'MaVaiTro');
        return response()->json(['ok' => true, 'id' => $id], 201);
    }

    public function destroyRole(int $id): JsonResponse
    {
        DB::connection($this->conn())->transaction(function () use ($id) {
            $conn = DB::connection($this->conn());
            $conn->table('phanquyen')->where('MaVaiTro', $id)->delete();
            $conn->table('taikhoanvaitro')->where('MaVaiTro', $id)->delete();
            $conn->table('vaitro')->where('MaVaiTro', $id)->delete();
        });
        return response()->json(['ok' => true]);
    }

    public function assignAccountRole(Request $request): JsonResponse
    {
        $payload = (array) $request->json()->all();
        DB::connection($this->conn())->table('taikhoanvaitro')->updateOrInsert(
            ['MaTK' => (int) $payload['MaTK'], 'MaVaiTro' => (int) $payload['MaVaiTro']],
            ['MaTK' => (int) $payload['MaTK'], 'MaVaiTro' => (int) $payload['MaVaiTro']]
        );
        return response()->json(['ok' => true]);
    }

    public function revokeAccountRole(Request $request): JsonResponse
    {
        $payload = (array) $request->json()->all();
        DB::connection($this->conn())->table('taikhoanvaitro')
            ->where('MaTK', (int) $payload['MaTK'])->where('MaVaiTro', (int) $payload['MaVaiTro'])->delete();
        return response()->json(['ok' => true]);
    }

    public function restoreDefaultPermissions(int $id): JsonResponse
    {
        $conn  = DB::connection($this->conn());
        $role  = $conn->table('vaitro')->where('MaVaiTro', $id)->first();
        if (!$role) {
            return response()->json(['ok' => false, 'message' => 'Vai trò không tồn tại.'], 404);
        }

        // Map role name -> default function names
        $defaults = [
            'Admin' => null, // null means ALL functions
        ];

        $roleName = (string) $role->TenVaiTro;
        if (!array_key_exists($roleName, $defaults)) {
            return response()->json(['ok' => false, 'message' => 'Không có bộ quyền mặc định cho vai trò này.'], 404);
        }

        $functionIds = $defaults[$roleName] === null
            ? $conn->table('chucnang')->pluck('MaCN')->map(fn ($id) => (int) $id)->all()
            : $conn->table('chucnang')->whereIn('TenChucNang', $defaults[$roleName])->pluck('MaCN')->map(fn ($id) => (int) $id)->all();

        $conn->transaction(function () use ($conn, $id, $functionIds) {
            $conn->table('phanquyen')->where('MaVaiTro', $id)->delete();
            if ($functionIds !== []) {
                $conn->table('phanquyen')->insert(
                    array_map(fn (int $fId) => ['MaVaiTro' => $id, 'MaCN' => $fId], $functionIds)
                );
            }
        });

        return response()->json(['ok' => true]);
    }
}
