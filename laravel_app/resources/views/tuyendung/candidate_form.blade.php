@php
    $title = 'Thêm ứng viên';
    $subtitle = 'Nhập thông tin ứng viên và tải lên CV PDF';
@endphp
@extends('layouts.app')

@section('content')
<section class="panel">
    <form method="post" action="{{ route('tuyendung.ungvien.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="field-grid">
            <div><label for="HoTen">Họ tên</label><input id="HoTen" name="HoTen" value="{{ old('HoTen') }}" required></div>
            <div><label for="NgaySinh">Ngày sinh</label><input id="NgaySinh" name="NgaySinh" type="date" value="{{ old('NgaySinh') }}"></div>
            <div><label for="GioiTinh">Giới tính</label><select id="GioiTinh" name="GioiTinh"><option value="">Chọn</option><option value="Nam" @selected(old('GioiTinh') === 'Nam')>Nam</option><option value="Nữ" @selected(old('GioiTinh') === 'Nữ')>Nữ</option></select></div>
            <div><label for="Email">Email</label><input id="Email" name="Email" type="email" value="{{ old('Email') }}"></div>
            <div><label for="DienThoai">Điện thoại</label><input id="DienThoai" name="DienThoai" value="{{ old('DienThoai') }}"></div>
            <div><label for="TrinhDo">Trình độ</label><input id="TrinhDo" name="TrinhDo" value="{{ old('TrinhDo') }}"></div>
            <div class="full-span"><label for="KinhNghiem">Kinh nghiệm</label><textarea id="KinhNghiem" name="KinhNghiem">{{ old('KinhNghiem') }}</textarea></div>
            <div class="full-span"><label for="FileCVUpload">File CV (PDF)</label><input id="FileCVUpload" name="FileCVUpload" type="file" accept="application/pdf" class="file-input-reset"></div>
        </div>
        <div class="form-actions-bar"><button class="btn" type="submit">Lưu ứng viên</button><a class="btn btn-secondary" href="{{ route('tuyendung.ungvien.index') }}">Về danh sách</a></div>
    </form>
</section>
@endsection