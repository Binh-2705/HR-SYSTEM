@php
    $title = $mode === 'create' ? 'Thêm khóa đào tạo' : 'Sửa khóa đào tạo';
    $subtitle = 'Cập nhật thông tin khóa đào tạo trên hệ thống';
@endphp
@extends('layouts.app')

@section('content')
<section class="panel">
    <form method="post" action="{{ $mode === 'create' ? route('daotao.store') : route('daotao.update', ['training' => $course['MaKDT']]) }}">
        @csrf
        @if ($mode === 'edit') @method('PUT') @endif
        <div class="field-grid">
            <div><label for="TenKhoaDaoTao">Tên khóa</label><input id="TenKhoaDaoTao" name="TenKhoaDaoTao" value="{{ old('TenKhoaDaoTao', $course['TenKhoaDaoTao'] ?? '') }}" required></div>
            <div><label for="DonViToChuc">Đơn vị tổ chức</label><input id="DonViToChuc" name="DonViToChuc" value="{{ old('DonViToChuc', $course['DonViToChuc'] ?? '') }}"></div>
            <div><label for="TuNgay">Từ ngày</label><input id="TuNgay" name="TuNgay" type="date" value="{{ old('TuNgay', $course['TuNgay'] ?? '') }}" required></div>
            <div><label for="DenNgay">Đến ngày</label><input id="DenNgay" name="DenNgay" type="date" value="{{ old('DenNgay', $course['DenNgay'] ?? '') }}" required></div>
            <div><label for="TrangThai">Trạng thái</label><select id="TrangThai" name="TrangThai" required>@foreach (['Lên kế hoạch','Đang đào tạo','Hoàn thành'] as $status)<option value="{{ $status }}" @selected(old('TrangThai', $course['TrangThai'] ?? 'Lên kế hoạch') === $status)>{{ $status }}</option>@endforeach</select></div>
            <div class="full-span"><label for="NoiDung">Nội dung</label><textarea id="NoiDung" name="NoiDung">{{ old('NoiDung', $course['NoiDung'] ?? '') }}</textarea></div>
        </div>
        <div class="form-actions-bar"><button class="btn" type="submit">{{ $mode === 'create' ? 'Tạo khóa đào tạo' : 'Cập nhật khóa đào tạo' }}</button>@if ($mode === 'edit' && in_array('xem_tham_gia_dao_tao', session('quyen', []), true))<a class="btn btn-secondary" href="{{ route('daotao.hocvien', ['training' => $course['MaKDT']]) }}">Quản lý học viên</a>@endif<a class="btn btn-secondary" href="{{ route('daotao.index') }}">Về danh sách</a></div>
    </form>
</section>
@endsection