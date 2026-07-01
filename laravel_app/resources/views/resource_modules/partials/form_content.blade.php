@php
    $fileFields   = $moduleConfig['file_fields'] ?? [];
    $fieldLabels  = $moduleConfig['field_labels'] ?? [];
    $fieldOptions = $moduleConfig['field_options'] ?? [];
    $fieldLookups = $fieldLookups ?? [];
    $hiddenFormColumns = array_map('strtolower', (array) ($moduleConfig['hidden_form_columns'] ?? []));
    $activeRouteKey = ($routeKey ?? $moduleKey);
    $editAction = null;
    if ($mode === 'edit') {
        $editAction = route($activeRouteKey . '.update', ['record' => $recordId]);
        if ($activeRouteKey === 'phancong') {
            $editAction = route('phancong.save', ['record' => $recordId]);
        } elseif ($activeRouteKey === 'hopdong') {
            $editAction = route('hopdong.save', ['record' => $recordId]);
        }
    }
@endphp
<section class="panel">
    @if ($errors->any())
        <div class="auth-alert auth-alert-error" style="margin-bottom:12px">
            @foreach ($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
    @endif
    <form method="post" action="{{ $mode === 'create' ? route($activeRouteKey . '.store') : $editAction }}" {{ count($fileFields) ? 'enctype="multipart/form-data"' : '' }}>
        @csrf
        @if ($mode === 'edit' && $activeRouteKey !== 'phancong' && $activeRouteKey !== 'hopdong')
            @method('PUT')
        @endif

        @php $primaryKeys = is_array($resourceConfig['primary_key']) ? $resourceConfig['primary_key'] : [$resourceConfig['primary_key']] @endphp
        <div class="field-grid">
            @foreach ($resourceConfig['columns'] as $column)
                @php
                    $field           = $column['field'];
                    if (in_array(strtolower((string) $field), $hiddenFormColumns, true)) {
                        continue;
                    }
                    $isPrimaryKey    = in_array($field, $primaryKeys, true);
                    $isAutoIncrement = str_contains($column['extra'], 'auto_increment');
                    $shouldDisable   = ($mode === 'edit' && $isPrimaryKey) || $isAutoIncrement;
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
                    @elseif ($field === 'MaBac')
                        {{-- Special grouping for salary grades by ngạch lương --}}
                        @php
                            // Use provided groupedSalaryGrades or fetch if not available
                            if (!isset($groupedSalaryGrades) || empty($groupedSalaryGrades)) {
                                $conn = config('service_registry.services.payroll.connection', 'payroll');
                                $allSalaryGrades = DB::connection($conn)
                                    ->table('bacluong as b')
                                    ->leftJoin('ngachluong as n', 'b.MaNgach', '=', 'n.MaNgach')
                                    ->select('b.MaBac', 'b.TenBac', 'b.HeSoLuong', 'b.LuongCoSo', 'n.TenNgach')
                                    ->orderBy('n.TenNgach')
                                    ->orderBy('b.HeSoLuong')
                                    ->get();
                                $groupedSalaryGrades = $allSalaryGrades->groupBy('TenNgach');
                            }
                        @endphp
                        @if (!empty($groupedSalaryGrades))
                            <select id="{{ $field }}" name="{{ $field }}" {{ $shouldDisable ? 'disabled' : '' }} {{ !$column['nullable'] && !$shouldDisable ? 'required' : '' }}>
                                <option value="">-- Chọn --</option>
                                @foreach ($groupedSalaryGrades as $ngach => $grades)
                                    <optgroup label="{{ $ngach ?: 'Khác' }}">
                                        @foreach ($grades as $grade)
                                            @php
                                                $luong = number_format((float)$grade->HeSoLuong * (float)($grade->LuongCoSo ?? 5310000), 0, ',', '.');
                                            @endphp
                                            <option value="{{ $grade->MaBac }}"
                                                {{ (string)$value === (string)$grade->MaBac ? 'selected' : '' }}>
                                                {{ $grade->TenBac }} — HS: {{ $grade->HeSoLuong }} ({{ $luong }} VNĐ)
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        @elseif ($lookupOptions !== null)
                            {{-- Fallback to regular lookup if grouped data fetch failed --}}
                            <select id="{{ $field }}" name="{{ $field }}" {{ $shouldDisable ? 'disabled' : '' }} {{ !$column['nullable'] && !$shouldDisable ? 'required' : '' }}>
                                <option value="">-- Chọn --</option>
                                @foreach ($lookupOptions as $opt)
                                    <option value="{{ $opt['value'] }}" {{ (string)$value === (string)$opt['value'] ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        @endif
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
                        <input id="{{ $field }}" name="{{ $field }}" type="{{ $inputType }}" value="{{ $value }}" {{ $shouldDisable ? 'disabled' : '' }} {{ !$column['nullable'] && !$shouldDisable ? 'required' : '' }}>
                    @endif
                    @error($field)<span class="field-error">{{ $message }}</span>@enderror
                </div>
            @endforeach
        </div>

        <div class="form-actions-bar">
            <button class="btn" type="submit">{{ $mode === 'create' ? 'Tạo bản ghi' : 'Cập nhật bản ghi' }}</button>
            <a class="btn btn-secondary" href="{{ route($activeRouteKey . '.index') }}">Về danh sách</a>
        </div>
    </form>
</section>
