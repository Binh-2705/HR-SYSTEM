@php
    $title = 'Học viên đào tạo';
    $subtitle = 'Quản lý danh sách tham gia và kết quả khóa đào tạo trên hệ thống';
@endphp
@extends('layouts.app')

@section('content')
<section class="panel">
    <div class="entity-head">
        <div>
            <div class="muted">Khóa đào tạo</div>
            <h3 class="top-gap-sm">{{ $course['TenKhoaDaoTao'] }}</h3>
            <div class="muted">{{ $course['TuNgay'] }} đến {{ $course['DenNgay'] }} · @if(!empty($course['DonViToChuc'])){{ $course['DonViToChuc'] }}@else<span class="field-status field-status-unassigned">Chưa được gán đơn vị tổ chức</span>@endif</div>
        </div>
        <div class="button-row spaced">
            <a class="btn btn-secondary" href="{{ route('daotao.edit', ['training' => $course['MaKDT']]) }}">Sửa khóa</a>
            <a class="btn btn-secondary" href="{{ route('daotao.index') }}">Về danh sách</a>
        </div>
    </div>
    @if (!$canEvaluate)
        <div class="muted top-gap-md">Khóa học chưa qua ngày kết thúc, bạn vẫn có thể gán học viên. Kết quả có thể được cập nhật sớm nếu cần.</div>
    @endif
</section>

@if (in_array('them_tham_gia_dao_tao', session('quyen', []), true))
<section class="panel">
    <form method="post" action="{{ route('daotao.hocvien.store', ['training' => $course['MaKDT']]) }}" class="filter-grid compact-wide">
        @csrf
        <div>
            <label for="MaNV" class="wide-search-label">Thêm nhân viên vào khóa</label>
            <select id="MaNV" name="MaNV" required>
                <option value="">Chọn nhân viên</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee['MaNV'] }}">{{ $employee['HoTen'] }} (#{{ $employee['MaNV'] }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <button class="btn" type="submit">Thêm học viên</button>
        </div>
    </form>
</section>
@endif

<section class="panel">
    <div class="table-shell">
        <table class="data-table table-compact">
            <thead>
                <tr>
                    <th>Nhân viên</th>
                    <th>Kết quả</th>
                    <th>Điểm</th>
                    <th>Ghi chú</th>
                    <th>Cập nhật</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($participants as $participant)
                    @php
                        $maNv = $participant['MaNV'] ?? null;
                        $hoTen = $participant['HoTen'] ?? null;
                    @endphp
                    <tr>
                        <td>
                            @if (!empty($hoTen))
                                <strong>{{ $hoTen }}</strong>
                            @else
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn họ tên</span>
                            @endif
                            <div class="muted">
                                @if (!empty($maNv))
                                    MãNV: {{ $maNv }}
                                @else
                                    <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: thiếu mã nhân viên</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <form method="post" action="{{ route('daotao.hocvien.ketqua', ['participant' => $participant['MaTGDT']]) }}" class="participants-form-grid">
                                @csrf
                                <input type="hidden" name="MaKDT" value="{{ $course['MaKDT'] }}">
                                <select name="KetQua">
                                    @foreach (['Đạt', 'Không đạt', 'Chưa đánh giá'] as $status)
                                        <option value="{{ $status }}" @selected($participant['KetQua'] === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                                <input name="DiemDanhGia" type="number" min="0" max="10" step="0.1" value="{{ $participant['DiemDanhGia'] }}">
                                <input name="GhiChu" value="{{ $participant['GhiChu'] }}" placeholder="Ghi chú">
                                <button class="btn" type="submit" @disabled(!in_array('capnhat_ketqua_dao_tao', session('quyen', []), true))>Lưu</button>
                            </form>
                        </td>
                        <td class="hidden-cell"></td>
                        <td class="hidden-cell"></td>
                        <td class="hidden-cell"></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">
                            <div class="empty-state-note">
                                <span>Chưa có dữ liệu nguồn học viên cho khóa đào tạo này.</span>
                                @if (in_array('them_tham_gia_dao_tao', session('quyen', []), true))
                                    <span class="muted-inline-note">Hãy thêm nhân viên vào khóa ở biểu mẫu phía trên.</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection