<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Services\AccountSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountSettingsController extends Controller
{
    public function __construct(private AccountSecurityService $security)
    {
    }

    public function show(Request $request): View
    {
        $maTK = (int) $request->session()->get('MaTK', 0); //	Lấy MaTK từ session (ID người đang đăng nhập)
        $account = $this->security->getById($maTK);
        abort_if($account === null, 404); // Nếu không tìm thấy tài khoản, trả về lỗi 404

        if (!$request->session()->has('session_marker')) { // Nếu session chưa có marker, tạo mới và lưu vào session
            $request->session()->put('session_marker', bin2hex(random_bytes(32)));
        }

        $marker = (string) $request->session()->get('session_marker'); // Lấy marker từ session
        $this->security->registerSessionAudit($maTK, $marker, (string) $request->userAgent(), (string) $request->ip());

        $recentSessions = array_map(function (array $row) use ($marker) { // Đánh dấu session hiện tại trong danh sách session gần đây
            $row['is_current'] = (string) ($row['session_marker'] ?? '') === $marker; // Kiểm tra xem session có phải là session hiện tại

            return $row;
        }, $this->security->getRecentSessions($maTK)); // Lấy danh sách các session gần đây của tài khoản từ database

        return view('account.settings', [   // Trả về view với dữ liệu tài khoản và session
            'account' => $account,
            'recentSessions' => $recentSessions,
            'sessionInfo' => [
                'session_id' => session()->getId(),
                'session_marker' => $marker,
                'must_change_password' => !empty($account['BuocDoiMatKhau']), // Kiểm tra xem người dùng có phải đổi mật khẩu không
            ],
        ]);
    }

    public function updateUsername(Request $request): RedirectResponse 
    {
        $payload = $request->validate([ 
            'TenDangNhapMoi' => ['required', 'regex:/^[A-Za-z0-9_.]{4,50}$/'],
            'MatKhauXacNhan' => ['required', 'string'],
        ]);

        $maTK = (int) $request->session()->get('MaTK', 0);
        $account = $this->security->getById($maTK);
        if (!$account || !password_verify($payload['MatKhauXacNhan'], (string) ($account['MatKhau'] ?? ''))) {
            return back()->withErrors(['form' => 'Mật khẩu xác nhận không đúng.']);
        }

        if (!$this->security->isUsernameAvailable($payload['TenDangNhapMoi'], $maTK)) {
            return back()->withErrors(['form' => 'Tên đăng nhập mới đã tồn tại.']);
        }

        $this->security->updateUsername($maTK, $payload['TenDangNhapMoi']);
        $request->session()->put('taikhoan', $this->security->getById($maTK));

        return back()->with('success', 'Đã cập nhật tên đăng nhập.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'MatKhauHienTai' => ['required', 'string'],
            'MatKhauMoi' => ['required', 'string', 'min:8', 'same:XacNhanMatKhauMoi'],
            'XacNhanMatKhauMoi' => ['required', 'string'],
        ]);

        $maTK = (int) $request->session()->get('MaTK', 0);
        $account = $this->security->getById($maTK);
        if (!$account || !password_verify($payload['MatKhauHienTai'], (string) ($account['MatKhau'] ?? ''))) {
            return back()->withErrors(['form' => 'Mật khẩu hiện tại không đúng.']);
        }

        if (password_verify($payload['MatKhauMoi'], (string) ($account['MatKhau'] ?? ''))) {
            return back()->withErrors(['form' => 'Mật khẩu mới không được trùng mật khẩu hiện tại.']);
        }

        $this->security->updatePassword($maTK, password_hash($payload['MatKhauMoi'], PASSWORD_DEFAULT));
        $request->session()->put('taikhoan', $this->security->getById($maTK));
        $request->session()->put('must_change_password', false);

        return back()->with('success', 'Đã đổi mật khẩu thành công.');
    }

    public function refreshSession(Request $request): RedirectResponse // làm mới phiên đăng nhập hiện tại
    {
        $request->session()->regenerate();
        $marker = bin2hex(random_bytes(32));
        $request->session()->put('session_marker', $marker);
        $this->security->registerSessionAudit((int) $request->session()->get('MaTK', 0), $marker, (string) $request->userAgent(), (string) $request->ip());

        return back()->with('success', 'Đã làm mới phiên đăng nhập hiện tại.');
    }

    public function revokeOtherSessions(Request $request): RedirectResponse // thu hồi các phiên đăng nhập khác
    {
        $maTK = (int) $request->session()->get('MaTK', 0);
        $marker = (string) $request->session()->get('session_marker', '');
        if ($maTK > 0 && $marker !== '') {
            $this->security->revokeOtherSessions($maTK, $marker);
        }

        return back()->with('success', 'Đã đăng xuất các phiên khác.');
    }
}