@php
    $title = $mode === 'create' ? 'Thêm ' . $moduleConfig['title'] : 'Sửa ' . $moduleConfig['title'];
    $subtitle = $moduleConfig['subtitle'];
    
    // Fetch salary grades with ngach information for MaBac dropdown grouping
    $conn = config('service_registry.services.payroll.connection', 'payroll');
    $allSalaryGrades = DB::connection($conn)
        ->table('bacluong as b')
        ->leftJoin('ngachluong as n', 'b.MaNgach', '=', 'n.MaNgach')
        ->select('b.MaBac', 'b.TenBac', 'b.HeSoLuong', 'b.LuongCoSo', 'n.TenNgach')
        ->orderBy('n.TenNgach')
        ->orderBy('b.HeSoLuong')
        ->get();
    
    // Group by TenNgach for optgroup rendering
    $groupedSalaryGrades = $allSalaryGrades->groupBy('TenNgach');
@endphp
@extends('layouts.app')

@section('content')
    @include('resource_modules.partials.form_content', ['groupedSalaryGrades' => $groupedSalaryGrades])
@endsection

@push('page_scripts')
<script>
    (function () {
        const start = document.getElementById('NgayBatDau');
        const end = document.getElementById('NgayKetThuc');
        if (!start || !end) return;

        let errorEl = document.getElementById('NgayKetThuc-error');
        if (!errorEl) {
            errorEl = document.createElement('span');
            errorEl.id = 'NgayKetThuc-error';
            errorEl.className = 'field-error';
            errorEl.style.display = 'none';
            end.parentNode.appendChild(errorEl);
        }

        function showError(msg) {
            errorEl.textContent = msg;
            errorEl.style.display = 'block';
            end.style.borderColor = '#e3342f';
            end.setCustomValidity('invalid');
        }

        function clearError() {
            errorEl.style.display = 'none';
            end.style.borderColor = '';
            end.setCustomValidity('');
        }

        start.addEventListener('change', function () {
            if (start.value) {
                end.min = start.value;
            }
            if (end.value && start.value && end.value < start.value) {
                end.value = '';
                showError('Ngày kết thúc phải từ ngày bắt đầu trở đi.');
            } else {
                clearError();
            }
        });

        end.addEventListener('change', function () {
            if (end.value && start.value && end.value < start.value) {
                end.value = '';
                showError('Ngày kết thúc phải từ ngày bắt đầu trở đi.');
            } else {
                clearError();
            }
        });

        if (start.value) end.min = start.value;
    })();
</script>
@endpush
