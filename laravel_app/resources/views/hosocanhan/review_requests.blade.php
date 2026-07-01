@php $title = 'Duyệt yêu cầu sửa hồ sơ' @endphp
@php $subtitle = 'Hàng đợi cập nhật hồ sơ cho admin và quản lý' @endphp
@extends('layouts.app')

@section('content')
<section class="panel">
    <div class="muted">Kiểm tra thông tin đề nghị và ghi chú phê duyệt hoặc từ chối để truy vết sau này.</div>
</section>

<section class="panel">
    <div class="table-shell">
        <table class="data-table table-compact">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nhân viên</th>
                    <th>Thông tin đề nghị</th>
                    <th>Ghi chú</th>
                    <th>Xử lý</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $request)
                    <tr>
                        <td>#{{ $request['id'] }}</td>
                        <td>
                            <strong>{{ $request['HoTen'] ?? '' }}</strong><br>
                            MãNV: {{ $request['MaNV'] }}<br>
                            Điện thoại: {{ $request['DienThoai'] ?? '' }}
                        </td>
                        <td>
                            <div>CCCD: {{ data_get($request, 'payload.CCCD', '') }}</div>
                            <div>Địa chỉ: {{ data_get($request, 'payload.DiaChi', '') }}</div>
                            <div>Trình độ: {{ data_get($request, 'payload.TrinhDo', '') }}</div>
                            <div>Chuyên môn: {{ data_get($request, 'payload.ChuyenMon', '') }}</div>
                        </td>
                        <td>{{ $request['note'] ?? '' }}</td>
                        <td>
                            <form method="post" action="{{ route('hosocanhan.review-requests.resolve', ['requestId' => $request['id']]) }}" class="review-action-form">
                                @csrf
                                <textarea name="review_note" rows="2" placeholder="Ghi chú khi duyệt hoặc từ chối"></textarea>
                                <div class="button-row">
                                    <button class="btn" type="submit" name="decision" value="approve">Duyệt</button>
                                    <button class="btn btn-danger" type="submit" name="decision" value="reject">Từ chối</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">Không có yêu cầu đang chờ duyệt.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection