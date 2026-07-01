@php
    $title    = 'Bảng bậc lương';
    $subtitle = 'Danh sách bậc lương theo ngạch lương trong hệ thống';

    // Load dữ liệu trực tiếp nếu không được truyền từ route
    if (!isset($rows)) {
        $conn = config('service_registry.services.payroll.connection', config('database.default'));
        $rows = \Illuminate\Support\Facades\DB::connection($conn)
            ->table('bacluong as b')
            ->leftJoin('ngachluong as n', 'b.MaNgach', '=', 'n.MaNgach')
            ->select('b.*', 'n.TenNgach')
            ->orderBy('n.TenNgach')
            ->orderBy('b.HeSoLuong')
            ->get();
        $groupSizes = $rows->groupBy('TenNgach')->map->count()->all();
        $luongCoSo  = (float) ($rows->first()?->LuongCoSo ?? 5310000);
    }
@endphp
@extends('layouts.app')

@section('content')

<section class="panel">
    <div class="panel-header-row">
        <div>
            <div class="metric-strong">Bảng bậc lương</div>
            <div class="muted top-gap-sm">Lương cơ sở hiện hành:
                <strong>{{ number_format($luongCoSo, 0, ',', '.') }} VNĐ</strong>
            </div>
        </div>
    </div>
</section>

<section class="panel">
    @if ($rows->isEmpty())
        <div class="muted">Chưa có dữ liệu bậc lương.</div>
    @else
    <div class="table-shell">
        <table class="data-table table-compact">
            <thead>
                <tr>
                    <th style="width:52px; text-align:center">STT</th>
                    <th>Ngạch lương</th>
                    <th>Tên bậc</th>
                    <th style="text-align:center">Hệ số</th>
                    <th style="text-align:right">Lương cơ sở</th>
                    <th style="text-align:right">Lương tính</th>
                </tr>
            </thead>
            <tbody>
                @php $stt = 1; $prevNgach = null; @endphp
                @foreach ($rows as $row)
                @php
                    $isFirstInGroup = $row->TenNgach !== $prevNgach;
                    $groupSize      = $groupSizes[$row->TenNgach] ?? 1;
                    $base           = (float) ($row->LuongCoSo ?? $luongCoSo);
                    $heso           = (float) ($row->HeSoLuong ?? 0);
                    $luongTinh      = $heso * $base;
                @endphp
                <tr>
                    <td style="text-align:center">{{ $stt++ }}</td>
                    @if ($isFirstInGroup)
                    <td rowspan="{{ $groupSize }}"
                        style="vertical-align:middle; font-weight:600; text-align:center; background:var(--surface-alt,#f8f9fa)">
                        {{ $row->TenNgach ?? '—' }}
                    </td>
                    @endif
                    <td>{{ $row->TenBac ?? '—' }}</td>
                    <td style="text-align:center">{{ $row->HeSoLuong }}</td>
                    <td style="text-align:right">
                        {{ number_format($base, 0, ',', '.') }} VNĐ
                    </td>
                    <td style="text-align:right; color:var(--danger,#e3342f); font-weight:600">
                        {{ number_format($luongTinh, 0, ',', '.') }} VNĐ
                    </td>
                </tr>
                @php $prevNgach = $row->TenNgach; @endphp
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</section>

@endsection
