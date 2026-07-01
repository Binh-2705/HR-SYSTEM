@php
    $title = 'Chọn đợt tuyển';
    $subtitle = 'Nộp hồ sơ ứng viên vào đợt tuyển phù hợp';
@endphp
@extends('layouts.app')

@section('content')
    @php
        $candidateId = data_get($candidate, 'MaUV', data_get($candidate, 'id'));
    @endphp
    <section class="panel">
        <div class="toolbar toolbar-start">
            <div>
                <div><strong>Ứng viên:</strong> {{ data_get($candidate, 'HoTen', 'N/A') }}</div>
                <div class="muted top-gap-sm">{{ data_get($candidate, 'Email', 'Chưa có email') }} | {{ data_get($candidate, 'DienThoai', 'Chưa có số điện thoại') }} | Điểm CV: {{ data_get($candidate, 'DiemCV', 0) }}/10</div>
            </div>
            <a class="btn btn-secondary" href="{{ route('tuyendung.ungvien.index') }}">← Quay lại</a>
        </div>
    </section>

    <section class="panel">
        <div class="table-shell">
            <table class="data-table table-compact">
                <thead>
                    <tr>
                        <th>Tên đợt tuyển</th>
                        <th>Vị trí tuyển</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campaigns as $campaign)
                        @php
                            $campaignId = data_get($campaign, 'MaDTD', data_get($campaign, 'id'));
                            $campaignName = data_get($campaign, 'TenDotTuyenDung', 'N/A');
                            $campaignPosition = data_get($campaign, 'ViTriTuyenDung', 'N/A');
                        @endphp
                        <tr>
                            <td>{{ $campaignName }}</td>
                            <td>{{ $campaignPosition }}</td>
                            <td class="nowrap-cell">
                                <form method="post" action="{{ route('tuyendung.ungvien.attach', ['candidate' => $candidateId]) }}" class="inline-form">
                                    @csrf
                                    <input type="hidden" name="MaDTD" value="{{ $campaignId }}">
                                    <button class="btn" type="submit">Nộp hồ sơ</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="muted">Không có đợt tuyển để nộp hồ sơ.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection