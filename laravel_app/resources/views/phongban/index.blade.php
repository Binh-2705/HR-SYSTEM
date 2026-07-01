@php $title = 'Phòng ban' @endphp
@php $subtitle = 'Danh sách phòng ban' @endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="table-shell">
            <table class="data-table table-compact">
                <thead>
                    <tr>
                        <th>Mã PB</th>
                        <th>Tên phòng ban</th>
                        <th>Mô tả</th>
                        <th>Số nhân viên</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departments as $department)
                        <tr>
                            <td>{{ $department->MaPB }}</td>
                            <td><strong>{{ $department->TenPB }}</strong></td>
                            <td>{{ $department->MoTa ?: 'Không có mô tả' }}</td>
                            <td>{{ $department->SoNhanVien }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted">Không có phòng ban.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($departments->lastPage() > 1)
            <div class="top-gap-lg">{{ $departments->links() }}</div>
        @endif
    </section>
@endsection