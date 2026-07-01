@php $title = 'Lịch sử lương hợp đồng' @endphp
@php $subtitle = 'Theo dõi các lần thay đổi mức lương của hợp đồng' @endphp
@extends('layouts.app')

@section('content')
<section class="panel">
    <div class="summary-card-grid">
        <div><div class="muted">Số hợp đồng</div><div class="metric-strong">{{ $contract['SoHopDong'] }}</div></div>
        <div><div class="muted">Nhân viên</div><div class="metric-strong">{{ $contract['HoTen'] }}</div></div>
        <div><div class="muted">Bậc hiện tại</div><div class="metric-strong">{{ $contract['TenBac'] }}</div></div>
        <div><div class="muted">Lương hiện tại</div><div class="metric-strong">{{ number_format($contract['LuongThucTe'], 0, ',', '.') }} VNĐ</div></div>
    </div>
</section>

<section class="panel">
    <div class="table-shell">
        <table class="data-table table-compact">
            <thead>
                <tr>
                    <th>Ngày áp dụng</th>
                    <th>Lương cũ</th>
                    <th>Lương mới</th>
                    <th>Chênh lệch</th>
                    <th>Lý do</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($history as $item)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($item['NgayApDung'])->format('d/m/Y') }}</td>
                        <td class="metric-value-danger">{{ number_format((float) $item['LuongCu'], 0, ',', '.') }} VNĐ</td>
                        <td class="metric-value-success">{{ number_format((float) $item['LuongMoi'], 0, ',', '.') }} VNĐ</td>
                        <td>{{ number_format((float) $item['LuongMoi'] - (float) $item['LuongCu'], 0, ',', '.') }} VNĐ</td>
                        <td>{{ $item['LyDo'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">Chưa có thay đổi lương nào cho hợp đồng này.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="top-gap-lg">
        <a class="btn btn-secondary" href="{{ route('hopdong.index') }}">Quay lại danh sách hợp đồng</a>
    </div>
</section>
@endsection