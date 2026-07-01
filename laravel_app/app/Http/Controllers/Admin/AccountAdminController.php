<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Services\AccountSecurityService;
use App\Services\GenericResourceModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountAdminController extends Controller
{
    public function __construct(
        private AccountSecurityService $accountSecurityService,
        private GenericResourceModuleService $modules,
    ) {
    }

    public function resetTemporaryPassword(Request $request, int $account): RedirectResponse
    {
        $record = $this->accountSecurityService->getById($account);
        if ($record === null) {
            return redirect()->route('taikhoan.index')->withErrors('Không tìm thấy tài khoản cần cấp lại mật khẩu.');
        }

        $temporaryPassword = 'HRM' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $updated = $this->accountSecurityService->updatePassword(
            $account,
            password_hash($temporaryPassword, PASSWORD_DEFAULT),
            true
        );

        if (!$updated) {
            return redirect()->route('taikhoan.index')->withErrors('Không thể cấp lại mật khẩu tạm.');
        }

        if ((int) $request->session()->get('MaTK', 0) === $account) {
            $request->session()->put('must_change_password', true);
            $currentAccount = (array) $request->session()->get('taikhoan', []);
            $currentAccount['BuocDoiMatKhau'] = 1;
            $request->session()->put('taikhoan', $currentAccount);
        }

        $username = (string) ($record['TenDangNhap'] ?? ('#' . $account));

        return redirect()->route('taikhoan.index')
            ->with('success', "Đã cấp mật khẩu tạm cho <strong>{$username}</strong>: <strong>{$temporaryPassword}</strong> — vui lòng ghi lại và đưa cho nhân viên. Họ sẽ được yêu cầu đổi mật khẩu khi đăng nhập.");
    }

    public function destroyLegacy(int $account): RedirectResponse //xóa tài khoản
    {
        $this->modules->delete('accounts', (string) $account);

        return redirect()->route('taikhoan.index')->with('success', 'Đã xóa tài khoản thành công.');
    }
}