@php $title = ($mode === 'create' ? 'Them' : 'Sua') . ' ' . strtoupper($service) . ' / ' . $resource @endphp
@php $subtitle = 'Cap nhat du lieu tai nguyen truc tiep tu cong ket noi dich vu' @endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="toolbar">
            <div>
                <h2 class="no-top-margin">{{ $mode === 'create' ? 'Them ban ghi' : 'Sua ban ghi' }}</h2>
                <div class="page-note">Bang {{ $resourceConfig['table'] }} | Khoa chinh <span class="code-chip">{{ $resourceConfig['primary_key_label'] ?? (is_array($resourceConfig['primary_key']) ? implode(', ', $resourceConfig['primary_key']) : $resourceConfig['primary_key']) }}</span></div>
            </div>
            <div class="button-row">
                <a class="btn btn-secondary" href="{{ route('services.show', ['service' => $service, 'resource' => $resource]) }}">Về danh sách</a>
            </div>
        </div>
    </section>

    <section class="panel">
        @if (session('success'))
            <div class="flash-alert flash-success flash-inline">
                <div class="flash-title">Lưu dữ liệu thành công</div>
                <div>{{ session('success') }}</div>
                <div class="flash-hint">Bạn có thể tiếp tục thêm bản ghi khác hoặc quay lại danh sách để kiểm tra.</div>
                <div class="flash-actions">
                    <a class="btn btn-secondary" href="{{ route('services.show', ['service' => $service, 'resource' => $resource]) }}">Xem danh sách</a>
                    @if ($mode === 'edit' && !empty($recordId))
                        <a class="btn btn-secondary" href="{{ route('services.edit', ['service' => $service, 'resource' => $resource, 'id' => $recordId]) }}">Tiếp tục chỉnh sửa</a>
                    @endif
                </div>
            </div>
        @endif
        @if ($errors->any())
            <div class="flash-alert flash-error flash-inline">
                <div class="flash-title">Không thể lưu bản ghi</div>
                <div>{{ $errors->first() }}</div>
                <div class="flash-hint">Kiểm tra kiểu dữ liệu từng trường rồi lưu lại.</div>
                <div class="flash-actions">
                    <a class="btn btn-secondary" href="{{ route('services.show', ['service' => $service, 'resource' => $resource]) }}">Quay về danh sách</a>
                </div>
            </div>
        @endif

        @php
            $primaryKeys = is_array($resourceConfig['primary_key']) ? $resourceConfig['primary_key'] : [$resourceConfig['primary_key']];
        @endphp

        <form method="post" action="{{ $mode === 'create' ? route('services.store', ['service' => $service, 'resource' => $resource]) : route('services.update', ['service' => $service, 'resource' => $resource, 'id' => $recordId]) }}">
            @csrf
            @if ($mode === 'edit')
                @method('PUT')
            @endif

            <div class="field-grid">
                @foreach ($resourceConfig['columns'] as $column)
                    @php
                        $field = $column['field'];
                        $isPrimaryKey = in_array($field, $primaryKeys, true);
                        $isAutoIncrement = str_contains($column['extra'], 'auto_increment');
                        $shouldDisable = ($mode === 'edit' && $isPrimaryKey) || $isAutoIncrement;
                        $currentValue = old($field, data_get($record, $field, $column['default']));
                        $inputType = 'text';
                        if (str_contains($column['type'], 'date') && !str_contains($column['type'], 'datetime')) {
                            $inputType = 'date';
                        } elseif (str_contains($column['type'], 'time')) {
                            $inputType = 'time';
                        } elseif (str_contains($column['type'], 'int') || str_contains($column['type'], 'decimal') || str_contains($column['type'], 'float') || str_contains($column['type'], 'double')) {
                            $inputType = 'number';
                        }
                        $isTextarea = str_contains($column['type'], 'text');
                    @endphp

                    <div class="{{ $isTextarea ? 'full-span' : '' }}">
                        <label for="{{ $field }}">{{ $field }}</label>
                        @if ($isTextarea)
                            <textarea id="{{ $field }}" name="{{ $field }}" {{ $shouldDisable ? 'disabled' : '' }}>{{ $currentValue }}</textarea>
                        @else
                            <input
                                id="{{ $field }}"
                                name="{{ $field }}"
                                type="{{ $inputType }}"
                                value="{{ $currentValue }}"
                                {{ $shouldDisable ? 'disabled' : '' }}
                                {{ !$column['nullable'] && !$shouldDisable ? 'required' : '' }}
                            >
                        @endif
                        <div class="muted-inline-note">{{ $column['type'] }} | {{ $column['nullable'] ? 'co the bo trong' : 'bat buoc' }}{{ $column['extra'] ? ' | ' . $column['extra'] : '' }}</div>
                    </div>
                @endforeach
            </div>

            <div class="form-actions-bar">
                <button class="btn" type="submit">{{ $mode === 'create' ? 'Tạo bản ghi' : 'Cập nhật bản ghi' }}</button>
                <a class="btn btn-secondary" href="{{ route('services.show', ['service' => $service, 'resource' => $resource]) }}">Huy</a>
            </div>
        </form>
    </section>
@endsection