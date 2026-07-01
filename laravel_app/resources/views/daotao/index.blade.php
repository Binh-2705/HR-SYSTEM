@php
    $title = 'Đào tạo';
    $subtitle = 'Quản trị khóa đào tạo trên hệ thống';
@endphp
@extends('layouts.app')

@section('content')
<div class="context-help-wrap">
    <details class="context-help">
        <summary>
            <span class="context-help-icon" aria-hidden="true">i</span>
            <span>Hướng dẫn nhanh</span>
        </summary>
        <div class="context-help-panel">
            <p class="context-help-title">Cách dùng chức năng Đào tạo</p>
            <ol class="context-help-steps">
                <li>Tạo khóa học và cập nhật trạng thái theo tiến độ thực tế.</li>
                <li>Mở <strong>Học viên</strong> để thêm nhân sự tham gia từng khóa.</li>
                <li>Sau đào tạo, cập nhật kết quả để hoàn tất hồ sơ năng lực nhân viên.</li>
            </ol>
            <p class="context-help-note">Với người mới, chỉ cần thao tác theo 3 bước trên là đủ hoàn thành một vòng đào tạo.</p>
        </div>
    </details>
</div>

<section class="panel">
    <form method="get" class="filter-grid">
        <div><label for="q" class="wide-search-label">Tìm kiếm</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tên khóa hoặc đơn vị tổ chức"></div>
        <div><label for="status" class="wide-search-label">Trạng thái</label><select id="status" name="status"><option value="">Tất cả</option>@foreach (['Lên kế hoạch','Đang đào tạo','Hoàn thành'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
        <div class="button-row"><button class="btn" type="submit">Lọc</button>@if (in_array('them_khoa_dao_tao', session('quyen', []), true))<a class="btn btn-secondary" href="{{ route('daotao.create') }}">Thêm mới</a>@endif</div>
    </form>
</section>

<section class="panel">
    <div class="table-shell">
        <table class="data-table table-compact">
            <thead>
            <tr>
                <th>MãKDT</th>
                <th>Tên khóa</th>
                <th>Đơn vị</th>
                <th>Học viên</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($courses as $course)
                @php
                    $soHocVienRaw = $course->SoHocVien ?? null;
                    $hasValidSoHocVien = $soHocVienRaw !== null && $soHocVienRaw !== '' && is_numeric($soHocVienRaw);
                @endphp
                <tr>
                    <td>{{ $course->MaKDT }}</td>
                    <td>
                        @if (!empty($course->TenKhoaDaoTao))
                            <strong>{{ $course->TenKhoaDaoTao }}</strong>
                        @else
                            <span class="field-status field-status-source">Thiếu dữ liệu nguồn tên khóa</span>
                        @endif
                    </td>
                    <td>
                        @if (!empty($course->DonViToChuc))
                            {{ $course->DonViToChuc }}
                        @else
                            <span class="field-status field-status-unassigned">Chưa được gán đơn vị tổ chức</span>
                        @endif
                    </td>
                    <td>
                        @if ($hasValidSoHocVien)
                            {{ (int) $soHocVienRaw }}
                        @elseif ($soHocVienRaw === null || $soHocVienRaw === '')
                            <span class="field-status field-status-source">Thiếu dữ liệu nguồn học viên</span>
                        @else
                            <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: số học viên</span>
                        @endif
                    </td>
                    <td>
                        @if (!empty($course->TrangThai))
                            {{ $course->TrangThai }}
                        @else
                            <span class="field-status field-status-source">Thiếu dữ liệu nguồn trạng thái</span>
                        @endif
                    </td>
                    <td>
                        <div class="button-row">
                            @if (in_array('xem_tham_gia_dao_tao', session('quyen', []), true))
                                <a class="btn btn-secondary" href="{{ route('daotao.hocvien', ['training' => $course->MaKDT]) }}">Học viên</a>
                            @endif
                            @if (in_array('them_khoa_dao_tao', session('quyen', []), true))
                                <a class="btn btn-secondary" href="{{ route('daotao.edit', ['training' => $course->MaKDT]) }}">Sửa</a>
                            @endif
                            @if (in_array('xoa_khoa_dao_tao', session('quyen', []), true))
                                <form method="post" action="{{ route('daotao.destroy', ['training' => $course->MaKDT]) }}" class="inline-form" onsubmit="return confirm('Xóa khóa đào tạo này?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger" type="submit">Xóa</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">
                        <div class="empty-state-note">
                            <span>Không có khóa đào tạo phù hợp với bộ lọc hiện tại.</span>
                            @if (in_array('them_khoa_dao_tao', session('quyen', []), true))
                                <a class="btn btn-secondary" href="{{ route('daotao.create') }}">Tạo khóa đào tạo đầu tiên</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="top-gap-lg">{{ $courses->links() }}</div>
</section>
@endsection