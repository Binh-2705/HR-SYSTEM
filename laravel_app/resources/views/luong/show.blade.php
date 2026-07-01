@php $title = 'Chi tiết bảng lương' @endphp
@php $subtitle = 'Thông tin chi tiết theo nhân viên và kỳ lương' @endphp
@php $canEdit = in_array('mo_chot_luong', session('quyen', []), true) || in_array('chot_luong', session('quyen', []), true) @endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="table-shell">
            <table class="data-table table-compact">
                <tbody>
                    <tr>
                        <th>Mã bảng lương</th>
                        <td>{{ $record['MaBL'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>Mã nhân viên</th>
                        <td>{{ $record['MaNV'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>Họ tên</th>
                        <td>{{ $record['HoTen'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>Tháng/Năm</th>
                        <td>{{ $record['Thang'] ?? '' }}/{{ $record['Nam'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>Lương cơ sở</th>
                        <td>{{ number_format((float) ($record['LuongCoSo'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <th>Hệ số lương</th>
                        <td>{{ $record['HeSoLuong'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>Hệ số chức vụ</th>
                        <td>{{ $record['HeSoChucVu'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>Phụ cấp</th>
                        <td>{{ number_format((float) ($record['PhuCap'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <th>Thưởng</th>
                        <td>{{ number_format((float) ($record['Thuong'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <th>Phạt</th>
                        <td>{{ number_format((float) ($record['Phat'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <th>Bảo hiểm</th>
                        <td>{{ number_format((float) ($record['BaoHiem'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <th>Tổng lương</th>
                        <td><strong class="metric-value-danger">{{ number_format((float) ($record['TongLuong'] ?? 0), 0, ',', '.') }}</strong></td>
                    </tr>
                    <tr>
                        <th>Trạng thái</th>
                        <td>{{ $record['TrangThai'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>Ngày tính</th>
                        <td>{{ $record['NgayTinh'] ?? '' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="form-actions-bar">
            <a href="{{ route('luong.index', ['month' => $record['Thang'] ?? null, 'year' => $record['Nam'] ?? null]) }}" class="btn btn-secondary">Quay lại danh sách</a>
            @if ($canEdit)
                <a href="{{ route('luong.edit', ['payroll' => $record['MaBL']]) }}" class="btn">Sửa bảng lương</a>
            @endif
        </div>
    </section>
@endsection
