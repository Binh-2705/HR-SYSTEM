@php $title = 'Bảng điều khiển' @endphp
@php $subtitle = 'Tổng quan hệ thống nhân sự và các phân hệ hiện có' @endphp
@extends('layouts.app')

@section('content')
    <div class="header-top">
        <div>
            <h1 data-i18n="home.dashboard_title">Bảng điều khiển</h1>
            <p data-i18n="home.dashboard_subtitle">Quản lý tổng quan hệ thống nhân sự</p>
            <p class="muted top-gap-md">Tài khoản: <strong>{{ $taiKhoan['TenDangNhap'] ?? 'Chưa có' }}</strong></p>
        </div>

        <div class="header-actions">
            <form method="get" action="{{ route('search.index') }}" class="search-box">
                <input type="text" name="q" placeholder="🔎 Tìm nhân viên..." data-i18n-placeholder="home.search_employee_placeholder">
            </form>
        </div>
    </div>

    <div class="stats">
        @forelse (array_slice($metricCards, 0, 4) as $index => $card)
            <div class="stat-box">
                <div class="stat-top">
                    <span class="stat-icon">{{ ['👥', '🏢', '📝', '💼'][$index] ?? '📌' }}</span>
                    <span class="stat-label">{{ $card['label'] }}</span>
                </div>
                <h2>{{ number_format($card['value']) }}</h2>
                <p>{{ $card['label'] }}</p>
                @php $signalNote = $quickSignals[$index]['note'] ?? null @endphp
                @if ($signalNote)
                    <span class="stat-note info">{{ $signalNote }}</span>
                @endif
            </div>
        @empty
            <div class="stat-box">
                <div class="stat-top"><span class="stat-icon">ℹ</span><span class="stat-label">Thông báo</span></div>
                <h2>0</h2>
                <p>Không có số liệu phù hợp với quyền hiện tại</p>
            </div>
        @endforelse
    </div>

    <div class="chart-container" id="dashboardCharts">
        <div style="grid-column:1/-1; display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
            <span class="muted" id="chartCacheNote" style="font-size:12px;"></span>
            <button type="button" id="btnRefreshCharts" class="btn btn-secondary" style="font-size:12px; padding:4px 12px;">↻ Làm mới</button>
        </div>
        <div class="chart-box" id="chartBox-department">
            <h3>🏢 Nhân viên theo phòng ban</h3>
            <div class="chart-loading"><span class="chart-spinner"></span> Đang tải…</div>
            <canvas id="chartDepartment" style="display:none"></canvas>
        </div>
        <div class="chart-box" id="chartBox-leave">
            <h3>📋 Trạng thái nghỉ phép</h3>
            <div class="chart-loading"><span class="chart-spinner"></span> Đang tải…</div>
            <canvas id="chartLeave" style="display:none"></canvas>
        </div>
        <div class="chart-box" id="chartBox-attendance">
            <h3>📅 Chấm công 7 ngày gần nhất</h3>
            <div class="chart-loading"><span class="chart-spinner"></span> Đang tải…</div>
            <canvas id="chartAttendance" style="display:none"></canvas>
        </div>
        <div class="chart-box" id="chartBox-recruitment">
            <h3>🎯 Tuyển dụng theo trạng thái</h3>
            <div class="chart-loading"><span class="chart-spinner"></span> Đang tải…</div>
            <canvas id="chartRecruitment" style="display:none"></canvas>
        </div>
    </div>

    <div class="notification-box">
        <h2>🔔 Hoạt động gần đây</h2>
        <div class="notification-grid">
            @forelse ($recentActivity as $activity)
                <a href="{{ $activity['href'] }}" class="notification-item success link-reset-block">
                    <div class="notify-main">{{ $activity['title'] }}</div>
                    <div class="notify-meta">
                        <span class="notify-time">{{ $activity['description'] }}</span>
                        <span class="notify-state unread">{{ $activity['at'] }}</span>
                    </div>
                </a>
            @empty
                <div class="notification-item neutral">✅ Chưa có hoạt động gần đây</div>
            @endforelse
        </div>
    </div>

    <section class="dashboard">
        @forelse ($moduleLinks as $link)
            <div class="card">
                <div class="card-title">
                    <span class="card-icon">{{ $link['secondary'] ? '📂' : '🚀' }}</span>
                    <h3>{{ $link['label'] }}</h3>
                </div>
                <p class="card-meta">Phân hệ sẵn sàng truy cập</p>
                <a href="{{ $link['route'] }}">Mở chi tiết</a>
            </div>
        @empty
            <div class="card">
                <div class="card-title"><span class="card-icon">ℹ</span><h3>Không có phân hệ</h3></div>
                <p class="card-meta">Không có phân hệ nào được cấp quyền hiển thị.</p>
            </div>
        @endforelse
    </section>

    <div id="dashboardChartData"
         data-charts-url="{{ route('dashboard.charts') }}"
         data-inline-charts="{{ json_encode($inlineCharts ?? []) }}"
         hidden></div>
@endsection

@push('page_scripts')
<script>
(function () {
    var PALETTE = ['#4f46e5','#7c3aed','#2563eb','#0891b2','#059669','#d97706','#dc2626','#db2777'];
    var CACHE_KEY = 'hrm_dash_charts_v2';
    var CACHE_TTL = 300000; // 5 phút
    var chartJsUrl = 'https://cdn.jsdelivr.net/npm/chart.js';
    var chartJsPromise = null;

    var dataEl    = document.getElementById('dashboardChartData');
    var cacheNote = document.getElementById('chartCacheNote');
    var refreshBtn = document.getElementById('btnRefreshCharts');
    var chartsUrl = dataEl ? dataEl.getAttribute('data-charts-url') : null;

    function ensureChartJs() {
        if (window.Chart) {
            return Promise.resolve();
        }

        if (chartJsPromise) {
            return chartJsPromise;
        }

        chartJsPromise = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = chartJsUrl;
            script.async = true;
            script.onload = function () { resolve(); };
            script.onerror = function () { reject(new Error('Failed to load Chart.js')); };
            document.head.appendChild(script);
        });

        return chartJsPromise;
    }

    function showChart(boxId, canvasId) {
        var box    = document.getElementById(boxId);
        var canvas = document.getElementById(canvasId);
        var loader = box ? box.querySelector('.chart-loading') : null;
        if (loader) loader.style.display = 'none';
        if (canvas) canvas.style.display = '';
        return canvas;
    }

    function showEmpty(boxId) {
        var box = document.getElementById(boxId);
        if (!box) return;
        var loader = box.querySelector('.chart-loading');
        if (loader) loader.innerHTML = '<span class="muted">Không có dữ liệu</span>';
    }

    function makeBar(el, labels, values, label) {
        new Chart(el, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{ label: label || '', data: values, backgroundColor: PALETTE.slice(0, values.length), borderRadius: 6, borderSkipped: false }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    }

    function makeDoughnut(el, labels, values) {
        new Chart(el, {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: values, backgroundColor: PALETTE.slice(0, values.length), hoverOffset: 8 }] },
            options: { responsive: true, cutout: '62%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
        });
    }

    function makeLine(el, labels, values) {
        new Chart(el, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{ label: 'Lượt chấm công', data: values, borderColor: '#4f46e5', backgroundColor: 'rgba(79,70,229,.12)', borderWidth: 2, pointRadius: 4, pointBackgroundColor: '#4f46e5', fill: true, tension: 0.4 }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    }

    function renderCharts(c) {
        if (c.department && c.department.labels && c.department.labels.length) {
            makeBar(showChart('chartBox-department', 'chartDepartment'), c.department.labels, c.department.values, 'Nhân viên');
        } else { showEmpty('chartBox-department'); }

        if (c.leave && c.leave.labels && c.leave.labels.length) {
            makeDoughnut(showChart('chartBox-leave', 'chartLeave'), c.leave.labels, c.leave.values);
        } else { showEmpty('chartBox-leave'); }

        if (c.attendance && c.attendance.labels && c.attendance.labels.length) {
            makeLine(showChart('chartBox-attendance', 'chartAttendance'), c.attendance.labels, c.attendance.values);
        } else { showEmpty('chartBox-attendance'); }

        if (c.recruitment && c.recruitment.labels && c.recruitment.labels.length) {
            makeDoughnut(showChart('chartBox-recruitment', 'chartRecruitment'), c.recruitment.labels, c.recruitment.values);
        } else if (c.payroll && c.payroll.labels && c.payroll.labels.length) {
            var h = document.querySelector('#chartBox-recruitment h3');
            if (h) h.textContent = '💰 Lương theo trạng thái';
            makeBar(showChart('chartBox-recruitment', 'chartRecruitment'), c.payroll.labels, c.payroll.values, 'Bảng lương');
        } else { showEmpty('chartBox-recruitment'); }
    }

    function fetchAndRender(forceRefresh) {
        if (!chartsUrl) return;
        ['chartBox-department','chartBox-leave','chartBox-attendance','chartBox-recruitment'].forEach(function(id) {
            var box = document.getElementById(id);
            if (!box) return;
            var loader = box.querySelector('.chart-loading');
            var canvas = box.querySelector('canvas');
            if (loader) { loader.style.display = ''; loader.innerHTML = '<span class="chart-spinner"></span> Đang tải…'; }
            if (canvas) canvas.style.display = 'none';
        });
        fetch((forceRefresh ? chartsUrl + '?refresh=1' : chartsUrl), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.ok) return;
                var c = res.charts || {};
                try { sessionStorage.setItem(CACHE_KEY, JSON.stringify({ charts: c, ts: Date.now() })); } catch(e) {}
                ensureChartJs().then(function() {
                    renderCharts(c);
                    if (cacheNote) cacheNote.textContent = 'Cập nhật lúc ' + new Date().toLocaleTimeString('vi-VN');
                }).catch(function() {
                    ['chartBox-department','chartBox-leave','chartBox-attendance','chartBox-recruitment'].forEach(showEmpty);
                });
            })
            .catch(function() {
                ['chartBox-department','chartBox-leave','chartBox-attendance','chartBox-recruitment'].forEach(showEmpty);
            });
    }

    // --- Khởi tạo ---
    // 1. Thử sessionStorage (lần quay lại trang trong session)
    try {
        var cached = JSON.parse(sessionStorage.getItem(CACHE_KEY) || 'null');
        if (cached && (Date.now() - cached.ts) < CACHE_TTL) {
            ensureChartJs().then(function() {
                renderCharts(cached.charts);
                if (cacheNote) cacheNote.textContent = 'Dữ liệu từ cache · ' + new Date(cached.ts).toLocaleTimeString('vi-VN');
            }).catch(function() {
                ['chartBox-department','chartBox-leave','chartBox-attendance','chartBox-recruitment'].forEach(showEmpty);
            });
        } else {
            throw new Error('stale');
        }
    } catch(e) {
        // 2. Dùng data inline từ server (không cần AJAX)
        try {
            var inline = JSON.parse((dataEl ? dataEl.getAttribute('data-inline-charts') : null) || 'null');
            if (inline && typeof inline === 'object' && Object.keys(inline).length > 0) {
                ensureChartJs().then(function() {
                    renderCharts(inline);
                    try { sessionStorage.setItem(CACHE_KEY, JSON.stringify({ charts: inline, ts: Date.now() })); } catch(se) {}
                    if (cacheNote) cacheNote.textContent = 'Tải từ server · ' + new Date().toLocaleTimeString('vi-VN');
                }).catch(function() {
                    ['chartBox-department','chartBox-leave','chartBox-attendance','chartBox-recruitment'].forEach(showEmpty);
                });
            } else {
                fetchAndRender(false);
            }
        } catch(pe) {
            fetchAndRender(false);
        }
    }

    // Nút làm mới
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            refreshBtn.disabled = true;
            refreshBtn.textContent = '…';
            try { sessionStorage.removeItem(CACHE_KEY); } catch(e) {}
            fetchAndRender(true);
            setTimeout(function() { refreshBtn.disabled = false; refreshBtn.textContent = '↻ Làm mới'; }, 3000);
        });
    }
})();
</script>
@endpush
