<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Hệ thống quản lý nhân sự' }}</title>
    <script>
    (function () {
        try {
            var root = document.documentElement;

            function getCookie(name) {
                var parts = (document.cookie || '').split(';');
                for (var i = 0; i < parts.length; i++) {
                    var part = parts[i].trim();
                    if (part.indexOf(name + '=') === 0) {
                        return decodeURIComponent(part.substring(name.length + 1));
                    }
                }
                return '';
            }

            var theme = localStorage.getItem('hrm-theme') || 'light';
            root.setAttribute('data-theme', theme === 'dark' ? 'dark' : 'light');

            var density = localStorage.getItem('hrm-density') || 'comfortable';
            root.setAttribute('data-density', density === 'compact' ? 'compact' : 'comfortable');

            var notifications = localStorage.getItem('hrm-notifications') || 'on';
            if (notifications === 'off') {
                root.classList.add('notifications-off');
            }

            var language = localStorage.getItem('hrm-language') || getCookie('hrm-language') || 'vi';
            root.setAttribute('data-language', language === 'en' ? 'en' : 'vi');
        } catch (e) {}
    })();
    </script>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v=20260420-5">
    <link rel="stylesheet" href="{{ asset('assets/css/legacy-bridge.css') }}?v=20260410-1">
    <link rel="stylesheet" href="{{ asset('assets/css/sidebar.css') }}?v=20260410-1">
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}?v=20260410-1">
    <link rel="stylesheet" href="{{ asset('assets/css/modules.css') }}?v=20260622-3">
    <link rel="stylesheet" href="{{ asset('assets/css/chatbot-widget.css') }}?v=20260420-1">
</head>
<body class="app-body">
    @php
        $account = (array) session('taikhoan', []);
        $sessionPerms = (array) session('quyen', []);
        // Prefer session permissions to keep page rendering fast.
        $maTKLayout = (int) ($account['MaTK'] ?? 0);
        if ($maTKLayout > 0 && empty($sessionPerms)) {
            try {
                $permissions = app(\App\Services\PermissionService::class)->getPermissionsByAccountId($maTKLayout);
                if (!empty($permissions)) {
                    request()->session()->put('quyen', $permissions);
                } else {
                    $permissions = $sessionPerms;
                }
            } catch (\Throwable $e) {
                $permissions = $sessionPerms;
            }
        } else {
            $permissions = $sessionPerms;
        }
        $resourceModules = config('laravel_resource_modules', []);
        $sidebarUsername = trim((string) ($account['TenDangNhap'] ?? 'Người dùng'));
        $sidebarRole = trim((string) ($account['VaiTro'] ?? 'Nhân viên'));
        $sidebarEmployeeCode = trim((string) ($account['MaNV'] ?? ''));
        $avatarSeed = $sidebarUsername !== '' ? $sidebarUsername : 'U';
        $avatarInitial = strtoupper(substr($avatarSeed, 0, 1));
        $sidebarRoleLower = strtolower($sidebarRole);
        $canNhanVien = in_array('xem_nhanvien', $permissions, true);
        $canHoSo = in_array('xem_hoso', $permissions, true) || in_array('xem_nhanvien', $permissions, true);
        $canPhanCong = in_array('xem_phancong', $permissions, true);
        $canHopDong = in_array('xem_hopdong', $permissions, true);
        $canTuyenDung = in_array('xem_dot_tuyen', $permissions, true);
        $canDaoTao = in_array('xem_khoa_dao_tao', $permissions, true);
        $showNhanSu = $canNhanVien || $canHoSo || $canPhanCong || $canHopDong || $canTuyenDung || $canDaoTao;
        $canChamCong = in_array('xem_chamcong', $permissions, true);
        $canCreate = in_array('them_chamcong', $permissions, true);
        $canLuong = in_array('xem_luong', $permissions, true);
        $canNghiPhep = in_array('xem_nghiphep', $permissions, true);
        $canQuyPhep = in_array('xem_quyphep', $permissions, true);
        $canBaoHiem = in_array('xem_baohiem', $permissions, true);
        $canKhenThuong = in_array('xem_khenthuong', $permissions, true);
        $showCong = $canChamCong || $canLuong || $canNghiPhep || $canQuyPhep || $canBaoHiem || $canKhenThuong;
        $canPhongBan = in_array('xem_phongban', $permissions, true);
        $canChucVu = in_array('xem_chucvu', $permissions, true);
        $canNgachLuong = in_array('xem_ngachluong', $permissions, true);
        $canBacLuong = in_array('xem_bacluong', $permissions, true);
        $canTaiKhoan = in_array('xem_taikhoan', $permissions, true);
        $canPhanQuyen = in_array('xem_phanquyen', $permissions, true);
        $showHeThong = $canPhongBan || $canChucVu || $canNgachLuong || $canBacLuong || $canTaiKhoan || $canPhanQuyen;
        $canBaoCao = in_array('xem_baocao', $permissions, true);
        $flashMessages = [];
        $contextModule = null;
        $experiencePanel = null;
        $experienceActions = [];

        $pushAction = function (array &$bucket, string $label, string $routeName, string $btnClass = 'btn btn-secondary') {
            if (\Illuminate\Support\Facades\Route::has($routeName)) {
                $bucket[] = [
                    'label' => $label,
                    'href' => route($routeName),
                    'class' => $btnClass,
                ];
            }
        };

        if (request()->routeIs('chamcong.*', 'attendance.*')) {
            $contextModule = 'attendance';
            $experiencePanel = [
                'title' => 'Bắt đầu nhanh với Chấm công',
                'subtitle' => 'Đi theo 3 bước để giảm sai sót dữ liệu và thao tác nhanh hơn.',
                'steps' => [
                    'Lọc theo ngày hoặc nhân viên để kiểm tra đúng phạm vi dữ liệu.',
                    'Rà các dòng đang báo Thiếu dữ liệu nguồn hoặc Dữ liệu không hợp lệ.',
                    'Sửa bản ghi ngay tại dòng liên quan rồi kiểm tra lại bảng tháng.',
                ],
            ];
            if ($canCreate) {
                $pushAction($experienceActions, 'Thêm chấm công', 'chamcong.create');
            }
            if ($canChamCong) {
                $pushAction($experienceActions, 'Xem bảng tháng', 'chamcong.matrix');
            }
        } elseif (request()->routeIs('luong.*', 'payroll.*')) {
            $contextModule = 'payroll';
            $experiencePanel = [
                'title' => 'Bắt đầu nhanh với Bảng lương',
                'subtitle' => 'Thao tác theo luồng để đảm bảo số liệu lương ổn định.',
                'steps' => [
                    'Chạy tính lương tháng trước khi rà soát chi tiết từng nhân viên.',
                    'Ưu tiên xử lý các bản ghi thiếu trạng thái hoặc thiếu dữ liệu nguồn.',
                    'Chỉ chốt lương sau khi đã kiểm tra thực nhận và tổng lương.',
                ],
            ];
            if (in_array('tinh_luong_thang', $permissions, true)) {
                $pushAction($experienceActions, 'Tạo bảng lương mới', 'luong.create');
            }
            if ($canLuong) {
                $pushAction($experienceActions, 'Xem danh sách lương', 'luong.index');
            }
        } elseif (request()->routeIs('tuyendung.*', 'recruitment.*')) {
            $contextModule = 'recruitment';
            $experiencePanel = [
                'title' => 'Bắt đầu nhanh với Tuyển dụng',
                'subtitle' => 'Quy trình gợi ý giúp theo dõi hồ sơ mượt hơn cho người mới.',
                'steps' => [
                    'Tạo đợt tuyển và điền rõ vị trí cần tuyển.',
                    'Thêm ứng viên trước, sau đó nộp hồ sơ vào đúng đợt tuyển.',
                    'Theo dõi phỏng vấn và đánh giá để cập nhật trạng thái kịp thời.',
                ],
            ];
            if (in_array('them_dot_tuyen', $permissions, true)) {
                $pushAction($experienceActions, 'Thêm đợt tuyển', 'tuyendung.create');
            }
            if (in_array('xem_ung_vien', $permissions, true)) {
                $pushAction($experienceActions, 'Mở danh sách ứng viên', 'tuyendung.ungvien.index');
            }
        } elseif (request()->routeIs('daotao.*', 'training.*')) {
            $contextModule = 'training';
            $experiencePanel = [
                'title' => 'Bắt đầu nhanh với Đào tạo',
                'subtitle' => 'Đi theo thứ tự để không bỏ sót học viên và kết quả.',
                'steps' => [
                    'Tạo khóa và xác nhận đủ thông tin đơn vị tổ chức, thời gian.',
                    'Thêm học viên ngay sau khi tạo để khóa có dữ liệu tham gia.',
                    'Cập nhật kết quả cuối khóa để hoàn thiện hồ sơ năng lực.',
                ],
            ];
            if (in_array('them_khoa_dao_tao', $permissions, true)) {
                $pushAction($experienceActions, 'Thêm khóa đào tạo', 'daotao.create');
            }
            if (in_array('xem_khoa_dao_tao', $permissions, true)) {
                $pushAction($experienceActions, 'Mở danh sách khóa', 'daotao.index');
            }
        }

        $flashBuilder = function (string $type, string $text) use ($contextModule, $experienceActions) {
            $titles = [
                'success' => 'Thao tác đã hoàn tất',
                'error' => 'Thao tác chưa hoàn tất',
                'info' => 'Thông báo hệ thống',
            ];

            $hintsByType = [
                'success' => 'Bạn có thể tiếp tục bước kế tiếp để hoàn thành quy trình.',
                'error' => 'Kiểm tra lại dữ liệu đang báo lỗi và thử lại thao tác.',
                'info' => 'Xem kỹ nội dung thông báo để chọn hành động phù hợp.',
            ];

            $moduleHints = [
                'attendance' => 'Nếu thiếu giờ vào/ra, mở bản ghi tương ứng để cập nhật nguồn chấm công.',
                'payroll' => 'Nếu bảng lương bất thường, kiểm tra lại dữ liệu chấm công và trạng thái chốt.',
                'recruitment' => 'Nếu thiếu hồ sơ hoặc lịch phỏng vấn, bổ sung ứng viên và trạng thái xử lý.',
                'training' => 'Nếu thiếu học viên hoặc kết quả, cập nhật ngay trong danh sách tham gia.',
            ];

            return [
                'type' => $type,
                'text' => $text,
                'title' => $titles[$type] ?? 'Thông báo',
                'hint' => ($moduleHints[$contextModule] ?? $hintsByType[$type] ?? null),
                'actions' => array_slice($experienceActions, 0, 2),
            ];
        };

        if (session('success')) {
            $flashMessages[] = $flashBuilder('success', (string) session('success'));
        }
        if (session('error')) {
            $flashMessages[] = $flashBuilder('error', (string) session('error'));
        }
        if (session('message')) {
            $flashMessages[] = $flashBuilder('info', (string) session('message'));
        }
        if (request()->query('msg')) {
            $flashMessages[] = $flashBuilder('info', (string) request()->query('msg'));
        }

        $hrGroupOpen = request()->routeIs('employees.*', 'nhanvien.*') || request()->routeIs('employee-profiles.*', 'hosocanhan.*') || request()->routeIs('assignments.*', 'phancong.*') || request()->routeIs('contracts.*', 'hopdong.*') || request()->routeIs('recruitment.*', 'tuyendung.*') || request()->routeIs('training.*', 'daotao.*');
        $benefitGroupOpen = request()->routeIs('attendance.*', 'chamcong.*') || request()->routeIs('payroll.*', 'luong.*') || request()->routeIs('leave-requests.*', 'nghiphep.*') || request()->routeIs('leave-balances.*', 'quyphep.*') || request()->routeIs('insurances.*', 'baohiem.*') || request()->routeIs('reward-records.*', 'khenthuong.*');
        $systemGroupOpen = request()->routeIs('departments.*', 'phongban.*') || request()->routeIs('positions.*', 'chucvu.*') || request()->routeIs('salary-bands.*', 'ngachluong.*') || request()->routeIs('salary-grades.*', 'bacluong.*') || request()->routeIs('accounts.*', 'taikhoan.*') || request()->routeIs('permission-matrix.*', 'phanquyen.*') || request()->routeIs('services.*') || request()->routeIs('system-health.*', 'systemhealth.*') || request()->routeIs('chatbot.*') || request()->routeIs('audit-logs.*', 'auditlog.*');
    @endphp


    @if (!empty($flashMessages))
        <div class="flash-stack">
            @foreach ($flashMessages as $flash)
                <div class="flash-alert flash-{{ $flash['type'] }}">
                    <div class="flash-title">{{ $flash['title'] }}</div>
                    <div>{{ $flash['text'] }}</div>
                    @if (!empty($flash['hint']))
                        <div class="flash-hint">{{ $flash['hint'] }}</div>
                    @endif
                    @if (!empty($flash['actions']))
                        <div class="flash-actions">
                            @foreach ($flash['actions'] as $action)
                                <a href="{{ $action['href'] }}" class="{{ $action['class'] }}">{{ $action['label'] }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="container">
        <aside class="sidebar" id="appSidebar">
            <div class="sidebar-brand">
                <span class="sidebar-brand-mark">HR</span>
                <div class="sidebar-brand-copy">
                    <span class="sidebar-brand-kicker">Workforce Console</span>
                    <h2 data-i18n="brand.title">Hệ thống nhân sự</h2>
                    <p>Quản lý hồ sơ, công lương và vận hành nội bộ trong một không gian làm việc thống nhất.</p>
                </div>
            </div>

            <ul class="menu-list">
                <li>
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><span class="menu-icon">HM</span><span data-i18n="menu.home">Trang chủ</span></a>
                </li>

                @if ($showNhanSu)
                    <li class="has-submenu {{ $hrGroupOpen ? 'open' : '' }}">
                        <a href="#" class="menu-toggle"><span class="menu-icon">NS</span><span data-i18n="menu.hr">Nhân sự</span> <span class="arrow">+</span></a>
                        <ul class="submenu">
                            @if ($canNhanVien)<li><a href="{{ route('nhanvien.index') }}" class="{{ request()->routeIs('employees.*', 'nhanvien.*') ? 'active' : '' }}">Nhân viên</a></li>@endif
                            @if ($canHoSo)<li><a href="{{ route('hosocanhan.index') }}" class="{{ request()->routeIs('employee-profiles.*', 'hosocanhan.*') ? 'active' : '' }}">Hồ sơ</a></li>@endif
                            @if ($canHoSo && in_array($sidebarRoleLower, ['nhanvien', 'admin', 'quanly', 'hr', 'ketoan'], true))<li><a href="{{ route('hosocanhan.create') }}">Nhập nhanh hồ sơ</a></li>@endif
                            @if ($canHoSo && in_array($sidebarRoleLower, ['admin', 'quanly'], true))<li><a href="{{ route('hosocanhan.review-requests') }}">Duyệt yêu cầu sửa</a></li>@endif
                            @if ($canPhanCong)<li><a href="{{ route('phancong.index') }}">Công tác</a></li>@endif
                            @if ($canHopDong)<li><a href="{{ route('hopdong.index') }}">Hợp đồng</a></li>@endif
                            @if ($canTuyenDung)<li><a href="{{ route('tuyendung.index') }}">Tuyển dụng</a></li>@endif
                            @if ($canDaoTao)<li><a href="{{ route('daotao.index') }}">Đào tạo</a></li>@endif
                        </ul>
                    </li>
                @endif

                @if ($showCong)
                    <li class="has-submenu {{ $benefitGroupOpen ? 'open' : '' }}">
                        <a href="#" class="menu-toggle"><span class="menu-icon">PL</span><span data-i18n="menu.benefits">Công và phúc lợi</span> <span class="arrow">+</span></a>
                        <ul class="submenu">
                            @if ($canChamCong)<li><a href="{{ route('chamcong.index') }}">Chấm công</a></li>@endif
                            @if ($canLuong)<li><a href="{{ route('luong.index') }}">Lương</a></li>@endif
                            @if (Route::has('nghiphep.index') && $canNghiPhep)<li><a href="{{ route('nghiphep.index') }}">Nghỉ phép</a></li>@endif
                            @if (Route::has('quyphep.index') && $canQuyPhep)<li><a href="{{ route('quyphep.index') }}">Quỹ phép năm</a></li>@endif
                            @if (Route::has('baohiem.index') && $canBaoHiem)<li><a href="{{ route('baohiem.index') }}">Bảo hiểm</a></li>@endif
                            @if (Route::has('khenthuong.index') && $canKhenThuong)<li><a href="{{ route('khenthuong.index') }}">Khen thưởng</a></li>@endif
                        </ul>
                    </li>
                @endif

                @if ($showHeThong)
                    <li class="has-submenu {{ $systemGroupOpen ? 'open' : '' }}">
                        <a href="#" class="menu-toggle"><span class="menu-icon">HT</span><span data-i18n="menu.system">Hệ thống</span> <span class="arrow">+</span></a>
                        <ul class="submenu">
                            @if ($canPhongBan)<li><a href="{{ route('phongban.index') }}">Phòng ban</a></li>@endif
                            @if (Route::has('chucvu.index') && $canChucVu)<li><a href="{{ route('chucvu.index') }}">Chức vụ</a></li>@endif
                            @if (Route::has('ngachluong.index') && $canNgachLuong)<li><a href="{{ route('ngachluong.index') }}">Ngạch lương</a></li>@endif
                            @if (Route::has('bacluong.index') && $canBacLuong)<li><a href="{{ route('bacluong.index') }}">Bậc lương</a></li>@endif
                            @if (Route::has('taikhoan.index') && $canTaiKhoan)<li><a href="{{ route('taikhoan.index') }}">Tài khoản</a></li>@endif
                            @if ($canPhanQuyen)<li><a href="{{ route('phanquyen.index') }}">Phân quyền</a></li>@endif
                            @if (Route::has('auditlog.index') && $canTaiKhoan)<li><a href="{{ route('auditlog.index') }}">Nhật ký hệ thống</a></li>@endif
                            @if (in_array('su_dung_chatbot', $permissions, true))<li><a href="{{ route('chatbot.index') }}" class="{{ request()->routeIs('chatbot.*') ? 'active' : '' }}">Nhật ký Chatbot</a></li>@endif
                            @if ($canTaiKhoan)<li><a href="{{ route('systemhealth.index') }}">Sức khỏe hệ thống</a></li>@endif
                            @if ($canPhanQuyen)<li><a href="{{ route('services.index') }}">Bảng dịch vụ</a></li>@endif
                        </ul>
                    </li>
                @endif

                @if ($canBaoCao)
                    <li>
                        <a href="{{ route('baocao.index') }}" class="{{ request()->routeIs('reports.*', 'baocao.*') ? 'active' : '' }}"><span class="menu-icon">BC</span><span data-i18n="menu.report">Báo cáo</span></a>
                    </li>
                @endif

                <li>
                    <a href="{{ route('search.index') }}" class="{{ request()->routeIs('search.*') ? 'active' : '' }}"><span class="menu-icon">TC</span><span>Tra cứu</span></a>
                </li>

                <li>
                    <a href="{{ route('settings.show') }}" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}"><span class="menu-icon">CD</span><span data-i18n="menu.settings">Cài đặt</span></a>
                </li>
            </ul>

            <div class="sidebar-account" id="sidebarAccountWidget">
                <button type="button" class="sidebar-account-trigger" id="sidebarAccountTrigger" aria-expanded="false">
                    <span class="sidebar-avatar">{{ $avatarInitial }}</span>
                    <span class="sidebar-account-text">
                        <strong>{{ $sidebarUsername }}</strong>
                        <small>
                            <span class="sidebar-status-dot"></span>
                            <span>Đang hoạt động</span>
                        </small>
                    </span>
                    <span class="sidebar-account-caret">▾</span>
                </button>

                <div class="sidebar-account-panel" id="sidebarAccountPanel">
                    <div class="sidebar-account-header">
                        <span class="sidebar-avatar sidebar-avatar-large">{{ $avatarInitial }}</span>
                        <div class="sidebar-account-identity">
                            <div class="sidebar-account-name">{{ $sidebarUsername }}</div>
                            <div class="sidebar-account-role">{{ $sidebarRole }}</div>
                        </div>
                    </div>

                    <div class="sidebar-account-meta">
                        <span class="sidebar-account-meta-label">Mã nhân sự</span>
                        <strong>{{ $sidebarEmployeeCode !== '' ? $sidebarEmployeeCode : 'Chưa gán' }}</strong>
                    </div>

                    <div class="sidebar-account-actions">
                        <a href="{{ route('settings.show') }}">
                            <span class="sidebar-action-icon">⚙</span>
                            <span>Cài đặt tài khoản</span>
                        </a>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn delete button-row button-full-row">
                                <span class="sidebar-action-icon">↪</span>
                                <span>Đăng xuất</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        <button type="button" class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></button>

        <main class="main-content">
            <div class="content-topbar">
                <button type="button" class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-controls="appSidebar" aria-expanded="false">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="content-topbar-meta">
                    <span class="content-kicker">Không gian làm việc</span>
                    <div class="content-meta-row">
                        <span class="content-pill">{{ $sidebarRole }}</span>
                        @if ($sidebarEmployeeCode !== '')
                            <span class="content-pill soft">{{ $sidebarEmployeeCode }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="page-shell-header">
                <div class="page-shell-copy">
                    <span class="page-shell-kicker">HR Workspace</span>
                    <h1>{{ $title ?? 'Hệ thống quản lý nhân sự' }}</h1>
                    @isset($subtitle)
                        <p>{{ $subtitle }}</p>
                    @else
                        <p>Theo dõi nhân sự, tác vụ vận hành và dữ liệu nội bộ trong một giao diện rõ ràng hơn.</p>
                    @endisset
                </div>
                <div class="page-shell-actions">
                    <a class="btn btn-secondary" href="{{ route('settings.show') }}">Cài đặt tài khoản</a>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-danger" type="submit">Đăng xuất</button>
                    </form>
                </div>
            </div>

            @if (!empty($experiencePanel))
                <section class="panel experience-strip">
                    <div class="experience-strip-head">
                        <div>
                            <h2 class="experience-strip-title">{{ $experiencePanel['title'] }}</h2>
                            <p class="experience-strip-subtitle">{{ $experiencePanel['subtitle'] }}</p>
                        </div>
                        @if (!empty($experienceActions))
                            <div class="button-row">
                                @foreach ($experienceActions as $action)
                                    <a href="{{ $action['href'] }}" class="{{ $action['class'] }}">{{ $action['label'] }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <ol class="experience-strip-steps">
                        @foreach (($experiencePanel['steps'] ?? []) as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                </section>
            @endif

            <div class="page-stage">
                @if ($errors->any() && !session('error'))
                    <div class="flash-alert flash-error flash-inline">{{ $errors->first() }}</div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

    <script src="{{ asset('assets/js/sidebar.js') }}?v=20260420-5" defer></script>

    {{-- ===== Floating AI Chat Widget ===== --}}
    @php $hasChat = in_array('su_dung_chatbot', (array)session('quyen', []), true); @endphp
    @if($hasChat)
        <div id="ai-chat-panel" role="dialog" aria-modal="true" aria-label="Trợ lý AI"
            data-ask-url="{{ route('chatbot.ask') }}"
            data-confirm-url="{{ route('chatbot.confirm-draft') }}"
            data-charts-url="{{ route('dashboard.charts') }}">
        <div class="ai-chat-header">
            <div class="ai-chat-header-avatar">🤖</div>
            <div class="ai-chat-header-text">
                <div class="ai-chat-header-title">Trợ lý AI Nhân sự</div>
                <div class="ai-chat-header-status">
                    <span class="ai-chat-status-dot"></span>
                    <span id="ai-chat-status-text">Sẵn sàng</span>
                </div>
            </div>
            <button type="button" class="ai-chat-header-btn" id="ai-chart-toggle" title="Xem biểu đồ">📊</button>
            <button type="button" class="ai-chat-header-btn" id="ai-chat-clear" title="Xoá lịch sử">🗑</button>
            <button type="button" class="ai-chat-header-btn" id="ai-chat-close" title="Đóng">✕</button>
        </div>
        {{-- Chart panel inside chatbot --}}
        <div id="ai-chart-panel" style="display:none; padding:12px 14px; border-bottom:1px solid var(--border-default,#e5e7eb); background:var(--surface-alt,#f9fafb);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <strong style="font-size:13px;">📊 Biểu đồ nhanh</strong>
                <select id="ai-chart-select" style="font-size:12px; padding:3px 8px; border-radius:6px; border:1px solid #d1d5db; background:#fff; cursor:pointer;">
                    <option value="department">Nhân viên / Phòng ban</option>
                    <option value="leave">Trạng thái nghỉ phép</option>
                    <option value="attendance">Chấm công 7 ngày</option>
                    <option value="recruitment">Tuyển dụng</option>
                    <option value="payroll">Lương tháng này</option>
                </select>
            </div>
            <div id="ai-chart-loading" style="text-align:center; padding:20px; color:#9ca3af; font-size:12px;">Đang tải…</div>
            <canvas id="ai-chart-canvas" width="320" height="180" style="display:none; max-height:180px;"></canvas>
        </div>
        <div class="ai-chat-messages" id="ai-chat-messages">
            <div class="ai-welcome" id="ai-welcome-state">
                <div class="ai-welcome-icon">🤖</div>
                <div class="ai-welcome-title">Xin chào! Tôi là Trợ lý AI</div>
                <div class="ai-welcome-sub">Hỏi tôi về nhân viên, nghỉ phép, hợp đồng, lương, chấm công hoặc bất cứ điều gì trong hệ thống.</div>
            </div>
        </div>
        <div class="ai-suggestions" id="ai-suggestions"></div>
        <div id="ai-draft-zone"></div>
        <div class="ai-chat-input-wrap">
            <textarea id="ai-chat-input" placeholder="Nhập câu hỏi…" rows="1" maxlength="900" autocomplete="off"></textarea>
            <button type="button" id="ai-chat-send" title="Gửi">➤</button>
        </div>
    </div>

    <button type="button" id="ai-chat-fab" aria-label="Mở trợ lý AI">
        <span id="ai-chat-fab-icon-open">🤖</span>
        <span id="ai-chat-fab-icon-close">✕</span>
        <span id="ai-chat-badge"></span>
    </button>

    <script src="{{ asset('assets/js/chatbot-widget.js') }}?v=20260628-1" defer></script>
    @endif
    {{-- ===== End AI Chat Widget ===== --}}

    <script>
    (function () {
        var tokenMeta = document.querySelector('meta[name="csrf-token"]');
        if (!tokenMeta) return;

        var token = tokenMeta.getAttribute('content') || '';
        if (!token) return;

        var postForms = document.querySelectorAll('form[method="post"], form[method="POST"]');
        postForms.forEach(function (form) {
            if (form.querySelector('input[name="_csrf_token"]')) return;

            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = '_csrf_token';
            hidden.value = token;
            form.appendChild(hidden);
        });

        if (window.jQuery && typeof window.jQuery.ajaxSetup === 'function') {
            window.jQuery.ajaxSetup({
                headers: {
                    'X-CSRF-Token': token
                }
            });
        }
    })();
    </script>

    @stack('page_scripts')
</body>
</html>