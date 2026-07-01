@php
    $title = 'Danh sách ứng viên';
    $subtitle = 'Quản lý ứng viên, đánh giá CV và nộp hồ sơ theo đợt tuyển';
@endphp
@extends('layouts.app')

@section('content')
    @php
        $permissions = (array) session('quyen', []);
    @endphp
    <section class="panel">
        <div class="button-row">
            @if (in_array('them_ung_vien', $permissions, true))
                <a class="btn" href="{{ route('tuyendung.ungvien.create') }}">+ Thêm ứng viên</a>
            @endif
            <a class="btn btn-secondary" href="{{ route('tuyendung.index') }}">Đợt tuyển</a>
        </div>
        <form method="get" class="top-gap-md">
            <div class="toolbar toolbar-start">
                <div>
                    <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nhập tên, email hoặc số điện thoại" style="max-width:360px;">
                </div>
                <div class="button-row">
                    <button class="btn" type="submit">Tìm</button>
                </div>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="table-shell">
            <table class="data-table table-compact">
                <thead><tr>
                    <th>Mã</th>
                    <th>Họ tên</th>
                    <th>Ngày sinh</th>
                    <th>Email</th>
                    <th>Điện thoại</th>
                    <th>Trình độ</th>
                    <th>CV</th>
                    <th>Điểm CV</th>
                    <th>Trạng thái</th>
                    <th>Ứng tuyển</th>
                </tr></thead>
                <tbody>
                @forelse ($candidates as $candidate)
                    @php
                        $candidateId = data_get($candidate, 'MaUV')
                            ?? data_get($candidate, 'ma_uv')
                            ?? data_get($candidate, 'id')
                            ?? data_get($candidate, 'MaUngVien');
                        $name = data_get($candidate, 'HoTen');
                        $birthDate = data_get($candidate, 'NgaySinh');
                        $birthDateTs = $birthDate ? strtotime((string) $birthDate) : false;
                        $email = data_get($candidate, 'Email');
                        $phone = data_get($candidate, 'DienThoai');
                        $degree = data_get($candidate, 'TrinhDo');
                        $cvFile = data_get($candidate, 'FileCV');
                        $scoreRaw = data_get($candidate, 'DiemCV');
                        $hasValidScore = $scoreRaw !== null && $scoreRaw !== '' && is_numeric($scoreRaw);
                        $score = $hasValidScore ? (int) $scoreRaw : 0;
                        $candidateStatus = $hasValidScore
                            ? ($score >= 8 ? 'Rất tiềm năng' : ($score >= 6 ? 'Khá' : 'Cần xem lại'))
                            : 'Dữ liệu không hợp lệ';
                        $scoreClass = $score >= 8 ? 'score-high' : ($score >= 6 ? 'score-mid' : 'score-low');
                    @endphp
                    <tr @if($score >= 8) style="background:#edf8ef;" @endif>
                        <td>
                            @if (!empty($candidateId))
                                {{ $candidateId }}
                            @else
                                <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: thiếu mã ứng viên</span>
                            @endif
                        </td>
                        <td>
                            @if (!empty($name))
                                {{ $name }}
                            @else
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn họ tên</span>
                            @endif
                        </td>
                        <td>
                            @if ($birthDate === null || $birthDate === '')
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn ngày sinh</span>
                            @elseif ($birthDateTs === false)
                                <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: ngày sinh</span>
                            @else
                                {{ date('d/m/Y', $birthDateTs) }}
                            @endif
                        </td>
                        <td>
                            @if (!empty($email))
                                {{ $email }}
                            @else
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn email</span>
                            @endif
                        </td>
                        <td>
                            @if (!empty($phone))
                                {{ $phone }}
                            @else
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn số điện thoại</span>
                            @endif
                        </td>
                        <td>
                            @if (!empty($degree))
                                {{ $degree }}
                            @else
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn trình độ</span>
                            @endif
                        </td>
                        <td>
                            @if (!empty($cvFile))
                                <a class="btn btn-secondary" href="{{ route('legacy.upload', ['path' => 'cv/' . ltrim((string) $cvFile, '/')]) }}" target="_blank">Xem CV</a>
                            @else
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn CV</span>
                            @endif
                        </td>
                        <td>
                            @if ($hasValidScore)
                                <strong class="{{ $scoreClass ?? '' }}">{{ $score }}</strong>
                            @elseif ($scoreRaw === null || $scoreRaw === '')
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn điểm CV</span>
                            @else
                                <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: điểm CV</span>
                            @endif
                        </td>
                        <td>
                            @if ($hasValidScore)
                                <strong class="{{ $scoreClass ?? '' }}">{{ $candidateStatus }}</strong>
                            @else
                                <span class="field-status field-status-invalid">Không thể phân loại do điểm CV lỗi</span>
                            @endif
                        </td>
                        <td class="nowrap-cell">
                            @if (in_array('them_ho_so', $permissions, true) && !empty($candidateId))
                                <a class="btn" href="{{ route('tuyendung.ungvien.apply', ['candidate' => $candidateId]) }}">Nộp hồ sơ</a>
                            @elseif (in_array('them_ho_so', $permissions, true))
                                <span class="field-status field-status-invalid">Thiếu mã ứng viên, không thể nộp hồ sơ</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="muted">
                            <div class="empty-state-note">
                                <span>Không có ứng viên phù hợp với điều kiện tìm kiếm.</span>
                                @if (in_array('them_ung_vien', $permissions, true))
                                    <a class="btn btn-secondary" href="{{ route('tuyendung.ungvien.create') }}">Thêm ứng viên mới</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="top-gap-lg">{{ $candidates->links() }}</div>
    </section>
@endsection