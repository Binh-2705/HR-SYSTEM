@php $title = 'Nhân viên' @endphp
@php $subtitle = 'Danh sách và quản trị nhân sự' @endphp
@php $canCreate = in_array('them_nhanvien', session('quyen', []), true) @endphp
@php $canEdit = in_array('sua_nhanvien', session('quyen', []), true) @endphp
@php $canDelete = in_array('xoa_nhanvien', session('quyen', []), true) @endphp
@php $isSelfView = $isSelfView ?? false @endphp
@extends('layouts.app')

@section('content')
    @if ($errors->any())
        <div class="flash-alert error">
            {{ $errors->first('form') ?: $errors->first() }}
        </div>
    @endif

    <div class="context-help-wrap">
        <details class="context-help">
            <summary>
                <span class="context-help-icon" aria-hidden="true">i</span>
                <span>Hướng dẫn nhanh</span>
            </summary>
            <div class="context-help-panel">
                <p class="context-help-title">Cách dùng chức năng Nhân viên</p>
                @if ($isSelfView)
                    <ol class="context-help-steps">
                        <li>Xem thông tin hồ sơ, trạng thái và bậc lương của bạn trong bảng bên dưới.</li>
                        <li>Nhấn <strong>Sửa</strong> để cập nhật thông tin cá nhân khi có thay đổi.</li>
                        <li>Kiểm tra lại email và số điện thoại để đảm bảo nhận thông báo nội bộ.</li>
                    </ol>
                    <p class="context-help-note">Bạn đang ở chế độ tự xem, hệ thống chỉ hiển thị dữ liệu của chính bạn.</p>
                @else
                    <ol class="context-help-steps">
                        <li>Dùng bộ lọc để khoanh vùng theo phòng ban hoặc trạng thái trước khi thao tác hàng loạt.</li>
                        <li>Thêm nhân viên mới bằng nút <strong>Thêm nhân viên</strong>, sau đó mở <strong>Sửa</strong> để hoàn thiện hồ sơ.</li>
                        <li>Ưu tiên kiểm tra các dòng có "Chưa nhập" để tránh thiếu dữ liệu nghiệp vụ.</li>
                    </ol>
                    <p class="context-help-note">Mẹo: bắt đầu bằng bộ lọc phòng ban sẽ giúp rà soát dữ liệu nhanh hơn.</p>
                @endif
            </div>
        </details>
    </div>

    @if (!$isSelfView)
    <section class="panel">
        <form method="get" action="{{ route('nhanvien.index') }}">
            <div class="field-grid">
                <div>
                    <label for="q">Tìm kiếm nhân viên</label>
                    <input id="q" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nhập tên, mã, email hoặc điện thoại...">
                </div>
                <div>
                    <label for="status">Trạng thái</label>
                    <select id="status" name="status">
                        <option value="">Tất cả trạng thái</option>
                        <option value="Đang làm" @selected(($filters['status'] ?? '') === 'Đang làm')>Đang làm</option>
                        <option value="Nghỉ" @selected(($filters['status'] ?? '') === 'Nghỉ')>Nghỉ</option>
                    </select>
                </div>
                <div>
                    <label for="department">Phòng ban</label>
                    <select id="department" name="department">
                        <option value="">Tất cả phòng ban</option>
                        @foreach ($options['departments'] as $department)
                            <option value="{{ $department->MaPB }}" @selected((string) ($filters['department'] ?? '') === (string) $department->MaPB)>{{ $department->TenPB }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="full-span button-row">
                    <button type="submit" class="btn">Lọc danh sách</button>
                    @if ($canCreate)
                        <a href="{{ route('nhanvien.create') }}" class="btn btn-secondary">Thêm nhân viên</a>
                    @endif
                </div>
            </div>
        </form>
    </section>
    @endif

    <section class="panel">
        <div class="table-shell">
            <table class="data-table table-compact">
                <thead>
                    <tr>
                        <th class="nowrap-cell">STT</th>
                        <th>Mã NV</th>
                        <th>Họ tên</th>
                        <th>Giới tính</th>
                        <th>Ngày sinh</th>
                        <th>Email</th>
                        <th>Điện thoại</th>
                        <th>Bậc lương</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        @php
                            $birthRaw = $employee->NgaySinh ?? null;
                            $birthTs = $birthRaw ? strtotime((string) $birthRaw) : false;
                        @endphp
                        <tr>
                            <td><strong>{{ ($employees->firstItem() ?? 1) + $loop->index }}</strong></td>
                            <td>{{ $employee->MaNV }}</td>
                            <td>
                                @if (!empty($employee->HoTen))
                                    <strong>{{ $employee->HoTen }}</strong>
                                @else
                                    <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: thiếu họ tên</span>
                                @endif
                                <div class="muted">
                                    @if (!empty($employee->TenPB))
                                        {{ $employee->TenPB }}
                                    @else
                                        <span class="field-status field-status-unassigned">Chưa được gán phòng ban (vào Sửa để cập nhật)</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if (!empty($employee->GioiTinh))
                                    {{ $employee->GioiTinh }}
                                @else
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn giới tính</span>
                                @endif
                            </td>
                            <td>
                                @if ($birthRaw === null || $birthRaw === '')
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn ngày sinh</span>
                                @elseif ($birthTs === false)
                                    <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: ngày sinh</span>
                                @else
                                    {{ date('d/m/Y', $birthTs) }}
                                @endif
                            </td>
                            <td>
                                @if (!empty($employee->Email))
                                    {{ $employee->Email }}
                                @else
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn email</span>
                                @endif
                            </td>
                            <td>
                                @if (!empty($employee->DienThoai))
                                    {{ $employee->DienThoai }}
                                @else
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn điện thoại</span>
                                @endif
                            </td>
                            <td>
                                @if (!empty($employee->TenBac))
                                    {{ $employee->TenBac }}
                                @else
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn bậc lương</span>
                                @endif
                            </td>
                            <td>
                                @if (!empty($employee->TrangThai))
                                    {{ $employee->TrangThai }}
                                @else
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn trạng thái</span>
                                @endif
                            </td>
                            <td>
                                <div class="button-row">
                                    @if ($canEdit)
                                        <a href="{{ route('nhanvien.edit', ['employee' => $employee->MaNV]) }}" class="btn btn-secondary">Sửa</a>
                                    @endif
                                    @if ($canDelete)
                                        <form method="post" action="{{ route('nhanvien.destroy', ['employee' => $employee->MaNV]) }}" class="inline-form" onsubmit="return confirm('Bạn có chắc muốn xóa nhân viên này?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">Xóa</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="muted">
                                <div class="empty-state-note">
                                    <span>Chưa có nhân viên phù hợp với bộ lọc hiện tại.</span>
                                    @if ($canCreate)
                                        <a href="{{ route('nhanvien.create') }}" class="btn btn-secondary">Thêm nhân viên đầu tiên</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($employees->lastPage() > 1)
            <div class="top-gap-lg">{{ $employees->links() }}</div>
        @endif
    </section>
@endsection