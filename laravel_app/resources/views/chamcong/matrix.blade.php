@php
    $title    = 'Bảng chấm công theo tháng';
    $subtitle = 'Tháng ' . $month . '/' . $year;
    $canCreate = in_array('them_chamcong',       session('quyen', []), true);
    $canExport = in_array('xuat_bang_cham_cong', session('quyen', []), true);
    $dayNames  = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
    $isSelfView = $isSelfView ?? false;
@endphp
@extends('layouts.app')

@push('styles')
<style>
.attendance-matrix-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
.attendance-matrix{border-collapse:collapse;font-size:.78rem;min-width:max-content;width:100%}
.attendance-matrix th,.attendance-matrix td{border:1px solid #e0e0e0;padding:4px 5px;text-align:center;white-space:nowrap}
.attendance-matrix thead th{background:#f5f6fa;font-weight:600;position:sticky;top:0;z-index:2}
.attendance-matrix th.col-manv,.attendance-matrix td.col-manv{min-width:60px}
.attendance-matrix th.col-name,.attendance-matrix td.col-name{min-width:160px;text-align:left;position:sticky;left:0;z-index:1;background:inherit}
.attendance-matrix thead th.col-name{z-index:3}
.attendance-matrix th.col-total,.attendance-matrix td.col-total{min-width:48px;font-weight:700;position:sticky;right:0;background:#f5f6fa}
.attendance-matrix th.weekend,.attendance-matrix td.weekend{background:#fafafa;color:#bbb}
.attendance-matrix tr.dept-row td{background:#eef2ff;font-weight:700;font-size:.82rem;text-align:left;padding:6px 10px;color:#3b4cb8;border-top:2px solid #c7d0f8}
.cc-dilam{color:#16a34a;font-weight:700}.cc-dimuon{color:#f59e0b;font-weight:700}.cc-nghiphep{color:#2563eb;font-weight:600}.cc-congtac{color:#7c3aed;font-weight:600}.cc-le{color:#d97706;font-weight:600}.cc-nkl{color:#dc2626;font-weight:600;font-size:.65rem}.cc-weekend{color:#d1d5db}
.cc-legend{display:flex;gap:16px;flex-wrap:wrap;margin-top:10px;font-size:.8rem}.cc-legend span{display:inline-flex;align-items:center;gap:4px}
.view-tabs{display:flex;gap:6px;margin-bottom:12px}.view-tabs a{padding:5px 14px;border-radius:6px;font-size:.82rem;border:1px solid #d1d5db;text-decoration:none;color:#374151}.view-tabs a.active{background:#3b4cb8;color:#fff;border-color:#3b4cb8}
.cc-cell{cursor:pointer;min-width:28px;transition:all .08s;background:#fff}.cc-cell:hover{background:#dbeafe}.cc-cell.saving{opacity:.4;pointer-events:none}
.cc-cell-select{width:100%;min-height:24px;padding:3px;font-size:.78rem;border:2px solid #3b4cb8;border-radius:4px;cursor:pointer;background:#fff;color:#000}
.cc-cell-select:focus{outline:none;box-shadow:0 0 0 3px rgba(59,76,184,.15)}
</style>
@endpush

@section('content')
<meta name="cell-save-url" content="{{ route('chamcong.matrix.cell') }}">
<meta name="csrf-token-val" content="{{ csrf_token() }}">

<section class="panel">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:12px">
        <h2 style="margin:0">📅 Bảng chấm công theo tháng</h2>
        <div class="view-tabs">
            <a href="{{ route('chamcong.index') }}">Danh sách</a>
            <a href="{{ route('chamcong.matrix', ['thang' => $month, 'nam' => $year]) }}" class="active">Bảng tháng</a>
        </div>
    </div>
    @if (!$isSelfView)
    <form method="get" action="{{ route('chamcong.matrix') }}">
        <div class="field-grid">
            <div>
                <label for="thang">Tháng</label>
                <input type="number" id="thang" name="thang" value="{{ $month }}" min="1" max="12" style="width:70px">
            </div>
            <div>
                <label for="nam">Năm</label>
                <input type="number" id="nam" name="nam" value="{{ $year }}" min="2000" max="2100" style="width:88px">
            </div>
            <div class="full-span button-row">
                <button type="submit" class="btn">🔍 Xem</button>
                @if ($canExport)
                    <a href="{{ route('chamcong.export-excel', ['thang' => $month, 'nam' => $year]) }}" class="btn btn-secondary">📤 Xuất Excel</a>
                @endif
            </div>
        </div>
    </form>
    @endif
</section>

<section class="panel">
    <div class="attendance-matrix-wrap">
        <table class="attendance-matrix" id="ccMatrix">
            <thead>
                <tr>
                    <th rowspan="2" class="col-manv">Mã NV</th>
                    <th rowspan="2" class="col-name">Nhân viên</th>
                    @foreach ($days as $d => $dow)
                        <th class="{{ ($dow === 0 || $dow === 6) ? 'weekend' : '' }}">{{ $d }}</th>
                    @endforeach
                    <th rowspan="2" class="col-total">Tổng</th>
                </tr>
                <tr>
                    @foreach ($days as $d => $dow)
                        <th class="{{ ($dow === 0 || $dow === 6) ? 'weekend' : '' }}">{{ $dayNames[$dow] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($matrix as $dept => $employees)
                    <tr class="dept-row">
                        <td colspan="{{ count($days) + 3 }}">🏢 {{ $dept }}</td>
                    </tr>
                    @foreach ($employees as $emp)
                        @php
                            $ngay  = $emp['Ngay'] ?? [];
                            $total = 0;
                            foreach ($ngay as $cell) {
                                $s = is_array($cell) ? ($cell['s'] ?? '') : (string) $cell;
                                if (in_array($s, ['Di lam', 'Di muon', 'Nghi phep', 'Cong tac', 'Le'], true)) {
                                    $total++;
                                }
                            }
                        @endphp
                        <tr>
                            <td class="col-manv">{{ $emp['MaNV'] }}</td>
                            <td class="col-name">{{ $emp['HoTen'] }}</td>
                            @foreach ($days as $d => $dow)
                                @php
                                    $key    = str_pad((string) $d, 2, '0', STR_PAD_LEFT);
                                    $cell   = $ngay[$key] ?? null;
                                    $status = is_array($cell) ? ($cell['s'] ?? '') : (string) ($cell ?? '');
                                    $macc   = is_array($cell) ? (int) ($cell['id'] ?? 0) : 0;
                                    $isWE   = ($dow === 0 || $dow === 6);
                                    $ngayStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                                    $cssClass = $isWE ? 'weekend' : '';
                                    if ($status === 'Di lam') { $icon = '✓'; $cssIcon = 'cc-dilam'; }
                                    elseif ($status === 'Di muon') { $icon = 'M'; $cssIcon = 'cc-dimuon'; }
                                    elseif ($status === 'Nghi phep') { $icon = 'P'; $cssIcon = 'cc-nghiphep'; }
                                    elseif ($status === 'Cong tac') { $icon = 'CT'; $cssIcon = 'cc-congtac'; }
                                    elseif ($status === 'Le') { $icon = 'L'; $cssIcon = 'cc-le'; }
                                    elseif ($status === 'Nghi khong luong') { $icon = 'NKL'; $cssIcon = 'cc-nkl'; }
                                    else { $icon = null; $cssIcon = null; }
                                @endphp
                                <td class="cc-cell {{ $cssClass }}"
                                    data-manv="{{ $emp['MaNV'] }}"
                                    data-hoten="{{ $emp['HoTen'] }}"
                                    data-ngay="{{ $ngayStr }}"
                                    data-macc="{{ $macc }}"
                                    data-status="{{ $status }}">
                                    @if ($icon)
                                        <span class="{{ $cssIcon }}">{{ $icon }}</span>
                                    @elseif ($isWE)
                                        <span class="cc-weekend">+</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="col-total" data-total-row>{{ $total }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="{{ count($days) + 3 }}" style="text-align:center;padding:24px;color:#888">
                            Không có dữ liệu chấm công cho tháng {{ $month }}/{{ $year }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="cc-legend" style="margin-top:12px">
        <span><span class="cc-dilam">✓</span> X = Đi làm</span>
        <span><span class="cc-dimuon">M</span> M = Đi muộn</span>
        <span><span class="cc-nghiphep">P</span> P = Nghỉ phép</span>
        <span><span class="cc-congtac">CT</span> CT = Công tác</span>
        <span><span class="cc-le">L</span> L = Nghỉ lễ</span>
        <span><span class="cc-nkl">NKL</span> NKL = Nghỉ không lương</span>
        @if ($canCreate)
            <span style="color:#888;font-style:italic">· Bấm ô để chỉnh sửa trực tiếp trên bảng</span>
        @endif
    </div>
</section>

@if ($canCreate)
@push('page_scripts')
<script>
(function () {
    'use strict';
    var SAVE_URL = document.querySelector('meta[name="cell-save-url"]').content;
    var CSRF     = document.querySelector('meta[name="csrf-token-val"]').content;

    var STATUS_LIST = [
        { val: 'Di lam',           icon: '✓',   code: 'X' },
        { val: 'Di muon',          icon: 'M',   code: 'M' },
        { val: 'Nghi phep',        icon: 'P',   code: 'P' },
        { val: 'Cong tac',         icon: 'CT',  code: 'CT' },
        { val: 'Le',               icon: 'L',   code: 'L' },
        { val: 'Nghi khong luong', icon: 'NKL',code: 'NKL' },
    ];

    var STATUS_MAP = {};
    STATUS_LIST.forEach(function (s) { STATUS_MAP[s.val] = s; });

    var CSS_MAP = {
        'Di lam': 'cc-dilam', 'Di muon': 'cc-dimuon', 'Nghi phep': 'cc-nghiphep',
        'Cong tac': 'cc-congtac', 'Le': 'cc-le', 'Nghi khong luong': 'cc-nkl'
    };

    /* Click cell → transform to select dropdown */
    document.getElementById('ccMatrix').addEventListener('click', function (e) {
        var td = e.target.closest('.cc-cell');
        if (!td || td.querySelector('select')) return; /* already editing */
        e.stopPropagation();

        var currentStatus = td.dataset.status;
        var select = document.createElement('select');
        select.className = 'cc-cell-select';
        select.innerHTML = '<option value="">(trống)</option>';
        
        STATUS_LIST.forEach(function (s) {
            var opt = document.createElement('option');
            opt.value = s.val;
            opt.textContent = s.code + ' = ' + s.val;
            if (s.val === currentStatus) opt.selected = true;
            select.appendChild(opt);
        });
        
        var optDel = document.createElement('option');
        optDel.value = '';
        optDel.textContent = '━━ Xóa ô';
        if (currentStatus === '') optDel.selected = true;
        select.appendChild(optDel);

        td.innerHTML = '';
        td.appendChild(select);
        select.focus();
        select.click();

        /* Save on change */
        select.addEventListener('change', function () {
            var newStatus = select.value;
            td.classList.add('saving');
            
            fetch(SAVE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': CSRF
                },
                body: JSON.stringify({
                    MaCC:      parseInt(td.dataset.macc) || 0,
                    MaNV:      parseInt(td.dataset.manv),
                    Ngay:      td.dataset.ngay,
                    TrangThai: newStatus,
                }),
            })
            .then(function (r) {
                return r.text().then(function (raw) {
                    var json;
                    try {
                        json = JSON.parse(raw);
                    } catch (e) {
                        throw new Error('Phản hồi không phải JSON (HTTP ' + r.status + '). Vui lòng tải lại trang hoặc đăng nhập lại.');
                    }

                    if (!r.ok || !json.ok) {
                        throw new Error(json.message || ('Lỗi máy chủ (HTTP ' + r.status + ')'));
                    }

                    return json;
                });
            })
            .then(function (json) {
                td.dataset.status = newStatus;
                td.dataset.macc   = json.MaCC || 0;

                /* Re-render cell */
                var isWE = td.classList.contains('weekend');
                td.classList.remove('saving');
                if (newStatus && STATUS_MAP[newStatus]) {
                    var s = STATUS_MAP[newStatus];
                    var css = CSS_MAP[newStatus] || '';
                    td.innerHTML = '<span class="' + css + '">' + s.icon + '</span>';
                } else {
                    td.innerHTML = isWE ? '<span class="cc-weekend">+</span>' : '';
                }

                /* Update row total */
                var row = td.closest('tr'), total = 0;
                row.querySelectorAll('.cc-cell').forEach(function (c) {
                    if (['Di lam','Di muon','Nghi phep','Cong tac','Le'].indexOf(c.dataset.status) !== -1) total++;
                });
                var totCell = row.querySelector('[data-total-row]');
                if (totCell) totCell.textContent = total;
            })
            .catch(function (err) { alert('Không lưu được: ' + err.message); })
            .finally(function () { td.classList.remove('saving'); });
        });

        /* Blur → close select, restore original content */
        select.addEventListener('blur', function () {
            setTimeout(function () {
                if (td.querySelector('select')) {
                    var status = td.dataset.status;
                    var isWE = td.classList.contains('weekend');
                    if (status && STATUS_MAP[status]) {
                        var s = STATUS_MAP[status];
                        var css = CSS_MAP[status] || '';
                        td.innerHTML = '<span class="' + css + '">' + s.icon + '</span>';
                    } else {
                        td.innerHTML = isWE ? '<span class="cc-weekend">+</span>' : '';
                    }
                }
            }, 50);
        });
    });
})();
</script>
@endpush
@endif

@endsection
