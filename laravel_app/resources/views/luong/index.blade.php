@php $title = 'Lương' @endphp
@php $subtitle = 'Quản trị bảng lương' @endphp
@php $canRun = in_array('tinh_luong_thang', session('quyen', []), true) @endphp
@php $canView = in_array('xem_luong', session('quyen', []), true) @endphp
@php $canLock = in_array('chot_luong', session('quyen', []), true) @endphp
@php $canUnlock = in_array('mo_chot_luong', session('quyen', []), true) @endphp
@php $canEdit = in_array('mo_chot_luong', session('quyen', []), true) || in_array('chot_luong', session('quyen', []), true) @endphp
@php $isSelfView = $isSelfView ?? false @endphp
@extends('layouts.app')

@section('content')
    <div class="context-help-wrap">
        <details class="context-help">
            <summary>
                <span class="context-help-icon" aria-hidden="true">i</span>
                <span>Hướng dẫn nhanh</span>
            </summary>
            <div class="context-help-panel">
                <p class="context-help-title">Cách dùng chức năng Bảng lương</p>
                @if ($isSelfView)
                    <ol class="context-help-steps">
                        <li>Chọn đúng tháng/năm để xem bảng lương cá nhân.</li>
                        <li>Mở <strong>Xem</strong> để kiểm tra chi tiết thành phần lương và các khoản khấu trừ.</li>
                        <li>Nếu dữ liệu bất thường, gửi phản hồi cho bộ phận nhân sự kèm mã bảng lương.</li>
                    </ol>
                    <p class="context-help-note">Bạn đang ở chế độ tự xem, chỉ thao tác trên dữ liệu của chính bạn.</p>
                @else
                    <ol class="context-help-steps">
                        <li>Chạy <strong>Tính lương tháng</strong> trước, sau đó kiểm tra kết quả theo bộ lọc tháng/năm.</li>
                        <li>Dùng <strong>Xem</strong> hoặc <strong>Sửa</strong> để rà soát các dòng có trạng thái chưa chốt.</li>
                        <li>Chỉ <strong>Chốt lương</strong> khi đã xác minh tổng lương và thực nhận.</li>
                    </ol>
                    <p class="context-help-note">Thứ tự thao tác khuyến nghị: Tính lương -> Rà soát -> Chốt lương.</p>
                @endif
            </div>
        </details>
    </div>

    @if ($canRun && !$isSelfView)
        <section class="panel">
            <form method="post" action="{{ route('luong.run-monthly') }}">
                @csrf
                <div class="field-grid">
                    <div>
                        <label for="run-month">Tháng tính lương</label>
                        <input id="run-month" type="number" name="thang" min="1" max="12" value="{{ request('month', now()->month) }}" required>
                    </div>
                    <div>
                        <label for="run-year">Năm</label>
                        <input id="run-year" type="number" name="nam" value="{{ request('year', now()->year) }}" required>
                    </div>
                    <div class="full-span button-row">
                        <button type="submit" class="btn">Tính lương tháng</button>
                        <a href="{{ route('luong.create') }}" class="btn btn-secondary">Thêm bảng lương</a>
                    </div>
                </div>
            </form>
        </section>
    @endif

    @if (!$isSelfView)
    <section class="panel">
        <form method="get" action="{{ route('luong.index') }}">
            <div class="field-grid">
                <div>
                    <label for="q">Nhân viên</label>
                    <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm theo họ tên hoặc mã nhân viên">
                </div>
                <div>
                    <label for="month">Tháng</label>
                    <input id="month" name="month" type="number" min="1" max="12" value="{{ $filters['month'] ?? '' }}" placeholder="Tháng">
                </div>
                <div>
                    <label for="year">Năm</label>
                    <input id="year" name="year" type="number" min="2000" max="2100" value="{{ $filters['year'] ?? '' }}" placeholder="Năm">
                </div>
                <div>
                    <label for="status">Trạng thái</label>
                    <input id="status" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="Chưa chốt / Đã chốt">
                </div>
                <div class="full-span button-row">
                    <button class="btn" type="submit">Lọc bảng lương</button>
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
                        <th>Mã BL</th>
                        <th>Nhân viên</th>
                        <th>Tháng</th>
                        <th>Năm</th>
                        <th>Thực nhận</th>
                        <th>Tổng lương</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        @php
                            $monthValue = (int) ($record->Thang ?? 0);
                            $yearValue = (int) ($record->Nam ?? 0);
                            $hasValidMonth = $monthValue >= 1 && $monthValue <= 12;
                            $hasValidYear = $yearValue >= 2000 && $yearValue <= 2100;
                            $thucNhanRaw = $record->ThucNhan ?? null;
                            $tongLuongRaw = $record->TongLuong ?? null;
                            $hasValidThucNhan = $thucNhanRaw !== null && $thucNhanRaw !== '' && is_numeric($thucNhanRaw);
                            $hasValidTongLuong = $tongLuongRaw !== null && $tongLuongRaw !== '' && is_numeric($tongLuongRaw);
                        @endphp
                        <tr>
                            <td>{{ $record->MaBL }}</td>
                            <td>
                                @if (!empty($record->HoTen))
                                    <strong>{{ $record->HoTen }}</strong>
                                @else
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn tên nhân viên</span>
                                @endif
                                <div class="muted">
                                    @if (!empty($record->MaNV))
                                        Mã NV: {{ $record->MaNV }}
                                    @else
                                        <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: thiếu mã nhân viên</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if ($hasValidMonth)
                                    {{ $monthValue }}
                                @else
                                    <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: tháng</span>
                                @endif
                            </td>
                            <td>
                                @if ($hasValidYear)
                                    {{ $yearValue }}
                                @else
                                    <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: năm</span>
                                @endif
                            </td>
                            <td>
                                @if ($hasValidThucNhan)
                                    <strong class="metric-value-danger">{{ number_format((float) $thucNhanRaw, 0, ',', '.') }}</strong>
                                @elseif ($thucNhanRaw === null || $thucNhanRaw === '')
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn thực nhận</span>
                                @else
                                    <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: thực nhận</span>
                                @endif
                            </td>
                            <td>
                                @if ($hasValidTongLuong)
                                    <strong class="metric-value-danger">{{ number_format((float) $tongLuongRaw, 0, ',', '.') }}</strong>
                                @elseif ($tongLuongRaw === null || $tongLuongRaw === '')
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn tổng lương</span>
                                @else
                                    <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: tổng lương</span>
                                @endif
                            </td>
                            <td>
                                @if ($record->TrangThai === 'Đã chốt')
                                    <span class="status-text-ok">Đã chốt</span>
                                @elseif (empty($record->TrangThai))
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn trạng thái</span>
                                @else
                                    <span class="status-text-warn">{{ $record->TrangThai }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($canView || $canEdit || $canLock || $canUnlock)
                                    <div class="button-row">
                                        @if ($canView)
                                            <a href="{{ route('luong.show', ['payroll' => $record->MaBL]) }}" class="btn btn-secondary">Xem</a>
                                        @endif
                                        @if ($canEdit)
                                            <a href="{{ route('luong.edit', ['payroll' => $record->MaBL]) }}" class="btn btn-secondary">Sửa</a>
                                        @endif
                                        @if ($record->TrangThai !== 'Đã chốt' && $canLock)
                                            <a href="{{ route('luong.lock.legacy', ['payroll' => $record->MaBL]) }}" class="btn">Chốt lương</a>
                                        @endif
                                        @if ($record->TrangThai === 'Đã chốt' && $canUnlock)
                                            <a href="{{ route('luong.unlock.legacy', ['payroll' => $record->MaBL]) }}" class="btn btn-secondary">Mở chốt</a>
                                        @endif
                                    </div>
                                @else
                                    <span class="muted-inline-note">Chỉ xem</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">
                                <div class="empty-state-note">
                                    <span>Chưa có dữ liệu lương theo điều kiện lọc hiện tại.</span>
                                    @if ($canRun && !$isSelfView)
                                        <span class="muted-inline-note">Hãy chạy "Tính lương tháng" để tạo dữ liệu.</span>
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