<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

use App\Services\AccountSecurityService;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private PermissionService $permissionService,
        private AccountSecurityService $securityService,
    ) {}

    public function showLogin(): View|RedirectResponse
    {
        if (session()->has('MaTK')) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'TenDangNhap' => ['required', 'string', 'max:100'],
            'MatKhau' => ['required', 'string', 'max:255'],
        ]);

        $account = $this->securityService->getByUsername($credentials['TenDangNhap']);

        if (!$account || !password_verify($credentials['MatKhau'], (string) ($account['MatKhau'] ?? '')) || !$this->isAccountActive((string) ($account['TrangThai'] ?? 'Hoạt động'))) {
            return back()
                ->withErrors(['auth' => 'Sai tên đăng nhập hoặc mật khẩu'])
                ->withInput(['TenDangNhap' => $credentials['TenDangNhap']]);
        }

        $permissions = $this->permissionService->getPermissionsByAccountId((int) $account['MaTK']);

        $request->session()->put('MaTK', (int) $account['MaTK']);
        $request->session()->put('taikhoan', $account);
        $request->session()->put('quyen', $permissions);
        $request->session()->put('must_change_password', !empty($account['BuocDoiMatKhau']));
        $request->session()->put('session_marker', bin2hex(random_bytes(32)));
        $this->securityService->registerSessionAudit(
            (int) $account['MaTK'],
            (string) $request->session()->get('session_marker'),
            (string) $request->userAgent(),
            (string) $request->ip()
        );

        if (!empty($account['BuocDoiMatKhau'])) {
            return redirect()->route('password.force');
        }

        return redirect()->route('dashboard');
    }

    private function isAccountActive(string $status): bool
    {
        $normalized = mb_strtolower(trim($status));

        if ($normalized === '' || $normalized === 'hoạt động' || $normalized === 'hoat dong') {
            return true;
        }

        // Some legacy dumps lose Vietnamese diacritics into question marks.
        if (str_starts_with($normalized, 'ho') && str_ends_with($normalized, 'ng')) {
            return true;
        }

        return !in_array($normalized, ['khóa', 'khoa', 'disabled', 'inactive'], true);
    }

    public function logout(Request $request): RedirectResponse
    {
        $maTK = (int) $request->session()->get('MaTK', 0);
        $marker = (string) $request->session()->get('session_marker', '');
        if ($maTK > 0 && $marker !== '') {
            $this->securityService->revokeCurrentSession($maTK, $marker);
        }

        $request->session()->flush();
        $request->session()->regenerateToken();

        return redirect()->route('login.form');
    }

    public function logoutBridge(Request $request): RedirectResponse
    {
        return $this->logout($request);
    }
}
