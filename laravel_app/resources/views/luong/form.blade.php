@php $title = $mode === 'create' ? 'Thêm bảng lương' : 'Sửa bảng lương' @endphp
@php $subtitle = 'Tính toán và điều chỉnh bảng lương' @endphp
@php $initialLuongCoSo = old('LuongCoSo', $record['LuongCoSo'] ?? '') @endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <form method="post" action="{{ $mode === 'create' ? route('luong.store') : route('luong.update', ['payroll' => $record['MaBL']]) }}">
            @csrf
            @if ($mode === 'edit')
                @method('PUT')
            @endif

            <div class="field-grid">
                <div>
                    <label for="MaNV">Chọn nhân viên</label>
                    <select id="MaNV" name="MaNV" required>
                        <option value="">-- Chọn nhân viên --</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->MaNV }}" @selected((string) old('MaNV', $record['MaNV'] ?? '') === (string) $employee->MaNV)>{{ $employee->HoTen }} ({{ $employee->MaNV }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="Thang">Tháng</label>
                    <input id="Thang" type="number" name="Thang" min="1" max="12" value="{{ old('Thang', $record['Thang'] ?? now()->month) }}" required>
                </div>
                <div>
                    <label for="Nam">Năm</label>
                    <input id="Nam" type="number" name="Nam" min="2000" max="2100" value="{{ old('Nam', $record['Nam'] ?? now()->year) }}" required>
                </div>
                <div>
                    <label for="LuongCoSo">Lương cơ bản</label>
                    <select id="LuongCoSo" name="LuongCoSo">
                        <option value="">-- Chọn lương cơ bản --</option>
                        @if ($initialLuongCoSo !== null && $initialLuongCoSo !== '')
                            <option value="{{ $initialLuongCoSo }}" selected>{{ number_format((float) $initialLuongCoSo, 0, ',', '.') }} VNĐ</option>
                        @endif
                    </select>
                    <div class="helper-text" style="margin-top:4px;color:#6b7280;font-size:0.82rem;">Chọn nhân viên + tháng/năm để tự tải lương cơ bản tương ứng.</div>
                    <div id="salaryComponentsNotice" style="margin-top:4px;color:#dc2626;font-size:0.82rem;display:none;"></div>
                </div>
                <div>
                    <label for="HeSoLuong">Hệ số lương</label>
                    <input id="HeSoLuong" type="number" step="0.01" name="HeSoLuong" value="{{ old('HeSoLuong', $record['HeSoLuong'] ?? '') }}">
                </div>
                <div>
                    <label for="HeSoChucVu">Hệ số chức vụ</label>
                    <input id="HeSoChucVu" type="number" step="0.01" name="HeSoChucVu" value="{{ old('HeSoChucVu', $record['HeSoChucVu'] ?? '') }}">
                </div>
                <div>
                    <label for="PhuCap">Phụ cấp (VNĐ)</label>
                    <input id="PhuCap" type="number" step="0.01" name="PhuCap" value="{{ old('PhuCap', $record['PhuCap'] ?? '') }}">
                </div>
                <div>
                    <label for="Thuong">Thưởng (VNĐ)</label>
                    <input id="Thuong" type="number" step="0.01" name="Thuong" value="{{ old('Thuong', $record['Thuong'] ?? '') }}">
                </div>
                <div>
                    <label for="Phat">Phạt (VNĐ)</label>
                    <input id="Phat" type="number" step="0.01" name="Phat" value="{{ old('Phat', $record['Phat'] ?? '') }}">
                </div>
                <div>
                    <label for="BaoHiem">Bảo hiểm (VNĐ)</label>
                    <input id="BaoHiem" type="number" step="0.01" name="BaoHiem" value="{{ old('BaoHiem', $record['BaoHiem'] ?? '') }}">
                </div>
                <div>
                    <label for="TongLuong">Tổng lương (VNĐ)</label>
                    <input id="TongLuong" type="number" step="1" name="TongLuong" value="{{ old('TongLuong', $record['TongLuong'] ?? '') }}">
                </div>
                <div>
                    <label for="TrangThai">Trạng thái</label>
                    <input id="TrangThai" type="text" name="TrangThai" value="{{ old('TrangThai', $record['TrangThai'] ?? 'Chưa chốt') }}" required>
                </div>
                <div>
                    <label for="NgayTinh">Ngày tính</label>
                    <input id="NgayTinh" type="date" name="NgayTinh" value="{{ old('NgayTinh', $record['NgayTinh'] ?? now()->toDateString()) }}">
                </div>
            </div>

            <div class="form-actions-bar">
                <button type="submit" class="btn">Lưu bảng lương</button>
                <a href="{{ route('luong.index') }}" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </section>
@endsection

@push('page_scripts')
<meta name="payroll-components-url" content="{{ route('luong.salary-components') }}">
<script>
(function () {
    const endpoint = document.querySelector('meta[name="payroll-components-url"]').content;
    const employeeField = document.getElementById('MaNV');
    const monthField = document.getElementById('Thang');
    const yearField = document.getElementById('Nam');

    const salaryBaseField = document.getElementById('LuongCoSo');
    const salaryFactorField = document.getElementById('HeSoLuong');
    const positionFactorField = document.getElementById('HeSoChucVu');
    const allowanceField = document.getElementById('PhuCap');
    const bonusField = document.getElementById('Thuong');
    const penaltyField = document.getElementById('Phat');
    const insuranceField = document.getElementById('BaoHiem');
    const totalSalaryField = document.getElementById('TongLuong');
    const notice = document.getElementById('salaryComponentsNotice');

    function toNumberValue(value) {
        if (value === null || value === undefined || value === '') {
            return '';
        }

        const num = Number(value);
        return Number.isFinite(num) ? num : '';
    }

    function updateSalaryBaseOptions(options, selectedValue) {
        const currentValue = salaryBaseField.value;
        salaryBaseField.innerHTML = '<option value="">-- Chọn lương cơ bản --</option>';

        if (Array.isArray(options)) {
            options.forEach(function (item) {
                const value = toNumberValue(item && item.value);
                if (value === '') {
                    return;
                }

                const label = item && item.label ? item.label : (new Intl.NumberFormat('vi-VN').format(value) + ' VNĐ');
                const option = document.createElement('option');
                option.value = String(value);
                option.textContent = label;
                salaryBaseField.appendChild(option);
            });
        }

        const targetValue = selectedValue !== '' && selectedValue !== null && selectedValue !== undefined
            ? String(selectedValue)
            : currentValue;

        if (targetValue !== '') {
            const matched = Array.from(salaryBaseField.options).find(function (opt) {
                return opt.value === targetValue;
            });

            if (!matched) {
                const fallbackOption = document.createElement('option');
                fallbackOption.value = targetValue;
                fallbackOption.textContent = new Intl.NumberFormat('vi-VN').format(Number(targetValue) || 0) + ' VNĐ';
                salaryBaseField.appendChild(fallbackOption);
            }

            salaryBaseField.value = targetValue;
        }
    }

    function fillFields(data) {
        const payload = data || {};

        updateSalaryBaseOptions(payload.LuongCoSoOptions || [], payload.LuongCoSo);
        salaryFactorField.value = toNumberValue(payload.HeSoLuong);
        positionFactorField.value = toNumberValue(payload.HeSoChucVu);
        allowanceField.value = toNumberValue(payload.PhuCap);
        bonusField.value = toNumberValue(payload.Thuong);
        penaltyField.value = toNumberValue(payload.Phat);
        insuranceField.value = toNumberValue(payload.BaoHiem);
        totalSalaryField.value = toNumberValue(payload.TongLuong);
    }

    let inFlight = null;

    async function fetchComponents() {
        const maNV = employeeField.value;
        const thang = monthField.value;
        const nam = yearField.value;

        if (!maNV || !thang || !nam) {
            notice.style.display = 'none';
            return;
        }

        if (inFlight) {
            inFlight.abort();
        }

        const controller = new AbortController();
        inFlight = controller;

        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('MaNV', maNV);
        url.searchParams.set('Thang', thang);
        url.searchParams.set('Nam', nam);

        try {
            const response = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json' },
                signal: controller.signal,
            });

            if (!response.ok) {
                notice.textContent = 'Không tải được dữ liệu lương tương ứng. Vui lòng thử lại.';
                notice.style.display = 'block';
                return;
            }

            const json = await response.json();
            const data = json && json.data ? json.data : json;
            fillFields(data);
            notice.style.display = 'none';
        } catch (error) {
            if (error && error.name === 'AbortError') {
                return;
            }

            notice.textContent = 'Không tải được dữ liệu lương tương ứng. Vui lòng thử lại.';
            notice.style.display = 'block';
        } finally {
            if (inFlight === controller) {
                inFlight = null;
            }
        }
    }

    employeeField.addEventListener('change', fetchComponents);
    monthField.addEventListener('change', fetchComponents);
    yearField.addEventListener('change', fetchComponents);

    if (employeeField.value && monthField.value && yearField.value) {
        fetchComponents();
    }
})();
</script>
@endpush