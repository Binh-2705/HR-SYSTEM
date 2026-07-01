@php
    $title = $mode === 'create' ? 'Thêm đợt tuyển dụng' : 'Sửa đợt tuyển dụng';
    $subtitle = 'Cập nhật nhu cầu và trạng thái tuyển dụng';
@endphp
@extends('layouts.app')

@section('content')
<section class="panel">
    <form method="post" action="{{ $mode === 'create' ? route('tuyendung.store') : route('tuyendung.update', ['recruitment' => $campaign['MaDTD']]) }}">
        @csrf
        @if ($mode === 'edit') @method('PUT') @endif
        <div class="field-grid">
            <div><label for="TenDotTuyenDung">Tên đợt</label><input id="TenDotTuyenDung" name="TenDotTuyenDung" value="{{ old('TenDotTuyenDung', $campaign['TenDotTuyenDung'] ?? '') }}" required></div>
            <div><label for="ViTriTuyenDung">Vị trí</label><input id="ViTriTuyenDung" name="ViTriTuyenDung" value="{{ old('ViTriTuyenDung', $campaign['ViTriTuyenDung'] ?? '') }}" required></div>
            <div><label for="SoLuong">Số lượng</label><input id="SoLuong" name="SoLuong" type="number" min="1" value="{{ old('SoLuong', $campaign['SoLuong'] ?? 1) }}" required></div>
            <div><label for="TrangThai">Trạng thái</label><select id="TrangThai" name="TrangThai" required><option value="Đang tuyển" @selected(old('TrangThai', $campaign['TrangThai'] ?? 'Đang tuyển') === 'Đang tuyển')>Đang tuyển</option><option value="Đã kết thúc" @selected(old('TrangThai', $campaign['TrangThai'] ?? '') === 'Đã kết thúc')>Đã kết thúc</option></select></div>
            <div><label for="TuNgay">Từ ngày</label><input id="TuNgay" name="TuNgay" type="date" value="{{ old('TuNgay', $campaign['TuNgay'] ?? '') }}" required></div>
            <div><label for="DenNgay">Đến ngày</label><input id="DenNgay" name="DenNgay" type="date" value="{{ old('DenNgay', $campaign['DenNgay'] ?? '') }}"></div>
            <div class="full-span"><label for="MoTa">Mô tả</label><textarea id="MoTa" name="MoTa">{{ old('MoTa', $campaign['MoTa'] ?? '') }}</textarea></div>
        </div>
        <div class="form-actions-bar"><button class="btn" type="submit">{{ $mode === 'create' ? 'Tạo đợt tuyển dụng' : 'Cập nhật đợt tuyển dụng' }}</button><a class="btn btn-secondary" href="{{ route('tuyendung.index') }}">Về danh sách</a></div>
    </form>
</section>
@endsection