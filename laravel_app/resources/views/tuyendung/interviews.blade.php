@php
    $title = 'Phỏng vấn và đánh giá';
    $subtitle = 'Quản lý lịch phỏng vấn và nhận xét ứng viên trong hệ thống';
@endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="toolbar toolbar-start">
            <div>
                <div><strong>{{ $application['HoTen'] }}</strong> - {{ $application['TenDotTuyenDung'] }}</div>
                <div class="muted top-gap-sm">
                    MãHS: {{ $application['MaHS'] }} |
                    @if (!empty($application['TrangThai']))
                        Trạng thái: {{ $application['TrangThai'] }}
                    @else
                        <span class="field-status field-status-source">Thiếu dữ liệu nguồn trạng thái</span>
                    @endif
                    |
                    @if (isset($application['DiemCV']) && $application['DiemCV'] !== '' && is_numeric($application['DiemCV']))
                        Điểm CV: {{ $application['DiemCV'] }}/10
                    @elseif (!isset($application['DiemCV']) || $application['DiemCV'] === '')
                        <span class="field-status field-status-source">Thiếu dữ liệu nguồn điểm CV</span>
                    @else
                        <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: điểm CV</span>
                    @endif
                </div>
            </div>
            <div class="button-row spaced">
                <a class="btn btn-secondary" href="{{ route('tuyendung.hoso.index', ['recruitment' => $application['MaDTD']]) }}">Về hồ sơ</a>
                @if (!empty($application['FileCV']))
                    <a class="btn" href="{{ route('legacy.upload', ['path' => 'cv/' . ltrim((string) $application['FileCV'], '/')]) }}" target="_blank">Mở CV</a>
                @endif
            </div>
        </div>
    </section>

    <section class="split-two">
        <div class="panel">
            <h3 class="no-top-margin">Thêm lịch phỏng vấn</h3>
            <form method="post" action="{{ route('tuyendung.hoso.phongvan.store', ['application' => $application['MaHS']]) }}">
                @csrf
                <div class="field-stack">
                    <div><label for="NgayPhongVan">Ngày phỏng vấn</label><input id="NgayPhongVan" name="NgayPhongVan" type="date" required></div>
                    <div><label for="GioPhongVan">Giờ phỏng vấn</label><input id="GioPhongVan" name="GioPhongVan" type="time" required></div>
                    <div><label for="DiaDiem">Địa điểm</label><input id="DiaDiem" name="DiaDiem"></div>
                    <div><label for="GhiChu">Ghi chú</label><textarea id="GhiChu" name="GhiChu"></textarea></div>
                    <div><label for="KetQua">Kết quả</label><input id="KetQua" name="KetQua"></div>
                    <button class="btn" type="submit">Thêm lịch</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <h3 class="no-top-margin">Thêm đánh giá</h3>
            <form method="post" action="{{ route('tuyendung.hoso.danhgia.store', ['application' => $application['MaHS']]) }}">
                @csrf
                <div class="field-stack">
                    <div><label for="DiemKyNang">Kỹ năng</label><input id="DiemKyNang" name="DiemKyNang" type="number" min="1" max="10" required></div>
                    <div><label for="DiemKinhNghiem">Kinh nghiệm</label><input id="DiemKinhNghiem" name="DiemKinhNghiem" type="number" min="1" max="10" required></div>
                    <div><label for="DiemThaiDo">Thái độ</label><input id="DiemThaiDo" name="DiemThaiDo" type="number" min="1" max="10" required></div>
                    <div><label for="NhanXet">Nhận xét</label><textarea id="NhanXet" name="NhanXet"></textarea></div>
                    <button class="btn" type="submit">Lưu đánh giá</button>
                </div>
            </form>
        </div>
    </section>

    <section class="split-two">
        <div class="panel">
            <h3 class="no-top-margin">Danh sách lịch phỏng vấn</h3>
            <div class="stack-list">
                @forelse ($interviews as $interview)
                    @php
                        $ngayPvRaw = $interview->NgayPhongVan ?? null;
                        $ngayPvTs = $ngayPvRaw ? strtotime((string) $ngayPvRaw) : false;
                        $gioPvRaw = $interview->GioPhongVan ?? null;
                        $gioPvValid = !empty($gioPvRaw) && preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $gioPvRaw) === 1;
                    @endphp
                    <div class="stack-card-soft">
                        <div>
                            @if ($ngayPvRaw === null || $ngayPvRaw === '')
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn ngày phỏng vấn</span>
                            @elseif ($ngayPvTs === false)
                                <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: ngày phỏng vấn</span>
                            @else
                                <strong>{{ date('d/m/Y', $ngayPvTs) }}</strong>
                            @endif
                            lúc
                            @if ($gioPvRaw === null || $gioPvRaw === '')
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn giờ phỏng vấn</span>
                            @elseif ($gioPvValid)
                                {{ $gioPvRaw }}
                            @else
                                <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: giờ phỏng vấn</span>
                            @endif
                        </div>
                        <div class="muted top-gap-sm">
                            @if (!empty($interview->DiaDiem))
                                {{ $interview->DiaDiem }}
                            @else
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn địa điểm</span>
                            @endif
                        </div>
                        @if (!empty($interview->GhiChu))
                            <div class="muted top-gap-sm">{{ $interview->GhiChu }}</div>
                        @endif
                        @if (!empty($interview->KetQua))
                            <div class="top-gap-sm"><strong>Kết quả:</strong> {{ $interview->KetQua }}</div>
                        @endif
                    </div>
                @empty
                    <div class="field-status field-status-source">Thiếu dữ liệu nguồn lịch phỏng vấn cho hồ sơ này.</div>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <h3 class="no-top-margin">Đánh giá đã lưu</h3>
            <div class="stack-list">
                @forelse ($reviews as $review)
                    @php
                        $hasValidSkill = isset($review->DiemKyNang) && is_numeric($review->DiemKyNang);
                        $hasValidExp = isset($review->DiemKinhNghiem) && is_numeric($review->DiemKinhNghiem);
                        $hasValidAtt = isset($review->DiemThaiDo) && is_numeric($review->DiemThaiDo);
                        $hasValidAverage = $hasValidSkill && $hasValidExp && $hasValidAtt;
                        $average = $hasValidAverage ? (($review->DiemKyNang + $review->DiemKinhNghiem + $review->DiemThaiDo) / 3) : null;
                    @endphp
                    <div class="stack-card-soft">
                        <div>
                            <strong>Điểm TB:</strong>
                            @if ($hasValidAverage)
                                {{ number_format($average, 1) }}/10
                            @else
                                <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: không tính được điểm trung bình</span>
                            @endif
                        </div>
                        <div class="muted top-gap-sm">Kỹ năng: {{ $review->DiemKyNang }} | Kinh nghiệm: {{ $review->DiemKinhNghiem }} | Thái độ: {{ $review->DiemThaiDo }}</div>
                        @if (!empty($review->NhanXet))
                            <div class="top-gap-sm">{{ $review->NhanXet }}</div>
                        @endif
                    </div>
                @empty
                    <div class="field-status field-status-source">Thiếu dữ liệu nguồn đánh giá phỏng vấn.</div>
                @endforelse
            </div>
        </div>
    </section>
@endsection