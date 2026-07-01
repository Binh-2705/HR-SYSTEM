@php $title = strtoupper($service) . ' / ' . $resource @endphp
@php $subtitle = 'Chi tiết tài nguyên và dữ liệu đang được map qua service registry' @endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="toolbar">
            <div>
                <h2 class="no-top-margin">{{ $service }} / {{ $resource }}</h2>
                <div class="page-note">
                    Kết nối: <span class="code-chip">{{ $payload['connection'] }}</span>
                    Bảng: <span class="code-chip">{{ $resourceConfig['table'] }}</span>
                    Khóa chính: <span class="code-chip">{{ $resourceConfig['primary_key_label'] ?? (is_array($resourceConfig['primary_key']) ? implode(', ', $resourceConfig['primary_key']) : $resourceConfig['primary_key']) }}</span>
                </div>
                <div class="page-note top-gap-md">API: <span class="code-chip">Dữ liệu được truy cập trực tiếp theo service cấu hình</span></div>
                <div class="page-note top-gap-sm">Token API đã cấu hình: <strong>{{ $apiTokenConfigured ? 'có' : 'chưa' }}</strong></div>
                @if ($resourceConfig['read_only'] ?? false)
                    <div class="page-note top-gap-sm">Chế độ tài nguyên: <strong>chỉ xem</strong></div>
                @endif
            </div>
            <div class="button-row">
                <a class="btn btn-secondary" href="{{ route('services.index') }}">Về bảng dịch vụ</a>
                @if (!($resourceConfig['read_only'] ?? false))
                    <a class="btn" href="{{ route('services.create', ['service' => $service, 'resource' => $resource]) }}">Thêm bản ghi</a>
                @endif
            </div>
        </div>
    </section>

    <section class="panel">
        @if (session('success'))
            <div class="flash-alert flash-success flash-inline">
                <div class="flash-title">Thao tác dữ liệu thành công</div>
                <div>{{ session('success') }}</div>
                <div class="flash-hint">Tiếp tục rà soát danh sách để xác nhận dữ liệu vừa thay đổi.</div>
                <div class="flash-actions">
                    @if (!($resourceConfig['read_only'] ?? false))
                        <a class="btn btn-secondary" href="{{ route('services.create', ['service' => $service, 'resource' => $resource]) }}">Thêm bản ghi mới</a>
                    @endif
                    <a class="btn btn-secondary" href="{{ route('services.index') }}">Về bảng dịch vụ</a>
                </div>
            </div>
        @endif

        <h3 class="no-top-margin">Danh sách dữ liệu</h3>
        <div class="page-note">Trang {{ $payload['pagination']['page'] }} | Limit {{ $payload['pagination']['limit'] }} | Tổng bản ghi {{ $payload['pagination']['total'] }}</div>

        @php
            $items = collect($payload['data']);
            $columns = $items->isNotEmpty() ? array_values(array_filter(array_keys((array) $items->first()), static fn ($column) => $column !== '__resource_id')) : [];
            $hasPrevious = $payload['pagination']['page'] > 1;
            $hasNext = ($payload['pagination']['page'] * $payload['pagination']['limit']) < $payload['pagination']['total'];
        @endphp

        @if ($items->isEmpty())
            <div class="service-empty top-gap-lg">
                <div>Không có dữ liệu phù hợp cho tài nguyên này.</div>
                @if (!($resourceConfig['read_only'] ?? false))
                    <div class="top-gap-sm"><a class="btn btn-secondary" href="{{ route('services.create', ['service' => $service, 'resource' => $resource]) }}">Thêm bản ghi đầu tiên</a></div>
                @endif
            </div>
        @else
            <div class="table-shell top-gap-lg">
                <table class="data-table table-compact">
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                            @if (!($resourceConfig['read_only'] ?? false))
                                <th>Thao tác</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                @foreach ($columns as $column)
                                    <td>{{ data_get((array) $item, $column) }}</td>
                                @endforeach
                                @if (!($resourceConfig['read_only'] ?? false))
                                    <td>
                                        <div class="button-row">
                                            <a class="btn btn-secondary" href="{{ route('services.edit', ['service' => $service, 'resource' => $resource, 'id' => data_get((array) $item, '__resource_id')]) }}">Sửa</a>
                                            <form method="post" action="{{ route('services.destroy', ['service' => $service, 'resource' => $resource, 'id' => data_get((array) $item, '__resource_id')]) }}" class="inline-form" onsubmit="return confirm('Xóa bản ghi này?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger" type="submit">Xóa</button>
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pager-links">
                @if ($hasPrevious)
                    <a class="btn btn-secondary" href="{{ route('services.show', ['service' => $service, 'resource' => $resource, 'page' => $payload['pagination']['page'] - 1, 'limit' => $payload['pagination']['limit']]) }}">Trang trước</a>
                @endif
                @if ($hasNext)
                    <a class="btn btn-secondary" href="{{ route('services.show', ['service' => $service, 'resource' => $resource, 'page' => $payload['pagination']['page'] + 1, 'limit' => $payload['pagination']['limit']]) }}">Trang sau</a>
                @endif
            </div>
        @endif
    </section>
@endsection