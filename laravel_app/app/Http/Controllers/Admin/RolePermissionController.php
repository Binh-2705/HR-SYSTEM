<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Services\RolePermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    public function __construct(private RolePermissionService $rolePermissionService)
    {
    }

    public function index(): View // xem danh sách vai trò và quyền
    {
        $data = $this->rolePermissionService->indexData();

        return view('phanquyen.index', [
            'roles' => $data['roles'], // Danh sách vai trò
            'groupOrder' => $data['groupOrder'], // Thứ tự nhóm chức năng
            'groupedFunctions' => $this->rolePermissionService->groupFunctions($data['functions']), // Các chức năng được nhóm theo nhóm chức năng
            'permissionsByRole' => $data['permissionsByRole'],// Các quyền được nhóm theo vai trò
        ]);
    }

    public function showAccount(int $account): View // xem chi tiết quyền của một tài khoản cụ thể
    {
        return view('phanquyen.detail', $this->rolePermissionService->accountDetail($account));
    }

    public function update(Request $request, int $role): RedirectResponse
    {
        $validated = $request->validate([
            'chucnang' => ['nullable', 'array'],
            'chucnang.*' => ['integer'],
        ]);

        $this->rolePermissionService->updateRolePermissions($role, $validated['chucnang'] ?? []);

        return redirect()->route('phanquyen.index')->with('success', 'Đã cập nhật quyền cho vai trò.');
    }

    public function restoreDefaults(int $role): RedirectResponse // khôi phục quyền mặc định cho vai trò
    {
        if (!$this->rolePermissionService->restoreDefaultPermissions($role)) {
            return redirect()->route('phanquyen.index')->withErrors('Không tìm thấy bộ quyền mặc định cho vai trò.');
        }

        return redirect()->route('phanquyen.index')->with('success', 'Đã khôi phục quyền mặc định cho vai trò.');
    }
}