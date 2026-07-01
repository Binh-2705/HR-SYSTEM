@php $title = 'Chấm công' @endphp
@php $subtitle = 'Quản trị bảng chấm công' @endphp
@php $canCreate = in_array('them_chamcong', session('quyen', []), true) @endphp
@php $canEdit = in_array('sua_chamcong', session('quyen', []), true) @endphp
@php $canDelete = in_array('xoa_chamcong', session('quyen', []), true) @endphp
@php $canExport = in_array('xuat_bang_cham_cong', session('quyen', []), true) @endphp
@php $isSelfView = $isSelfView ?? false @endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:12px">
            <div></div>
            <div style="display:flex;gap:6px">
                <a href="{{ route('chamcong.index') }}"
                   style="padding:5px 14px;border-radius:6px;font-size:0.82rem;border:1px solid #3b4cb8;background:#3b4cb8;color:#fff;text-decoration:none">Danh sách</a>
                <a href="{{ route('chamcong.matrix', ['thang' => now()->month, 'nam' => now()->year]) }}"
                   style="padding:5px 14px;border-radius:6px;font-size:0.82rem;border:1px solid #d1d5db;color:#374151;text-decoration:none">Bảng tháng</a>
            </div>
        </div>
        @if (!$isSelfView)
        <form method="get" action="{{ route('chamcong.index') }}">
            <div class="field-grid">
                <div>
                    <label for="q">Nhân viên</label>
                    <input id="q" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm theo họ tên hoặc mã nhân viên">
                </div>
                <div>
                    <label for="status">Trạng thái</label>
                    <select id="status" name="status">
                        <option value="">Tất cả trạng thái</option>
                        @foreach (['Di lam' => 'Đi làm', 'Nghi phep' => 'Nghỉ phép', 'Nghi khong luong' => 'Nghỉ không lương', 'Cong tac' => 'Công tác', 'Le' => 'Lễ'] as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}" @selected(($filters['status'] ?? '') === $statusValue)>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date">Ngày</label>
                    <input id="date" name="date" type="date" value="{{ $filters['date'] ?? '' }}">
                </div>
                <div class="full-span button-row">
                    <button type="submit" class="btn">Xem chấm công</button>
                    @if ($canCreate)
                        <a href="{{ route('chamcong.create') }}" class="btn btn-secondary">Thêm chấm công</a>
                    @endif
                    @if ($canExport)
                        <a href="{{ route('chamcong.export-excel', ['thang' => request('thang', now()->month), 'nam' => request('nam', now()->year)]) }}" class="btn btn-secondary">Xuất Excel</a>
                    @endif
                </div>
            </div>
        </form>
        @endif
    </section>

    <section class="panel">
        <div class="table-shell">
            <table class="data-table table-compact">
                <thead>
                    <tr>
                        <th>Mã CC</th>
                        <th>Nhân viên</th>
                        <th>Ngày</th>
                        <th>Giờ vào</th>
                        <th>Giờ ra</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        @php
                            $attendanceId = $record->MaCC ?? $record->id ?? $record->ma_cc ?? null;
                            $employeeName = $record->HoTen ?? $record->ho_ten ?? $record->TenNhanVien ?? null;
                            $employeeCode = $record->MaNV ?? $record->ma_nv ?? '';
                            $departmentName = $record->TenPB ?? $record->ten_pb ?? $record->TenPhongBan ?? null;
                            $workDate = $record->Ngay ?? $record->ngay ?? null;
                            $timeIn = $record->GioVao ?? $record->gio_vao ?? null;
                            $timeOut = $record->GioRa ?? $record->gio_ra ?? null;
                            $status = $record->TrangThai ?? $record->trang_thai ?? null;

                            $workDateTs = $workDate ? strtotime((string) $workDate) : false;
                            $isTimeInValid = !empty($timeIn) && preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $timeIn) === 1;
                            $isTimeOutValid = !empty($timeOut) && preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $timeOut) === 1;
                        @endphp
                        <tr>
                            <td>
                                @if (!empty($attendanceId))
                                    {{ $attendanceId }}
                                @else
                                    <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: thiếu mã chấm công</span>
                                @endif
                            </td>
                            <td>
                                @if (!empty($employeeName))
                                    <strong>{{ $employeeName }}</strong>
                                @else
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn tên nhân viên</span>
                                @endif
                                <div class="muted">
                                    @if (!empty($employeeCode))
                                        {{ $employeeCode }}{{ $departmentName ? ' - ' . $departmentName : '' }}
                                    @else
                                        <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: thiếu mã nhân viên</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if ($workDate === null || $workDate === '')
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn ngày công</span>
                                @elseif ($workDateTs === false)
                                    <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: ngày công</span>
                                @else
                                    {{ date('d/m/Y', $workDateTs) }}
                                @endif
                            </td>
                            <td>
                                @if ($timeIn === null || $timeIn === '')
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn giờ vào</span>
                                @elseif ($isTimeInValid)
                                    {{ $timeIn }}
                                @else
                                    <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: giờ vào</span>
                                @endif
                            </td>
                            <td>
                                @if ($timeOut === null || $timeOut === '')
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn giờ ra</span>
                                @elseif ($isTimeOutValid)
                                    {{ $timeOut }}
                                @else
                                    <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: giờ ra</span>
                                @endif
                            </td>
                            <td>
                                @if (!empty($status))
                                    {{ $status }}
                                @else
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn trạng thái</span>
                                @endif
                            </td>
                            <td>
                                <div class="button-row">
                                    @if ($canEdit)
                                        @if ($attendanceId)
                                            <a href="{{ route('chamcong.edit', ['attendance' => $attendanceId]) }}" class="btn btn-secondary">Sửa</a>
                                        @endif
                                    @endif
                                    @if ($canDelete)
                                        @if ($attendanceId)
                                            <form method="post" action="{{ route('chamcong.destroy', ['attendance' => $attendanceId]) }}" class="inline-form" onsubmit="return confirm('Bạn có chắc muốn xóa bản ghi chấm công này?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger">Xóa</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">
                                <div class="empty-state-note">
                                    <span>Không có dữ liệu chấm công phù hợp bộ lọc hiện tại.</span>
                                    @if ($canCreate)
                                        <a href="{{ route('chamcong.create') }}" class="btn btn-secondary">Thêm bản ghi chấm công</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($records->lastPage() > 1)
            <div class="top-gap-lg">{{ $records->links() }}</div>
        @endif
    </section>
@endsection