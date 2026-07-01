@php
    $title = $mode === 'create' ? 'Thêm báo cáo' : 'Sửa báo cáo';
    $subtitle = 'Cập nhật thông tin báo cáo trên hệ thống';
@endphp
@extends('layouts.app')

@section('content')
<section class="panel">
    <form method="post" action="{{ $mode === 'create' ? route('baocao.store') : route('baocao.update', ['report' => $report['MaBC']]) }}">
        @csrf
        @if ($mode === 'edit') @method('PUT') @endif
        <div class="field-grid">
            <div><label for="TenBaoCao">Tên báo cáo</label><input id="TenBaoCao" name="TenBaoCao" value="{{ old('TenBaoCao', $report['TenBaoCao'] ?? '') }}" required></div>
            <div><label for="LoaiBaoCao">Loại báo cáo</label><select id="LoaiBaoCao" name="LoaiBaoCao" required>@foreach (['Nhân sự','Chấm công','Nghỉ phép','Hợp đồng','Lương'] as $type)<option value="{{ $type }}" @selected(old('LoaiBaoCao', $report['LoaiBaoCao'] ?? 'Nhân sự') === $type)>{{ $type }}</option>@endforeach</select></div>
            <div><label for="TuNgay">Từ ngày</label><input id="TuNgay" name="TuNgay" type="date" value="{{ old('TuNgay', $report['TuNgay'] ?? '') }}"></div>
            <div><label for="DenNgay">Đến ngày</label><input id="DenNgay" name="DenNgay" type="date" value="{{ old('DenNgay', $report['DenNgay'] ?? '') }}"></div>
            <div class="full-span"><label for="GhiChu">Ghi chú</label><textarea id="GhiChu" name="GhiChu">{{ old('GhiChu', $report['GhiChu'] ?? '') }}</textarea></div>
        </div>
        <div class="form-actions-bar"><button class="btn" type="submit">{{ $mode === 'create' ? 'Tạo báo cáo' : 'Cập nhật báo cáo' }}</button><a class="btn btn-secondary" href="{{ route('baocao.index') }}">Về danh sách</a></div>
    </form>
</section>
@endsection