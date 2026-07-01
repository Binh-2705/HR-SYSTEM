@php $title = 'Gia hạn hợp đồng' @endphp
@php $subtitle = 'Gia hạn hợp đồng lao động trên hệ thống' @endphp
@extends('layouts.app')

@section('content')
@if (session('success'))
<section class="panel">
    <div class="flash-alert flash-success flash-inline">
        <div class="flash-title">Gia hạn hợp đồng thành công</div>
        <div>{{ session('success') }}</div>
        <div class="flash-hint">Bước kế tiếp: kiểm tra lịch sử lương và tình trạng hợp đồng mới.</div>
        <div class="flash-actions">
            <a class="btn btn-secondary" href="{{ route('hopdong.index') }}">Về danh sách hợp đồng</a>
        </div>
    </div>
</section>
@endif

@if ($errors->any())
<section class="panel">
    <div class="flash-alert flash-error flash-inline">
        <div class="flash-title">Không thể gia hạn hợp đồng</div>
        <div>{{ $errors->first() }}</div>
        <div class="flash-hint">Kiểm tra ngày bắt đầu/kết thúc và số hợp đồng mới trước khi thử lại.</div>
    </div>
</section>
@endif

<section class="panel">
    <div class="muted">Hợp đồng gốc</div>
    <div class="metric-strong top-gap-sm">{{ $contract['SoHopDong'] }} · {{ $contract['HoTen'] }}</div>
    <div class="muted top-gap-sm">Bậc hiện tại: {{ $contract['TenBac'] }} · Lương hiện tại: {{ number_format($contract['LuongThucTe'], 0, ',', '.') }} VNĐ</div>
</section>

<section class="panel">
    <form method="post" action="{{ route('hopdong.renew.store', ['contract' => $contract['MaHopDong']]) }}">
        @csrf
        <div class="field-grid">
            <div>
                <label for="SoHopDong">Số hợp đồng mới</label>
                <input id="SoHopDong" name="SoHopDong" value="{{ old('SoHopDong') }}" required>
            </div>
            <div>
                <label for="LoaiHopDong">Loại hợp đồng</label>
                <select id="LoaiHopDong" name="LoaiHopDong" required>
                    @foreach (['Thử việc', 'Xác định thời hạn', 'Không xác định thời hạn'] as $type)
                        <option value="{{ $type }}" @selected(old('LoaiHopDong', $contract['LoaiHopDong']) === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="NgayBatDau">Ngày bắt đầu mới</label>
                <input id="NgayBatDau" name="NgayBatDau" type="date" value="{{ old('NgayBatDau', now()->toDateString()) }}" required>
                @error('NgayBatDau')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div>
                <label for="NgayKetThuc">Ngày kết thúc mới</label>
                <input id="NgayKetThuc" name="NgayKetThuc" type="date" value="{{ old('NgayKetThuc') }}">
                @error('NgayKetThuc')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="full-span">
                <label for="GhiChu">Ghi chú</label>
                <textarea id="GhiChu" name="GhiChu">{{ old('GhiChu', 'Gia hạn từ hợp đồng số ' . $contract['SoHopDong']) }}</textarea>
            </div>
        </div>
        <div class="form-actions-bar">
            <button class="btn" type="submit">Xác nhận gia hạn</button>
            <a class="btn btn-secondary" href="{{ route('hopdong.index') }}">Hủy bỏ</a>
        </div>
    </form>
</section>

@push('page_scripts')
<script>
    (function () {
        const start = document.getElementById('NgayBatDau');
        const end = document.getElementById('NgayKetThuc');
        if (!start || !end) return;

        let errorEl = document.getElementById('NgayKetThuc-error');
        if (!errorEl) {
            errorEl = document.createElement('span');
            errorEl.id = 'NgayKetThuc-error';
            errorEl.className = 'field-error';
            errorEl.style.display = 'none';
            end.parentNode.appendChild(errorEl);
        }

        function showError(msg) {
            errorEl.textContent = msg;
            errorEl.style.display = 'block';
            end.style.borderColor = '#e3342f';
            end.setCustomValidity('invalid');
        }

        function clearError() {
            errorEl.style.display = 'none';
            end.style.borderColor = '';
            end.setCustomValidity('');
        }

        start.addEventListener('change', function () {
            if (start.value) {
                end.min = start.value;
            }
            if (end.value && start.value && end.value < start.value) {
                end.value = '';
                showError('Ngày kết thúc phải từ ngày bắt đầu trở đi.');
            } else {
                clearError();
            }
        });

        end.addEventListener('change', function () {
            if (end.value && start.value && end.value < start.value) {
                end.value = '';
                showError('Ngày kết thúc phải từ ngày bắt đầu trở đi.');
            } else {
                clearError();
            }
        });

        if (start.value) end.min = start.value;
    })();
</script>
@endpush
@endsection
@endsection