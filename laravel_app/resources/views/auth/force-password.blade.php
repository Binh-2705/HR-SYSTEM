<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đổi mật khẩu bắt buộc</title>
    <link rel="stylesheet" href="{{ asset('assets/css/style1.css') }}?v=20260420-2">
    <link rel="stylesheet" href="{{ asset('assets/css/legacy-bridge.css') }}?v=20260410-1">
</head>
<body>
    <main class="login-shell">
    <section class="login-container login-compact">
        <div class="login-center">
            <span class="auth-title-badge">Bắt buộc cập nhật</span>
            <h2>Đổi mật khẩu bắt buộc</h2>
            <p>Tài khoản {{ $account['TenDangNhap'] }} đang dùng mật khẩu tạm. Hãy đổi mật khẩu để tiếp tục vào hệ thống.</p>
            @if ($errors->any())
                <div class="auth-alert auth-alert-error">
                    <span class="auth-alert-title">Không thể đổi mật khẩu tạm</span>
                    <span>{{ $errors->first() }}</span>
                    <span class="auth-alert-hint">Mật khẩu mới cần đúng định dạng và trùng xác nhận.</span>
                </div>
            @endif
            @if (session('success'))
                <div class="auth-alert auth-alert-success">
                    <span class="auth-alert-title">Đổi mật khẩu thành công</span>
                    <span>{{ session('success') }}</span>
                    <span class="auth-alert-hint">Hệ thống sẽ tiếp tục phiên làm việc với mật khẩu mới.</span>
                </div>
            @endif
            <form method="post" action="{{ route('password.force.submit') }}" class="auth-form">
                @csrf
                <div>
                    <label for="MatKhauMoi">Mật khẩu mới</label>
                    <input id="MatKhauMoi" name="MatKhauMoi" type="password" required>
                </div>
                <div>
                    <label for="XacNhanMatKhau">Xác nhận mật khẩu</label>
                    <input id="XacNhanMatKhau" name="XacNhanMatKhau" type="password" required>
                </div>
                <button class="btn" type="submit">Cập nhật mật khẩu</button>
            </form>
            <div class="auth-inline-note">Mật khẩu mới sẽ được áp dụng ngay cho phiên làm việc hiện tại.</div>
        </div>
    </section>
    </main>
</body>
</html>