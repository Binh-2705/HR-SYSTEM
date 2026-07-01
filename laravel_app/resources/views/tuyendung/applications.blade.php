@php
    $title = 'Hồ sơ ứng tuyển';
    $subtitle = 'Theo dõi hồ sơ theo từng đợt tuyển trong hệ thống';
@endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="toolbar toolbar-start">
            <div>
                <div><strong>#{{ $campaign['MaDTD'] }} - {{ $campaign['TenDotTuyenDung'] }}</strong></div>
                <div class="muted top-gap-sm">{{ $campaign['ViTriTuyenDung'] }} | {{ $campaign['TrangThai'] }} | Từ {{ $campaign['TuNgay'] }} đến {{ $campaign['DenNgay'] ?: 'N/A' }}</div>
            </div>
            <div class="button-row spaced">
                @if (in_array('them_ho_so', session('quyen', []), true))
                    <a class="btn" href="{{ route('tuyendung.ungvien.index') }}">Chọn ứng viên</a>
                @endif
                <a class="btn btn-secondary" href="{{ route('tuyendung.index') }}">Về đợt tuyển</a>
            </div>
        </div>
    </section>

    <section class="panel">
        <form method="get" class="filter-grid">
            <div>
                <label for="q" class="wide-search-label">Tìm hồ sơ</label>
                <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Họ tên, email hoặc điện thoại">
            </div>
            <div>
                <label for="status" class="wide-search-label">Trạng thái</label>
                <select id="status" name="status">
                    <option value="">Tất cả</option>
                    @foreach (['Nộp hồ sơ', 'Sàng lọc', 'Phỏng vấn', 'Offer', 'Nhận việc', 'Rớt'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div><button class="btn" type="submit">Lọc</button></div>
        </form>
    </section>

    <section class="panel">
        <div class="table-shell">
            <table class="data-table table-compact">
                <thead><tr>
                    <th>MãHS</th>
                    <th>Ứng viên</th>
                    <th>CV</th>
                    <th>Ngày nộp</th>
                    <th>Trạng thái</th>
                    <th>Phỏng vấn</th>
                    <th>Thao tác</th>
                </tr></thead>
                <tbody>
                @forelse ($applications as $application)
                    @php
                        $ngayNopRaw = $application->NgayNop ?? null;
                        $ngayNopTs = $ngayNopRaw ? strtotime((string) $ngayNopRaw) : false;
                        $diemCvRaw = $application->DiemCV ?? null;
                        $hasValidDiemCv = $diemCvRaw !== null && $diemCvRaw !== '' && is_numeric($diemCvRaw);
                        $soLichRaw = $application->SoLichPhongVan ?? null;
                        $hasValidSoLich = $soLichRaw !== null && $soLichRaw !== '' && is_numeric($soLichRaw);
                    @endphp
                    <tr>
                        <td>
                            @if (!empty($application->MaHS))
                                {{ $application->MaHS }}
                            @else
                                <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: thiếu mã hồ sơ</span>
                            @endif
                        </td>
                        <td>
                            @if (!empty($application->HoTen))
                                <strong>{{ $application->HoTen }}</strong>
                            @else
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn họ tên</span>
                            @endif
                            <div class="muted top-gap-sm">
                                @if (!empty($application->Email))
                                    {{ $application->Email }}
                                @else
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn email</span>
                                @endif
                                |
                                @if (!empty($application->DienThoai))
                                    {{ $application->DienThoai }}
                                @else
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn số điện thoại</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if ($hasValidDiemCv)
                                {{ $diemCvRaw }}/10
                            @elseif ($diemCvRaw === null || $diemCvRaw === '')
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn điểm CV</span>
                            @else
                                <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: điểm CV</span>
                            @endif
                        </td>
                        <td>
                            @if ($ngayNopRaw === null || $ngayNopRaw === '')
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn ngày nộp</span>
                            @elseif ($ngayNopTs === false)
                                <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: ngày nộp</span>
                            @else
                                {{ date('d/m/Y', $ngayNopTs) }}
                            @endif
                        </td>
                        <td class="min-col-240">
                            @if (in_array('capnhat_trangthai', session('quyen', []), true))
                                <form method="post" action="{{ route('tuyendung.hoso.status', ['application' => $application->MaHS]) }}" class="review-action-form">
                                    @csrf
                                    <select name="TrangThai">
                                        @foreach (['Nộp hồ sơ', 'Sàng lọc', 'Phỏng vấn', 'Offer', 'Nhận việc', 'Rớt'] as $status)
                                            <option value="{{ $status }}" @selected($application->TrangThai === $status)>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                        <textarea name="GhiChu" placeholder="Ghi chú">{{ $application->GhiChu }}</textarea>
                                        <button class="btn" type="submit">Cập nhật</button>
                                </form>
                            @else
                                @if (!empty($application->TrangThai))
                                    {{ $application->TrangThai }}
                                @else
                                    <span class="field-status field-status-source">Thiếu dữ liệu nguồn trạng thái</span>
                                @endif
                            @endif
                        </td>
                        <td>
                            @if ($hasValidSoLich)
                                {{ (int) $soLichRaw }}
                            @elseif ($soLichRaw === null || $soLichRaw === '')
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn lịch PV</span>
                            @else
                                <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: số lịch PV</span>
                            @endif
                        </td>
                        <td class="nowrap-cell">
                            @if (in_array('xem_lich_phong_van', session('quyen', []), true))
                                <a class="btn btn-secondary" href="{{ route('tuyendung.hoso.phongvan', ['application' => $application->MaHS]) }}">Phỏng vấn</a>
                            @endif
                            @if (!empty($application->FileCV))
                                <a class="btn" href="{{ route('legacy.upload', ['path' => 'cv/' . ltrim((string) $application->FileCV, '/')]) }}" target="_blank">CV</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">
                            <div class="empty-state-note">
                                <span>Chưa có hồ sơ ứng tuyển trong đợt này.</span>
                                @if (in_array('them_ho_so', session('quyen', []), true))
                                    <a class="btn btn-secondary" href="{{ route('tuyendung.ungvien.index') }}">Chọn ứng viên để nộp hồ sơ</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="top-gap-lg">{{ $applications->links() }}</div>
    </section>
@endsection