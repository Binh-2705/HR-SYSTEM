<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đặt lại mật khẩu</title>
    <link rel="stylesheet" href="{{ asset('assets/css/style1.css') }}?v=20260410-1">
    <link rel="stylesheet" href="{{ asset('assets/css/legacy-bridge.css') }}?v=20260410-1">
</head>
<body>
    <main class="login-shell">
    <section class="login-container login-compact">
        <div class="login-center">
            <span class="auth-title-badge">Khôi phục bằng token</span>
            <h2>Đặt lại mật khẩu</h2>
            <p>Token hợp lệ sẽ cho phép cập nhật mật khẩu trực tiếp mà không cần quay lại quy trình cũ.</p>
            @if ($errors->any())
                <div class="auth-alert auth-alert-error">
                    <span class="auth-alert-title">Không thể cập nhật mật khẩu</span>
                    <span>{{ $errors->first() }}</span>
                    <span class="auth-alert-hint">Token có thể đã hết hạn hoặc mật khẩu xác nhận chưa khớp.</span>
                    <span class="auth-alert-actions"><a href="{{ route('password.forgot') }}">Lấy lại token mới</a></span>
                </div>
            @endif
            @if (session('success'))
                <div class="auth-alert auth-alert-success">
                    <span class="auth-alert-title">Mật khẩu đã được cập nhật</span>
                    <span>{{ session('success') }}</span>
                    <span class="auth-alert-hint">Bạn có thể đăng nhập lại bằng mật khẩu vừa đặt.</span>
                </div>
            @endif
            <form method="post" action="{{ route('password.reset.submit') }}" class="auth-form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div>
                    <label for="MatKhauMoi">Mật khẩu mới</label>
                    <input id="MatKhauMoi" name="MatKhauMoi" type="password" required>
                </div>
                <div>
                    <label for="XacNhanMatKhau">Xác nhận mật khẩu</label>
                    <input id="XacNhanMatKhau" name="XacNhanMatKhau" type="password" required>
                </div>
                <div class="auth-actions-row">
                    <button class="btn" type="submit">Cập nhật mật khẩu</button>
                    <a href="{{ route('login.form') }}" class="back-link">Quay lại đăng nhập</a>
                </div>
            </form>
        </div>
    </section>
    </main>
</body>
</html>