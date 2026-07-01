@php $title = $moduleConfig['title'] @endphp
@php $subtitle = $moduleConfig['subtitle'] @endphp
@extends('layouts.app')

@section('content')
    @php
        $hiddenColumns = array_map('strtolower', $moduleConfig['hidden_columns'] ?? []);
        $columns = collect($resourceConfig['columns'])->pluck('field')->filter(fn ($field) => $field !== '__resource_id' && !in_array(strtolower($field), $hiddenColumns, true))->take(8)->values();
        $fieldLabels = (array) ($moduleConfig['field_labels'] ?? []);
        $fieldValueLabels = (array) ($moduleConfig['field_value_labels'] ?? []);
        $disableFilter = (bool) ($moduleConfig['disable_filter'] ?? false);
        $disableExport = (bool) ($moduleConfig['disable_export'] ?? false);
        $hideActions = (bool) ($moduleConfig['hide_actions'] ?? false);
        $isSelfView = (bool) ($isSelfView ?? false);
        $showApprovalProgress = true;
        $showRemainingLeave = true;
        $leaveBalanceSummary = (array) ($currentLeaveBalanceSummary ?? []);
    @endphp

    @if (!empty($leaveBalanceSummary))
    <section class="panel">
        <div class="leave-balance-summary">
            <div class="leave-balance-title">Quỹ phép năm {{ $leaveBalanceSummary['year'] ?? date('Y') }}</div>
            <div class="leave-balance-metrics">
                <span class="leave-balance-badge is-remaining">Còn lại: {{ $leaveBalanceSummary['remaining_days'] ?? 0 }} ngày</span>
                <span class="leave-balance-badge is-used">Đã dùng: {{ $leaveBalanceSummary['used_days'] ?? 0 }} ngày</span>
                <span class="leave-balance-badge is-entitled">Tổng quyền lợi: {{ $leaveBalanceSummary['entitled_days'] ?? 0 }} ngày</span>
            </div>
        </div>
    </section>
    @endif

    <section class="panel">
        @if (!$disableFilter && !$isSelfView)
        <form method="get" class="filter-grid single-wide">
            <div>
                <label for="q" class="wide-search-label">Tìm kiếm</label>
                <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm trong danh mục này">
            </div>
            <div class="button-row">
                <button class="btn" type="submit">Lọc</button>
                @if (!$disableExport && in_array($moduleConfig['permission']['view'], session('quyen', []), true))
                    <a class="btn btn-secondary" href="{{ route(($routeKey ?? $moduleKey) . '.export-excel', request()->only(['q'])) }}">Xuất Excel</a>
                @endif
                @if (!($resourceConfig['read_only'] ?? false) && in_array($moduleConfig['permission']['create'], session('quyen', []), true))
                    <a class="btn btn-secondary" href="{{ route(($routeKey ?? $moduleKey) . '.create') }}">Thêm mới</a>
                @endif
            </div>
        </form>
        @endif
    </section>

    <section class="panel">
        <div class="table-shell">
            <table class="data-table table-compact">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th>{{ $fieldLabels[$column] ?? $column }}</th>
                        @endforeach
                        @if ($showApprovalProgress)
                        <th>Tiến độ duyệt</th>
                        @endif
                        @if ($showRemainingLeave)
                        <th>Phép năm còn lại</th>
                        @endif
                        @if (!$hideActions)
                        <th>Thao tác</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            @foreach ($columns as $column)
                                @php
                                    $cellValue = data_get($item, $column);
                                    $displayValue = is_scalar($cellValue)
                                        ? (($fieldValueLabels[$column][$cellValue] ?? $cellValue))
                                        : $cellValue;
                                @endphp
                                <td>{{ $displayValue }}</td>
                            @endforeach
                            @if ($showApprovalProgress)
                                @php
                                    $approvalProgress = (string) data_get($item, 'ApprovalProgress', '-');
                                    $statusText = \Illuminate\Support\Str::lower(trim((string) data_get($item, 'TrangThai', '')));
                                    $progressLower = \Illuminate\Support\Str::lower($approvalProgress);
                                    $approvedCount = (int) data_get($item, 'ApprovalCount', 0);
                                    $requiredApprovals = max(1, (int) data_get($item, 'RequiredApprovals', 2));
                                    $progressClass = 'approval-progress-badge is-neutral';

                                    if ($statusText === 'từ chối' || \Illuminate\Support\Str::contains($progressLower, 'từ chối')) {
                                        $progressClass = 'approval-progress-badge is-rejected';
                                    } elseif ($statusText === 'đã duyệt' || $approvedCount >= $requiredApprovals) {
                                        $progressClass = 'approval-progress-badge is-approved';
                                    } elseif ($statusText === 'chờ duyệt' && $approvedCount > 0) {
                                        $progressClass = 'approval-progress-badge is-in-progress';
                                    } elseif ($statusText === 'chờ duyệt') {
                                        $progressClass = 'approval-progress-badge is-pending';
                                    }
                                @endphp
                                <td>
                                    <span class="{{ $progressClass }}" title="{{ data_get($item, 'ApprovalTooltip', '') }}">{{ $approvalProgress }}</span>
                                </td>
                            @endif
                            @if ($showRemainingLeave)
                                @php
                                    $remainingLabel = (string) data_get($item, 'RemainingLeaveLabel', '-');
                                    $remainingClass = 'leave-balance-badge is-entitled';
                                    if ($remainingLabel === 'Không áp dụng') {
                                        $remainingClass = 'leave-balance-badge is-na';
                                    } elseif ((int) data_get($item, 'RemainingLeaveDays', 0) <= 2) {
                                        $remainingClass = 'leave-balance-badge is-low';
                                    } else {
                                        $remainingClass = 'leave-balance-badge is-remaining';
                                    }
                                @endphp
                                <td>
                                    <span class="{{ $remainingClass }}" title="{{ data_get($item, 'RemainingLeaveTooltip', '') }}">{{ $remainingLabel }}</span>
                                </td>
                            @endif
                            @if (!$hideActions)
                            <td>
                                @if ($resourceConfig['read_only'] ?? false)
                                    <span class="muted">Chỉ xem</span>
                                @else
                                    <div class="button-row">
                                    @if (in_array($moduleConfig['permission']['update'], session('quyen', []), true))
                                        <a class="btn btn-secondary" href="{{ route(($routeKey ?? $moduleKey) . '.edit', ['record' => data_get($item, '__resource_id')]) }}">Sửa</a>
                                    @endif
                                    @php
                                        $trangThai = strtolower(trim((string) data_get($item, 'TrangThai', '')));
                                        $canApprove = $trangThai === 'chờ duyệt' && in_array('sua_nghiphep', session('quyen', []), true);
                                    @endphp
                                    @if ($canApprove)
                                        <form method="post" action="{{ route('nghiphep.approve', ['record' => data_get($item, '__resource_id')]) }}" class="inline-form">
                                            @csrf
                                            <button class="btn btn-success" type="submit">Duyệt</button>
                                        </form>
                                    @endif
                                    @if (in_array($moduleConfig['permission']['delete'], session('quyen', []), true))
                                        <form method="post" action="{{ route(($routeKey ?? $moduleKey) . '.destroy', ['record' => data_get($item, '__resource_id')]) }}" class="inline-form" onsubmit="return confirm('Xóa bản ghi này?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger" type="submit">Xóa</button>
                                        </form>
                                    @endif
                                    </div>
                                @endif
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $columns->count() + ($showApprovalProgress ? 1 : 0) + ($showRemainingLeave ? 1 : 0) + ($hideActions ? 0 : 1) }}" class="muted">Không có dữ liệu.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="top-gap-lg">{{ $items->links() }}</div>
    </section>
@endsection
