@php $title = 'Cài đặt tài khoản' @endphp
@php $subtitle = 'Cập nhật tên đăng nhập, mật khẩu và quản lý phiên đăng nhập' @endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="settings-grid">
            <article class="detail-card settings-card">
                <span class="eyebrow">Tên đăng nhập</span>
                <strong>{{ $account['TenDangNhap'] }}</strong>
            </article>
            <article class="detail-card settings-card">
                <span class="eyebrow">Vai trò</span>
                <strong>{{ $account['VaiTro'] ?? 'Nhân viên' }}</strong>
            </article>
            <article class="detail-card settings-card">
                <span class="eyebrow">Mã nhân viên</span>
                <strong>{{ $account['MaNV'] ?: 'Chưa gán' }}</strong>
            </article>
        </div>
    </section>

    <div class="settings-grid">
    <section class="panel settings-card">
        <h2 class="no-top-margin">Đổi tên đăng nhập</h2>
        <p class="settings-note">Đổi tên đăng nhập và xác nhận bằng mật khẩu hiện tại để giữ nguyên cơ chế bảo mật như hệ thống cũ.</p>
        <form method="post" action="{{ route('settings.username') }}" class="settings-form-stack">
            @csrf
            <div class="field-grid">
                <div class="settings-control-row">
                    <label for="TenDangNhapMoi">Tên đăng nhập mới</label>
                    <input id="TenDangNhapMoi" name="TenDangNhapMoi" value="{{ old('TenDangNhapMoi') }}" required>
                </div>
                <div class="settings-control-row">
                    <label for="MatKhauXacNhan">Mật khẩu xác nhận</label>
                    <input id="MatKhauXacNhan" name="MatKhauXacNhan" type="password" required>
                </div>
            </div>
            <div class="form-actions-bar"><button class="btn" type="submit">Cập nhật tên đăng nhập</button></div>
        </form>
    </section>

    <section class="panel settings-card">
        <h2 class="no-top-margin">Đổi mật khẩu</h2>
        <p class="settings-note">Mật khẩu mới được áp dụng ngay cho phiên hiện tại và các phiên khác có thể bị thu hồi nếu cần.</p>
        <form method="post" action="{{ route('settings.password') }}" class="settings-form-stack">
            @csrf
            <div class="field-grid">
                <div class="settings-control-row">
                    <label for="MatKhauHienTai">Mật khẩu hiện tại</label>
                    <input id="MatKhauHienTai" name="MatKhauHienTai" type="password" required>
                </div>
                <div class="settings-control-row">
                    <label for="MatKhauMoi">Mật khẩu mới</label>
                    <input id="MatKhauMoi" name="MatKhauMoi" type="password" required>
                </div>
                <div class="settings-control-row">
                    <label for="XacNhanMatKhauMoi">Xác nhận mật khẩu mới</label>
                    <input id="XacNhanMatKhauMoi" name="XacNhanMatKhauMoi" type="password" required>
                </div>
            </div>
            <div class="form-actions-bar"><button class="btn" type="submit">Cập nhật mật khẩu</button></div>
        </form>
    </section>
    </div>

    <section class="panel settings-card">
        <h2 class="no-top-margin">Quản lý phiên đăng nhập</h2>
        <p class="settings-note">Theo dõi các phiên đăng nhập hoạt động và thu hồi phiên khác ngay trong hệ thống mà không cần quay lại runtime cũ.</p>
        <div class="settings-row"><strong>Session hiện tại:</strong> <span class="settings-session-id">{{ $sessionInfo['session_marker'] }}</span></div>
        <div class="split-actions top-gap-lg">
            <form method="post" action="{{ route('settings.refresh-session') }}" class="inline-form">@csrf<button class="btn btn-secondary" type="submit">Làm mới phiên hiện tại</button></form>
            <form method="post" action="{{ route('settings.revoke-other-sessions') }}" class="inline-form">@csrf<button class="btn btn-danger" type="submit">Đăng xuất các phiên khác</button></form>
        </div>
        <div class="table-shell">
            <table class="data-table table-compact">
                <thead>
                    <tr>
                        <th>Session</th>
                        <th>IP</th>
                        <th>Lần hoạt động cuối</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentSessions as $session)
                        <tr>
                            <td>{{ $session['session_marker'] }}@if($session['is_current']) <strong>(hiện tại)</strong>@endif</td>
                            <td>{{ $session['ip_address'] ?: 'không rõ' }}</td>
                            <td>{{ $session['last_activity'] }}</td>
                            <td>
                                <span class="status-badge {{ $session['revoked_at'] ? 'danger' : ($session['is_current'] ? 'info' : 'success') }}">
                                    {{ $session['revoked_at'] ? 'Đã thu hồi' : ($session['is_current'] ? 'Hiện tại' : 'Đang hoạt động') }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection