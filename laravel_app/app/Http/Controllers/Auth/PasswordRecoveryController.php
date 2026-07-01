<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

use App\Services\AccountSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordRecoveryController extends Controller
{
    public function __construct(private AccountSecurityService $security)
    {
    }

    public function showForgot(): View
    {
        return view('auth.forgot-password');
    }

    public function handleForgot(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'TenDangNhap' => ['required', 'string'],
            'MaNhanVien' => ['required', 'string'],
            'NgaySinh' => ['required', 'date'],
            'SoDienThoai4So' => ['required', 'string', 'min:4'],
            'MatKhauMoi' => ['required', 'string', 'min:8', 'same:XacNhanMatKhau'],
            'XacNhanMatKhau' => ['required', 'string'],
        ], [
            'TenDangNhap.required' => 'Vui lòng nhập tên đăng nhập.',
            'MaNhanVien.required' => 'Vui lòng nhập mã nhân viên.',
            'NgaySinh.required' => 'Vui lòng nhập ngày sinh.',
            'NgaySinh.date' => 'Ngày sinh không hợp lệ.',
            'SoDienThoai4So.required' => 'Vui lòng nhập 4 số cuối điện thoại.',
            'SoDienThoai4So.min' => '4 số cuối điện thoại phải có ít nhất 4 ký tự.',
            'MatKhauMoi.required' => 'Vui lòng nhập mật khẩu mới.',
            'MatKhauMoi.min' => 'Mật khẩu mới phải có ít nhất 8 ký tự.',
            'MatKhauMoi.same' => 'Xác nhận mật khẩu không khớp.',
            'XacNhanMatKhau.required' => 'Vui lòng xác nhận mật khẩu.',
        ]);

        $match = $this->security->findAccountForInternalRecovery(
            $payload['TenDangNhap'],
            $payload['MaNhanVien'],
            $payload['NgaySinh'],
            $payload['SoDienThoai4So'],
        );

        if (!$match) {
            return back()->withInput()->withErrors(['form' => 'Thông tin xác thực không khớp.']);
        }

        if (password_verify($payload['MatKhauMoi'], (string) ($match['account']['MatKhau'] ?? ''))) {
            return back()->withInput()->withErrors(['form' => 'Mật khẩu mới không được trùng mật khẩu hiện tại.']);
        }

        $this->security->updatePassword((int) $match['account']['MaTK'], password_hash($payload['MatKhauMoi'], PASSWORD_DEFAULT));

        return redirect()->route('login.form')->with('success', 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập lại.');
    }

    public function showForcedChange(Request $request): View|RedirectResponse
    {
        $maTK = (int) $request->session()->get('MaTK', 0);
        if ($maTK <= 0) {
            return redirect()->route('login.form');
        }

        $account = $this->security->getById($maTK);
        if (!$account || !$this->security->isPasswordChangeRequired($maTK)) {
            return redirect()->route('dashboard');
        }

        return view('auth.force-password', ['account' => $account]);
    }

    public function handleForcedChange(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'MatKhauMoi' => ['required', 'string', 'min:8', 'same:XacNhanMatKhau'],
            'XacNhanMatKhau' => ['required', 'string'],
        ], [
            'MatKhauMoi.required' => 'Vui lòng nhập mật khẩu mới.',
            'MatKhauMoi.min' => 'Mật khẩu mới phải có ít nhất 8 ký tự.',
            'MatKhauMoi.same' => 'Xác nhận mật khẩu không khớp.',
            'XacNhanMatKhau.required' => 'Vui lòng xác nhận mật khẩu.',
        ]);

        $maTK = (int) $request->session()->get('MaTK', 0);
        $account = $this->security->getById($maTK);
        if (!$account) {
            return redirect()->route('login.form');
        }

        if (password_verify($payload['MatKhauMoi'], (string) ($account['MatKhau'] ?? ''))) {
            return back()->withErrors(['form' => 'Mật khẩu mới không được trùng mật khẩu tạm hiện tại.']);
        }

        $this->security->updatePassword($maTK, password_hash($payload['MatKhauMoi'], PASSWORD_DEFAULT));
        $request->session()->put('taikhoan', $this->security->getById($maTK));
        $request->session()->put('must_change_password', false);

        return redirect()->route('dashboard')->with('success', 'Đã đổi mật khẩu thành công.');
    }

    public function showReset(Request $request): View
    {
        return view('auth.reset-password', ['token' => (string) $request->query('token', '')]);
    }

    public function handleReset(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'token' => ['required', 'string'],
            'MatKhauMoi' => ['required', 'string', 'min:8', 'same:XacNhanMatKhau'],
            'XacNhanMatKhau' => ['required', 'string'],
        ], [
            'token.required' => 'Token không hợp lệ.',
            'MatKhauMoi.required' => 'Vui lòng nhập mật khẩu mới.',
            'MatKhauMoi.min' => 'Mật khẩu mới phải có ít nhất 8 ký tự.',
            'MatKhauMoi.same' => 'Xác nhận mật khẩu không khớp.',
            'XacNhanMatKhau.required' => 'Vui lòng xác nhận mật khẩu.',
        ]);

        $tokenRow = $this->security->findValidResetToken($payload['token']);
        if (!$tokenRow) {
            return back()->withErrors(['form' => 'Liên kết không hợp lệ hoặc đã hết hạn.']);
        }

        $this->security->updatePassword((int) $tokenRow['MaTK'], password_hash($payload['MatKhauMoi'], PASSWORD_DEFAULT));
        $this->security->markResetTokenUsed((int) $tokenRow['id']);

        return redirect()->route('login.form')->with('success', 'Đặt lại mật khẩu thành công.');
    }
}