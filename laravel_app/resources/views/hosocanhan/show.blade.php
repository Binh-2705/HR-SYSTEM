@php $title = 'Chi tiết hồ sơ nhân viên' @endphp
@php $subtitle = 'Thông tin chi tiết hồ sơ nhân sự trên hệ thống' @endphp
@extends('layouts.app')

@section('content')
<section class="panel">
    <div class="profile-shell">
        <div>
            @if (!empty($profile['Anh']))
                <img src="{{ route('legacy.upload', ['path' => ltrim((string) $profile['Anh'], '/')]) }}" alt="Ảnh nhân viên" class="profile-avatar">
            @else
                <div class="profile-avatar-empty">Chưa có ảnh</div>
            @endif
        </div>
        <div>
            <h3 class="no-top-margin">{{ $profile['HoTen'] ?? 'Chưa cập nhật' }}</h3>
            <div class="muted top-gap-sm">{{ $profile['TenCV'] ?? 'Chưa có chức vụ' }} · {{ $profile['TenPB'] ?? 'Chưa có phòng ban' }}</div>
            <div class="info-pills">
                <span class="info-pill">MãNV: {{ $profile['MaNV'] ?? '---' }}</span>
                <span class="info-pill">MãHồSơ: {{ $profile['MaHoSo'] ?? '---' }}</span>
                <span class="info-pill">Hôn nhân: {{ $profile['TrangThaiHonNhan'] ?? '---' }}</span>
            </div>
        </div>
    </div>
</section>

<section class="detail-grid">
    <article class="panel"><h3 class="no-top-margin">Giấy tờ</h3><div class="muted">CCCD</div><div>{{ $profile['CCCD'] ?? '---' }}</div><div class="muted top-gap-md">Nơi cấp</div><div>{{ $profile['NoiCap'] ?? '---' }}</div><div class="muted top-gap-md">Ngày cấp</div><div>{{ $profile['NgayCap'] ?? '---' }}</div></article>
    <article class="panel"><h3 class="no-top-margin">Thông tin cá nhân</h3><div class="muted">Địa chỉ</div><div>{{ $profile['DiaChi'] ?? '---' }}</div><div class="muted top-gap-md">Dân tộc</div><div>{{ $profile['DanToc'] ?? '---' }}</div><div class="muted top-gap-md">Tôn giáo</div><div>{{ $profile['TonGiao'] ?? '---' }}</div></article>
    <article class="panel"><h3 class="no-top-margin">Thông tin công việc</h3><div class="muted">Trình độ</div><div>{{ $profile['TrinhDo'] ?? '---' }}</div><div class="muted top-gap-md">Chuyên môn</div><div>{{ $profile['ChuyenMon'] ?? '---' }}</div><div class="muted top-gap-md">Ngày vào làm</div><div>{{ $profile['NgayVaoLam'] ?? '---' }}</div></article>
</section>

<section class="panel">
    <a class="btn btn-secondary" href="{{ route('hosocanhan.index') }}">Quay lại danh sách</a>
</section>
@endsection