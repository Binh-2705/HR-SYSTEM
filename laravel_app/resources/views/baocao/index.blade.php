@php
    $title = 'Báo cáo';
    $subtitle = 'Quản trị danh mục báo cáo trên hệ thống';
@endphp
@extends('layouts.app')

@section('content')
<section class="panel">
    <form method="get" class="filter-grid">
        <div>
            <label for="q" class="wide-search-label">Tìm kiếm</label>
            <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tên báo cáo hoặc người tạo">
        </div>
        <div>
            <label for="type" class="wide-search-label">Loại báo cáo</label>
            <select id="type" name="type">
                <option value="">Tất cả</option>
                @foreach (['Nhân sự','Chấm công','Nghỉ phép','Hợp đồng','Lương'] as $type)
                    <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div class="button-row">
            <button class="btn" type="submit">Lọc</button>
            @if (in_array('them_baocao', session('quyen', []), true))
                <a class="btn btn-secondary" href="{{ route('baocao.create') }}">Thêm mới</a>
            @endif
            @if (in_array('xuatex_baocao', session('quyen', []), true))
                <a class="btn btn-secondary" href="{{ route('baocao.export-excel', request()->only(['q', 'type'])) }}">Xuất Excel</a>
                <a class="btn btn-secondary" href="{{ route('baocao.export-json', request()->only(['q', 'type'])) }}">Xuất JSON</a>
            @endif
        </div>
    </form>
</section>

<section class="panel">
    <div class="table-shell">
        <table class="data-table table-compact">
            <thead>
                <tr>
                    <th>MãBC</th>
                    <th>Tên báo cáo</th>
                    <th>Loại</th>
                    <th>Người tạo</th>
                    <th>Thời điểm tạo</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reports as $report)
                    <tr>
                        <td>{{ $report->MaBC }}</td>
                        <td><strong>{{ $report->TenBaoCao }}</strong></td>
                        <td>{{ $report->LoaiBaoCao }}</td>
                        <td>{{ $report->NguoiTao ?: 'system' }}</td>
                        <td>{{ $report->ThoiDiemTao }}</td>
                        <td>
                            <div class="button-row">
                                @if (in_array('sua_baocao', session('quyen', []), true) || in_array('them_baocao', session('quyen', []), true))
                                    <a class="btn btn-secondary" href="{{ route('baocao.edit', ['report' => $report->MaBC]) }}">Sửa</a>
                                @endif
                                @if (in_array('xoa_baocao', session('quyen', []), true))
                                    <form method="post" action="{{ route('baocao.destroy', ['report' => $report->MaBC]) }}" class="inline-form" onsubmit="return confirm('Xóa báo cáo này?');">
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
                        <td colspan="6" class="muted">Không có dữ liệu báo cáo.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="top-gap-lg">{{ $reports->links() }}</div>
</section>
@endsection