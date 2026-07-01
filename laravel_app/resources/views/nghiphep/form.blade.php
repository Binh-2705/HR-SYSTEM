@php
    $title = $mode === 'create' ? 'Thêm ' . $moduleConfig['title'] : 'Sửa ' . $moduleConfig['title'];
    $subtitle = $moduleConfig['subtitle'];
    $fileFields   = $moduleConfig['file_fields'] ?? [];
    $fieldLabels  = $moduleConfig['field_labels'] ?? [];
    $fieldOptions = $moduleConfig['field_options'] ?? [];
    $fieldLookups = $fieldLookups ?? [];
    $activeRouteKey = ($routeKey ?? $moduleKey);
    $editAction = null;
    if ($mode === 'edit') {
        $editAction = route($activeRouteKey . '.update', ['record' => $recordId]);
    }
@endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <form method="post" action="{{ $mode === 'create' ? route($activeRouteKey . '.store') : $editAction }}" {{ count($fileFields) ? 'enctype="multipart/form-data"' : '' }}>
            @csrf
            @if ($mode === 'edit')
                @method('PUT')
            @endif

            @php $primaryKeys = is_array($resourceConfig['primary_key']) ? $resourceConfig['primary_key'] : [$resourceConfig['primary_key']] @endphp
            <div class="field-grid">
                @foreach ($resourceConfig['columns'] as $column)
                    @php
                        $field           = $column['field'];
                        $isPrimaryKey    = in_array($field, $primaryKeys, true);
                        $isAutoIncrement = str_contains($column['extra'], 'auto_increment');
                        $shouldDisable   = ($mode === 'edit' && $isPrimaryKey) || $isAutoIncrement;
                        // Ẩn TrangThai khi tạo mới (create mode)
                        if ($mode === 'create' && $field === 'TrangThai') {
                            continue;
                        }
                        // Ẩn NgayDuyet khi tạo mới (create mode)
                        if ($mode === 'create' && $field === 'NgayDuyet') {
                            continue;
                        }
                        // Disable TrangThai và NgayDuyet ở edit mode (read-only)
                        if ($mode === 'edit' && in_array($field, ['TrangThai', 'NgayDuyet'], true)) {
                            $shouldDisable = true;
                        }
                        // SoNgayNghi là readonly (tính tự động)
                        $isReadonly = $field === 'SoNgayNghi';
                        $value           = old($field, data_get($record, $field, $column['default']));
                        $label           = $fieldLabels[$field] ?? $field;
                        $isTextarea      = str_contains($column['type'], 'text');
                        $isEnum          = str_starts_with($column['type'], 'enum(');
                        $inputType       = 'text';
                        if (str_contains($column['type'], 'date') && !str_contains($column['type'], 'datetime')) {
                            $inputType = 'date';
                        } elseif (str_contains($column['type'], 'time')) {
                            $inputType = 'time';
                        } elseif (str_contains($column['type'], 'int') || str_contains($column['type'], 'decimal') || str_contains($column['type'], 'float') || str_contains($column['type'], 'double')) {
                            $inputType = 'number';
                        }
                        // Parse enum values from type string e.g. enum('A','B')
                        $enumValues = [];
                        if ($isEnum) {
                            preg_match_all("/'([^']+)'/", $column['type'], $em);
                            $enumValues = $em[1] ?? [];
                        }
                        // Config-defined options override enum parsing
                        if (!empty($fieldOptions[$field])) {
                            $isEnum     = true;
                            $enumValues = $fieldOptions[$field];
                        }
                        // Dynamic DB lookup overrides everything else
                        $lookupOptions = $fieldLookups[$field] ?? null;
                    @endphp
                    <div class="{{ $isTextarea ? 'full-span' : '' }}">
                        <label for="{{ $field }}">{{ $label }}</label>
                        @if (in_array($field, $fileFields, true))
                            @if ($value)
                                <div class="top-gap-sm" style="margin-bottom:6px">
                                    <img src="{{ route('legacy.upload', ['path' => 'photos/' . $value]) }}" alt="Ảnh hiện tại" style="max-height:80px;border-radius:4px;border:1px solid #e5e7eb;">
                                </div>
                            @endif
                            <input id="{{ $field }}" name="{{ $field }}" type="file" accept="image/*" {{ $shouldDisable ? 'disabled' : '' }}>
                        @elseif ($lookupOptions !== null)
                            <select id="{{ $field }}" name="{{ $field }}" {{ $shouldDisable ? 'disabled' : '' }} {{ !$column['nullable'] && !$shouldDisable ? 'required' : '' }}>
                                <option value="">-- Chọn --</option>
                                @foreach ($lookupOptions as $opt)
                                    <option value="{{ $opt['value'] }}" {{ (string)$value === (string)$opt['value'] ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        @elseif ($isEnum)
                            <select id="{{ $field }}" name="{{ $field }}" {{ $shouldDisable ? 'disabled' : '' }} {{ !$column['nullable'] && !$shouldDisable ? 'required' : '' }}>
                                @if ($column['nullable'])
                                    <option value="">-- Chọn --</option>
                                @endif
                                @foreach ($enumValues as $opt)
                                    <option value="{{ $opt }}" {{ $value == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                        @elseif ($isTextarea)
                            <textarea id="{{ $field }}" name="{{ $field }}" {{ $shouldDisable ? 'disabled' : '' }}>{{ $value }}</textarea>
                        @else
                            <input id="{{ $field }}" name="{{ $field }}" type="{{ $inputType }}" value="{{ $value }}" {{ $shouldDisable ? 'disabled' : '' }} {{ $isReadonly ? 'readonly' : '' }} {{ !$column['nullable'] && !$shouldDisable ? 'required' : '' }}>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="form-actions-bar">
                <button class="btn" type="submit">{{ $mode === 'create' ? 'Tạo bản ghi' : 'Cập nhật bản ghi' }}</button>
                <a class="btn btn-secondary" href="{{ route($activeRouteKey . '.index') }}">Về danh sách</a>
            </div>
        </form>
    </section>

    @push('page_scripts')
    <script>
        function initializeNgayNghiCalculation() {
            const tuNgayInput = document.getElementById('TuNgay');
            const denNgayInput = document.getElementById('DenNgay');
            const soNgayNghiInput = document.getElementById('SoNgayNghi');

            if (!tuNgayInput || !denNgayInput || !soNgayNghiInput) {
                console.warn('Không tìm thấy các input cần thiết:', {
                    tuNgay: !!tuNgayInput,
                    denNgay: !!denNgayInput,
                    soNgayNghi: !!soNgayNghiInput
                });
                return;
            }

            function calculateDays() {
                const tuValue = tuNgayInput.value.trim();
                const denValue = denNgayInput.value.trim();

                if (!tuValue || !denValue) {
                    soNgayNghiInput.value = '';
                    return;
                }

                try {
                    // Parse dates: handle YYYY-MM-DD format
                    const tuDate = new Date(tuValue + 'T00:00:00Z');
                    const denDate = new Date(denValue + 'T00:00:00Z');

                    if (isNaN(tuDate.getTime()) || isNaN(denDate.getTime())) {
                        console.warn('Ngày không hợp lệ:', tuValue, denValue);
                        soNgayNghiInput.value = '';
                        return;
                    }

                    if (denDate < tuDate) {
                        soNgayNghiInput.value = '';
                        return;
                    }

                    // Calculate days difference: (denDate - tuDate) / ms per day + 1
                    const diffMs = denDate - tuDate;
                    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24)) + 1;
                    soNgayNghiInput.value = diffDays > 0 ? diffDays : '';
                } catch (error) {
                    console.error('Lỗi tính toán ngày:', error);
                    soNgayNghiInput.value = '';
                }
            }

            // Attach event listeners to both change and input events
            tuNgayInput.addEventListener('change', calculateDays);
            tuNgayInput.addEventListener('input', calculateDays);
            denNgayInput.addEventListener('change', calculateDays);
            denNgayInput.addEventListener('input', calculateDays);

            // Calculate on page load (for edit mode)
            calculateDays();
        }

        // Run after DOM is fully loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeNgayNghiCalculation);
        } else {
            initializeNgayNghiCalculation();
        }
    </script>
    @endpush
@endsection
