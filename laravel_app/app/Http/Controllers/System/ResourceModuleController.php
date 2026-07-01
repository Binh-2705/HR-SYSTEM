<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;

use App\Services\GenericResourceModuleService;
use App\Services\InternalApiClient;
use App\Services\ServiceResourceGateway;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;
use LogicException;

class ResourceModuleController extends Controller
{
    public function __construct(
        private GenericResourceModuleService $modules,
        private InternalApiClient $client,
        private ServiceResourceGateway $gateway,
    ) {}

    public function index(Request $request, string $module): View
    {
        $meta = $this->modules->describe($module);

        $filters = $request->only(['q']);

        // NhanVien can only see their own records for personal modules
        if (in_array($module, ['employee-profiles', 'assignments', 'contracts', 'leave-requests', 'insurances'], true)) {
            $account = (array) session('taikhoan', []);
            $role = strtolower(trim((string) ($account['VaiTro'] ?? '')));
            $ownMaNV = (int) ($account['MaNV'] ?? 0);
            if ($role === 'nhanvien' && $ownMaNV > 0) {
                $filters['ma_nv'] = $ownMaNV;
            }
        }

        $items = $this->modules->paginate($module, $filters);
        $items->appends($request->query());
        $currentLeaveBalanceSummary = null;

        if ($module === 'leave-requests') {
            $requiredApprovals = max(1, (int) config('approval_workflows.leave_requests.required_approvals', 2));
            $defaultEntitledDays = max(0, (int) config('approval_workflows.leave_requests.leave_balance.default_entitled_days', 12));
            $deductibleTypes = (array) config('approval_workflows.leave_requests.leave_balance.deductible_types', ['Nghỉ phép năm']);
            $connection = (string) config('service_registry.services.hr.connection', config('database.default'));
            $hasLeaveBalanceTable = Schema::connection($connection)->hasTable('leave_balances');

            $approvalCounts = [];
            $approvalActionsByLeave = [];
            $leaveBalanceMap = [];
            if (Schema::connection($connection)->hasTable('leave_request_approval_actions')) {
                $leaveIds = collect($items->items())
                    ->pluck('MaNP')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values();

                if ($leaveIds->isNotEmpty()) {
                    $approvalCounts = DB::connection($connection)
                        ->table('leave_request_approval_actions')
                        ->selectRaw('MaNP, COUNT(DISTINCT MaTK) as total')
                        ->whereIn('MaNP', $leaveIds->all())
                        ->where('ActionName', 'approved')
                        ->groupBy('MaNP')
                        ->pluck('total', 'MaNP')
                        ->map(fn ($value) => (int) $value)
                        ->all();

                    $actions = DB::connection($connection)
                        ->table('leave_request_approval_actions as lra')
                        ->leftJoin('taikhoan as tk', 'lra.MaTK', '=', 'tk.MaTK')
                        ->leftJoin('nhanvien as nv', 'tk.MaNV', '=', 'nv.MaNV')
                        ->whereIn('lra.MaNP', $leaveIds->all())
                        ->orderBy('lra.MaNP')
                        ->orderBy('lra.created_at')
                        ->get([
                            'lra.MaNP',
                            'lra.MaTK',
                            'lra.ActionName',
                            'lra.ApproverRole',
                            'lra.created_at',
                            'tk.TenDangNhap',
                            'nv.HoTen',
                        ]);

                    foreach ($actions as $action) {
                        $leaveId = (int) ($action->MaNP ?? 0);
                        if ($leaveId <= 0) {
                            continue;
                        }

                        $name = trim((string) ($action->HoTen ?? ''));
                        if ($name === '') {
                            $name = trim((string) ($action->TenDangNhap ?? ''));
                        }
                        if ($name === '') {
                            $name = 'Tài khoản #' . (int) ($action->MaTK ?? 0);
                        }

                        $rawTime = (string) ($action->created_at ?? '');
                        $timestamp = strtotime($rawTime);
                        $timeLabel = $timestamp ? date('d/m/Y H:i', $timestamp) : '';

                        $approvalActionsByLeave[$leaveId][] = [
                            'action' => trim((string) ($action->ActionName ?? '')),
                            'name' => $name,
                            'role' => trim((string) ($action->ApproverRole ?? '')),
                            'at' => $timeLabel,
                        ];
                    }
                }
            }

            if ($hasLeaveBalanceTable) {
                $employeeIds = collect($items->items())
                    ->pluck('MaNV')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values();

                $years = collect($items->items())
                    ->map(function ($item) {
                        $timestamp = strtotime((string) ($item->TuNgay ?? ''));

                        return (int) ($timestamp ? date('Y', $timestamp) : date('Y'));
                    })
                    ->filter(fn ($year) => $year > 0)
                    ->unique()
                    ->values();

                if ($employeeIds->isNotEmpty() && $years->isNotEmpty()) {
                    $balances = DB::connection($connection)
                        ->table('leave_balances')
                        ->whereIn('MaNV', $employeeIds->all())
                        ->whereIn('Nam', $years->all())
                        ->get(['MaNV', 'Nam', 'EntitledDays', 'UsedDays']);

                    foreach ($balances as $balance) {
                        $key = (int) ($balance->MaNV ?? 0) . '|' . (int) ($balance->Nam ?? 0);
                        $leaveBalanceMap[$key] = [
                            'entitled' => max(0, (int) ($balance->EntitledDays ?? 0)),
                            'used' => max(0, (int) ($balance->UsedDays ?? 0)),
                        ];
                    }
                }
            }

            $normalizeLeaveType = static function (string $value): string {
                $ascii = Str::ascii($value);

                return strtolower((string) preg_replace('/[^a-z0-9]/', '', $ascii));
            };
            $deductibleTypeMap = [];
            foreach ($deductibleTypes as $type) {
                $deductibleTypeMap[$normalizeLeaveType((string) $type)] = true;
            }

            $account = (array) session('taikhoan', []);
            $currentEmployeeId = (int) ($account['MaNV'] ?? 0);
            $currentYear = (int) date('Y');
            if ($currentEmployeeId > 0) {
                $summaryEntitled = $defaultEntitledDays;
                $summaryUsed = 0;

                if ($hasLeaveBalanceTable) {
                    $summary = DB::connection($connection)
                        ->table('leave_balances')
                        ->where('MaNV', $currentEmployeeId)
                        ->where('Nam', $currentYear)
                        ->first(['EntitledDays', 'UsedDays']);

                    if ($summary !== null) {
                        $summaryEntitled = max(0, (int) ($summary->EntitledDays ?? $defaultEntitledDays));
                        $summaryUsed = max(0, (int) ($summary->UsedDays ?? 0));
                    }
                }

                $currentLeaveBalanceSummary = [
                    'year' => $currentYear,
                    'entitled_days' => $summaryEntitled,
                    'used_days' => $summaryUsed,
                    'remaining_days' => max(0, $summaryEntitled - $summaryUsed),
                ];
            }

            $transformedItems = collect($items->items())->map(function ($item) use ($approvalCounts, $requiredApprovals, $approvalActionsByLeave, $leaveBalanceMap, $defaultEntitledDays, $normalizeLeaveType, $deductibleTypeMap) {
                $status = trim((string) ($item->TrangThai ?? ''));
                $leaveId = (int) ($item->MaNP ?? 0);
                $approved = (int) ($approvalCounts[$leaveId] ?? 0);
                $actions = $approvalActionsByLeave[$leaveId] ?? [];
                $employeeId = (int) ($item->MaNV ?? 0);
                $leaveYearTimestamp = strtotime((string) ($item->TuNgay ?? ''));
                $leaveYear = (int) ($leaveYearTimestamp ? date('Y', $leaveYearTimestamp) : date('Y'));
                $leaveTypeKey = $normalizeLeaveType((string) ($item->LoaiNghi ?? ''));
                $isDeductible = isset($deductibleTypeMap[$leaveTypeKey]);

                $balanceKey = $employeeId . '|' . $leaveYear;
                $balance = $leaveBalanceMap[$balanceKey] ?? [
                    'entitled' => $defaultEntitledDays,
                    'used' => 0,
                ];
                $remainingDays = max(0, (int) $balance['entitled'] - (int) $balance['used']);

                $approvedActions = [];
                $rejectedAction = null;
                foreach ($actions as $action) {
                    if (($action['action'] ?? '') === 'approved') {
                        $approvedActions[] = $action;
                    }
                    if (($action['action'] ?? '') === 'rejected' && $rejectedAction === null) {
                        $rejectedAction = $action;
                    }
                }

                if ($status === 'Đã duyệt') {
                    $progressLabel = 'Hoàn tất ' . $requiredApprovals . '/' . $requiredApprovals;
                } elseif ($status === 'Từ chối') {
                    $progressLabel = 'Đã từ chối';
                } elseif ($status === 'Chờ duyệt') {
                    if ($approved > 0) {
                        $progressLabel = 'Đang duyệt ' . min($approved, $requiredApprovals) . '/' . $requiredApprovals;
                    } else {
                        $progressLabel = 'Chờ bước 1/' . $requiredApprovals;
                    }
                } else {
                    $progressLabel = '-';
                }

                $effectiveApproved = $status === 'Đã duyệt'
                    ? $requiredApprovals
                    : min($approved, $requiredApprovals);

                $tooltipLines = ['Tiến độ hệ thống: ' . $effectiveApproved . '/' . $requiredApprovals];
                if (count($approvedActions) > 0) {
                    foreach ($approvedActions as $index => $action) {
                        $rolePart = ($action['role'] ?? '') !== '' ? ' (' . $action['role'] . ')' : '';
                        $timePart = ($action['at'] ?? '') !== '' ? ' - ' . $action['at'] : '';
                        $tooltipLines[] = 'Bước ' . ($index + 1) . ': ' . ($action['name'] ?? 'Không rõ') . $rolePart . $timePart;
                    }
                } else {
                    $tooltipLines[] = 'Chưa có người duyệt nào.';
                }

                if (is_array($rejectedAction)) {
                    $rolePart = ($rejectedAction['role'] ?? '') !== '' ? ' (' . $rejectedAction['role'] . ')' : '';
                    $timePart = ($rejectedAction['at'] ?? '') !== '' ? ' - ' . $rejectedAction['at'] : '';
                    $tooltipLines[] = 'Từ chối bởi: ' . ($rejectedAction['name'] ?? 'Không rõ') . $rolePart . $timePart;
                }

                $item->ApprovalProgress = $progressLabel;
                $item->ApprovalCount = $approved;
                $item->RequiredApprovals = $requiredApprovals;
                $item->ApprovalTooltip = implode("\n", $tooltipLines);
                $item->RemainingLeaveDays = $remainingDays;
                $item->RemainingLeaveLabel = $isDeductible ? ($remainingDays . ' ngày') : 'Không áp dụng';
                $item->RemainingLeaveTooltip = 'Năm ' . $leaveYear . ': Đã dùng ' . (int) $balance['used'] . '/' . (int) $balance['entitled'] . ' ngày phép năm';

                return $item;
            })->values();

            $items = new \Illuminate\Pagination\LengthAwarePaginator(
                $transformedItems,
                $items->total(),
                $items->perPage(),
                $items->currentPage(),
                [
                    'path' => $items->path(),
                    'pageName' => 'page',
                    'query' => $request->query(),
                ]
            );
            $items->appends($request->query());
        }

        return view($this->resolveModuleView($module, 'index'), [
            'moduleKey' => $module,
            'routeKey' => $meta['module']['legacy_name'] ?? $module,
            'moduleConfig' => $meta['module'],
            'resourceConfig' => $meta['resource'],
            'items' => $items,
            'filters' => $request->only(['q']),
            'isSelfView' => isset($filters['ma_nv']),
            'currentLeaveBalanceSummary' => $currentLeaveBalanceSummary,
        ]);
    }

    public function create(string $module): View
    {
        $meta = $this->modules->describe($module);
        abort_if($meta['resource']['read_only'] ?? false, 404);

        // Auto-fill MaNV for NhanVien role
        $defaultRecord = [];
        if (!empty($meta['module']['auto_fill_ma_nv'])) {
            $account = (array) session('taikhoan', []);
            $role = strtolower(trim((string) ($account['VaiTro'] ?? '')));
            if ($role === 'nhanvien' && ($account['MaNV'] ?? 0) > 0) {
                $defaultRecord['MaNV'] = (int) $account['MaNV'];
            }
        }

        if ($module === 'leave-balances') {
            $defaultRecord['Nam'] = (int) date('Y');
            $defaultRecord['EntitledDays'] = max(0, (int) config('approval_workflows.leave_requests.leave_balance.default_entitled_days', 12));
            $defaultRecord['UsedDays'] = 0;
        }

        return view($this->resolveModuleView($module, 'form'), [
            'mode' => 'create',
            'moduleKey' => $module,
            'routeKey' => $meta['module']['legacy_name'] ?? $module,
            'moduleConfig' => $meta['module'],
            'resourceConfig' => $meta['resource'],
            'record' => $defaultRecord,
            'recordId' => null,
            'fieldLookups' => $this->loadFieldLookups($meta['module']),
        ]);
    }

    public function store(Request $request, string $module): RedirectResponse
    {
        $meta = $this->modules->describe($module);
        abort_if($meta['resource']['read_only'] ?? false, 404);
        $routeKey = $meta['module']['legacy_name'] ?? $module;

        if ($module === 'contracts') {
            $dateError = $this->validateContractDates($request);
            if ($dateError) {
                return back()->withInput()->withErrors(['NgayKetThuc' => $dateError]);
            }
        }

        if ($module === 'leave-balances') {
            $balanceErrors = $this->validateLeaveBalancePayload($request, null);
            if ($balanceErrors !== []) {
                return back()->withInput()->withErrors($balanceErrors);
            }
        }

        try {
            $payload = $this->buildPayload($request, $meta['resource'], $meta['module'], false);

            // For accounts: capture plain-text password before it gets hashed in the gateway
            $plainPassword = null;
            if ($module === 'accounts' && isset($payload['MatKhau']) && $payload['MatKhau'] !== '') {
                $plainPassword = $payload['MatKhau'];
            }

            $recordId = $this->modules->create($module, $payload);

            if ($module === 'employee-profiles') {
                $this->syncEmployeeProfileToRelatedModules($payload);
            }

            $successMsg = 'Đã tạo bản ghi thành công.';
            if ($plainPassword !== null) {
                $username = $payload['TenDangNhap'] ?? ('#' . $recordId);
                $successMsg = "Đã tạo tài khoản <strong>{$username}</strong> thành công. Mật khẩu ban đầu: <strong>{$plainPassword}</strong> — vui lòng ghi lại và đưa cho nhân viên.";
            }

            return redirect()->route("{$routeKey}.edit", ['record' => $recordId])
                ->with('success', $successMsg);
        } catch (QueryException|LogicException $exception) {
            return back()->withInput()->withErrors(['form' => 'Không thể tạo bản ghi: ' . $exception->getMessage()]);
        }
    }

    public function edit(string $module, string $record): View
    {
        $meta = $this->resolveMetaWithFallback($module);
        abort_if($meta['resource']['read_only'] ?? false, 404);
        $item = $this->modules->find($module, $record);

        if ($item === null) {
            $item = $this->findRecordDirectly($meta, $record);
        }

        // Fallback for employee profiles: load directly from DB when API lookup fails.
        if ($item === null && $module === 'employee-profiles' && ctype_digit($record)) {
            $conn = (string) config('service_registry.services.hr.connection', config('database.default'));

            $byProfileId = DB::connection($conn)
                ->table('hosonhanvien')
                ->where('MaHoSo', (int) $record)
                ->first();

            if ($byProfileId !== null) {
                $item = (array) $byProfileId;
                $item['__resource_id'] = (string) ($item['MaHoSo'] ?? $record);
            }
        }

        // Backward-compatible fallback: some legacy links pass MaNV instead of MaHoSo.
        if ($item === null && $module === 'employee-profiles' && ctype_digit($record)) {
            $conn = (string) config('service_registry.services.hr.connection', config('database.default'));

            $byEmployeeId = DB::connection($conn)
                ->table('hosonhanvien')
                ->where('MaNV', (int) $record)
                ->first();

            if ($byEmployeeId !== null) {
                $item = (array) $byEmployeeId;
                $record = (string) ($item['MaHoSo'] ?? $record);
                $item['__resource_id'] = $record;
            }
        }

        // Backward-compatible fallback: support legacy identifiers (MaNV[,Nam]) for leave balances.
        if ($item === null && $module === 'leave-balances') {
            $conn = (string) config('service_registry.services.hr.connection', config('database.default'));
            $recordId = null;
            $employeeId = null;
            $year = null;

            if (ctype_digit($record)) {
                $recordId = (int) $record;
                $employeeId = (int) $record;
            } elseif (str_contains($record, ',')) {
                $parts = explode(',', $record, 2);
                if (isset($parts[0]) && ctype_digit((string) $parts[0])) {
                    $employeeId = (int) $parts[0];
                }
                if (isset($parts[1]) && ctype_digit((string) $parts[1])) {
                    $year = (int) $parts[1];
                }
            }

            if ($recordId !== null) {
                $leaveBalance = DB::connection($conn)
                    ->table('leave_balances')
                    ->where('id', $recordId)
                    ->first();

                if ($leaveBalance !== null) {
                    $item = (array) $leaveBalance;
                    $record = (string) ($item['id'] ?? $recordId);
                    $item['__resource_id'] = $record;
                }
            }

            if ($item === null && $employeeId !== null) {
                $query = DB::connection($conn)
                    ->table('leave_balances')
                    ->where('MaNV', $employeeId);

                if ($year !== null) {
                    $query->where('Nam', $year);
                }

                $leaveBalance = $query
                    ->orderByDesc('Nam')
                    ->orderByDesc('id')
                    ->first();

                if ($leaveBalance !== null) {
                    $item = (array) $leaveBalance;
                    $record = (string) ($item['id'] ?? $record);
                    $item['__resource_id'] = $record;
                }
            }
        }

        abort_if($item === null, 404);

        return view($this->resolveModuleView($module, 'form'), [
            'mode' => 'edit',
            'moduleKey' => $module,
            'routeKey' => $meta['module']['legacy_name'] ?? $module,
            'moduleConfig' => $meta['module'],
            'resourceConfig' => $meta['resource'],
            'record' => $item,
            'recordId' => $record,
            'fieldLookups' => $this->loadFieldLookups($meta['module']),
        ]);
    }

    private function loadFieldLookups(array $moduleConfig): array
    {
        $result = [];
        foreach ($moduleConfig['field_lookups'] ?? [] as $field => $def) {
            $conn = (string) config("service_registry.services.{$def['service']}.connection", config('database.default'));
            try {
                $cacheKey = sprintf(
                    'field_lookup_%s_%s_%s_%s',
                    $conn,
                    (string) $def['table'],
                    (string) $def['value_col'],
                    (string) $def['label_col']
                );
                $rows = Cache::remember($cacheKey, 300, function () use ($conn, $def) {
                    return DB::connection($conn)
                        ->table($def['table'])
                        ->orderBy($def['label_col'])
                        ->get([$def['value_col'], $def['label_col']]);
                });
                $result[$field] = $rows->map(fn ($r) => [
                    'value' => $r->{$def['value_col']},
                    'label' => $r->{$def['label_col']},
                ])->all();
            } catch (\Throwable) {
                $result[$field] = [];
            }
        }
        return $result;
    }

    private function findRecordDirectly(array $meta, string $record): ?array
    {
        $service = (string) data_get($meta, 'module.service', '');
        $resource = (string) data_get($meta, 'module.resource', '');
        $resourceDef = (array) config("service_registry.services.{$service}.resources.{$resource}", []);

        $table = (string) ($resourceDef['table'] ?? '');
        $primaryKey = $resourceDef['primary_key'] ?? null;
        $conn = (string) config("service_registry.services.{$service}.connection", config('database.default'));

        if ($table === '' || !is_string($primaryKey) || $primaryKey === '') {
            return null;
        }

        $row = DB::connection($conn)
            ->table($table)
            ->where($primaryKey, urldecode($record))
            ->first();

        if ($row === null) {
            return null;
        }

        $item = (array) $row;
        $item['__resource_id'] = (string) ($item[$primaryKey] ?? $record);

        return $item;
    }

    private function resolveMetaWithFallback(string $module): array
    {
        try {
            return $this->modules->describe($module);
        } catch (\Throwable) {
            $moduleConfig = $this->modules->module($module);
            $resourceConfig = $this->gateway->describeResource(
                (string) $moduleConfig['service'],
                (string) $moduleConfig['resource']
            );
            $resourceConfig['read_only'] = (bool) (($moduleConfig['read_only'] ?? false) || ($resourceConfig['read_only'] ?? false));

            return [
                'module' => $moduleConfig,
                'resource' => $resourceConfig,
            ];
        }
    }

    public function update(Request $request, string $module, string $record): RedirectResponse
    {
        $meta = $this->resolveMetaWithFallback($module);
        abort_if($meta['resource']['read_only'] ?? false, 404);
        $routeKey = $meta['module']['legacy_name'] ?? $module;

        if ($module === 'contracts') {
            $dateError = $this->validateContractDates($request);
            if ($dateError) {
                return back()->withInput()->withErrors(['NgayKetThuc' => $dateError]);
            }
        }

        if ($module === 'leave-balances') {
            $balanceErrors = $this->validateLeaveBalancePayload($request, $record);
            if ($balanceErrors !== []) {
                return back()->withInput()->withErrors($balanceErrors);
            }
        }

        try {
            $payload = $this->buildPayload($request, $meta['resource'], $meta['module'], true);

            if ($module === 'assignments' && ctype_digit($record)) {
                $updated = $this->updateRecordDirectly($meta, $record, $payload);
                if ($updated) {
                    return redirect()->route("{$routeKey}.edit", ['record' => $record])
                        ->with('success', 'Đã cập nhật bản ghi thành công.');
                }
            }

            $this->modules->update($module, $record, $payload);

            if ($module === 'employee-profiles') {
                $current = $this->modules->find($module, $record) ?? [];
                $this->syncEmployeeProfileToRelatedModules(array_merge($current, $payload));
            }

            return redirect()->route("{$routeKey}.edit", ['record' => $record])
                ->with('success', 'Đã cập nhật bản ghi thành công.');
        } catch (ModelNotFoundException $exception) {
            if ($module === 'assignments' && ctype_digit($record) && isset($payload) && is_array($payload)) {
                $updated = $this->updateRecordDirectly($meta, $record, $payload);
                if ($updated) {
                    return redirect()->route("{$routeKey}.edit", ['record' => $record])
                        ->with('success', 'Đã cập nhật bản ghi thành công.');
                }
            }

            return back()->withInput()->withErrors(['form' => 'Không tìm thấy bản ghi để cập nhật.']);
        } catch (QueryException|LogicException $exception) {
            return back()->withInput()->withErrors(['form' => 'Không thể cập nhật bản ghi: ' . $exception->getMessage()]);
        }
    }

    private function updateRecordDirectly(array $meta, string $record, array $payload): bool
    {
        $service = (string) data_get($meta, 'module.service', '');
        $resource = (string) data_get($meta, 'module.resource', '');
        $resourceDef = (array) config("service_registry.services.{$service}.resources.{$resource}", []);

        $table = (string) ($resourceDef['table'] ?? '');
        $primaryKey = $resourceDef['primary_key'] ?? null;
        $conn = (string) config("service_registry.services.{$service}.connection", config('database.default'));

        if ($table === '' || !is_string($primaryKey) || $primaryKey === '') {
            return false;
        }

        $affected = DB::connection($conn)
            ->table($table)
            ->where($primaryKey, urldecode($record))
            ->update($payload);

        if ($affected > 0) {
            return true;
        }

        $exists = DB::connection($conn)
            ->table($table)
            ->where($primaryKey, urldecode($record))
            ->exists();

        return $exists;
    }

    public function destroy(string $module, string $record): RedirectResponse
    {
        $meta = $this->modules->describe($module);
        abort_if($meta['resource']['read_only'] ?? false, 404);
        $routeKey = $meta['module']['legacy_name'] ?? $module;

        try {
            $this->modules->delete($module, $record);

            return redirect()->route("{$routeKey}.index")
                ->with('success', 'Đã xóa bản ghi thành công.');
        } catch (QueryException|LogicException $exception) {
            return back()->withErrors(['form' => 'Không thể xóa bản ghi: ' . $exception->getMessage()]);
        }
    }

    public function destroyLegacy(string $module, string $record): RedirectResponse
    {
        return $this->destroy($module, $record);
    }

    public function assignmentHistory(Request $request): RedirectResponse
    {
        $employeeCode = trim((string) $request->query('manv', $request->query('MaNV', '')));

        return redirect()->route('phancong.index', $employeeCode !== '' ? ['q' => $employeeCode] : []);
    }

    public function deactivateInsurance(string $module, string $record): RedirectResponse
    {
        abort_unless($module === 'insurances', 404);

        $this->client->post("biz/insurances/{$record}/deactivate");

        return redirect()->route('baohiem.index')->with('success', 'Đã ngừng bảo hiểm thành công.');
    }

    public function approveLeaveRequest(string $module, string $record): RedirectResponse
    {
        abort_unless($module === 'leave-requests', 404);

        $result = $this->client->post("biz/leave-requests/{$record}/approve");
        $ok = (bool) ($result['ok'] ?? false);
        $message = (string) ($result['message'] ?? 'Không thể duyệt đơn nghỉ phép.');

        return redirect()->route('nghiphep.index')->with(
            $ok ? 'success' : 'error',
            $ok ? $message : $message
        );
    }

    public function rejectLeaveRequest(string $module, string $record): RedirectResponse
    {
        abort_unless($module === 'leave-requests', 404);

        $result = $this->client->post("biz/leave-requests/{$record}/reject");
        $ok = (bool) ($result['ok'] ?? false);
        $message = (string) ($result['message'] ?? 'Không thể từ chối đơn nghỉ phép.');

        return redirect()->route('nghiphep.index')->with($ok ? 'success' : 'error', $message);
    }

    public function seedAnnualLeaveBalances(Request $request, string $module): RedirectResponse
    {
        abort_unless($module === 'leave-balances', 404);

        $year = (int) ($request->input('year', date('Y')));

        try {
            Artisan::call('leave:seed-annual-balances', [
                '--year' => $year,
            ]);

            $output = trim((string) Artisan::output());
            $message = $output !== ''
                ? $output
                : ('Đã khởi tạo quỹ phép năm ' . $year . '.');

            return redirect()->route('quyphep.index')->with('success', $message);
        } catch (\Throwable $e) {
            return redirect()->route('quyphep.index')
                ->with('error', 'Không thể khởi tạo quỹ phép năm: ' . $e->getMessage());
        }
    }

    public function exportExcel(Request $request, string $module): StreamedResponse
    {
        $meta = $this->modules->describe($module);
        abort_if((bool) ($meta['module']['disable_export'] ?? false), 404);

        $export = $this->modules->exportRows($module, $request->only(['q']));

        return response()->streamDownload(function () use ($export) {
            echo "\xEF\xBB\xBF";
            echo '<table border="1"><tr>';
            foreach ($export['columns'] as $column) {
                echo '<th>' . e((string) $column) . '</th>';
            }
            echo '</tr>';

            foreach ($export['rows'] as $row) {
                echo '<tr>';
                foreach ($export['columns'] as $column) {
                    echo '<td>' . e((string) data_get($row, $column, '')) . '</td>';
                }
                echo '</tr>';
            }

            echo '</table>';
        }, 'du-lieu-' . Str::slug((string) ($export['meta']['module']['title'] ?? $module), '-') . '-' . now()->format('Ymd-His') . '.xls', [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function validateContractDates(Request $request): ?string
    {
        $start = $request->input('NgayBatDau');
        $end = $request->input('NgayKetThuc');

        if (empty($start) || !strtotime((string) $start)) {
            return 'Ngày bắt đầu không hợp lệ.';
        }

        if (!empty($end)) {
            if (!strtotime((string) $end)) {
                return 'Ngày kết thúc không hợp lệ.';
            }
            if (strtotime((string) $end) < strtotime((string) $start)) {
                return 'Ngày kết thúc phải từ ngày bắt đầu trở đi.';
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function validateLeaveBalancePayload(Request $request, ?string $recordId): array
    {
        $errors = [];

        $employeeId = (int) $request->input('MaNV', 0);
        $year = (int) $request->input('Nam', 0);
        $entitled = (int) $request->input('EntitledDays', 0);
        $used = (int) $request->input('UsedDays', 0);

        if ($employeeId <= 0) {
            $errors['MaNV'] = 'Vui lòng chọn nhân viên.';
        }

        if ($year < 2000 || $year > 2100) {
            $errors['Nam'] = 'Năm không hợp lệ.';
        }

        if ($entitled < 0) {
            $errors['EntitledDays'] = 'Tổng ngày phép phải lớn hơn hoặc bằng 0.';
        }

        if ($used < 0) {
            $errors['UsedDays'] = 'Số ngày đã sử dụng phải lớn hơn hoặc bằng 0.';
        }

        if ($entitled < $used) {
            $errors['EntitledDays'] = 'Tổng ngày phép không được nhỏ hơn số ngày đã sử dụng.';
        }

        if ($errors !== []) {
            return $errors;
        }

        $connection = (string) config('service_registry.services.hr.connection', config('database.default'));
        $duplicateQuery = DB::connection($connection)
            ->table('leave_balances')
            ->where('MaNV', $employeeId)
            ->where('Nam', $year);

        if ($recordId !== null && ctype_digit($recordId)) {
            $duplicateQuery->where('id', '!=', (int) $recordId);
        }

        if ($duplicateQuery->exists()) {
            $errors['MaNV'] = 'Nhân viên này đã có quỹ phép cho năm đã chọn.';
        }

        return $errors;
    }

    private function buildPayload(Request $request, array $resourceConfig, array $moduleConfig, bool $isUpdate): array
    {
        $payload = [];
        $primaryKeys = is_array($resourceConfig['primary_key'] ?? null)
            ? $resourceConfig['primary_key']
            : [(string) ($resourceConfig['primary_key'] ?? 'id')];
        $fileFields = (array) ($moduleConfig['file_fields'] ?? []);

        // For NhanVien role with auto_fill_ma_nv, force MaNV from session on create
        $forceMaNV = null;
        if (!$isUpdate && !empty($moduleConfig['auto_fill_ma_nv'])) {
            $account = (array) session('taikhoan', []);
            $role = strtolower(trim((string) ($account['VaiTro'] ?? '')));
            if ($role === 'nhanvien' && ($account['MaNV'] ?? 0) > 0) {
                $forceMaNV = (int) $account['MaNV'];
            }
        }

        foreach ($resourceConfig['columns'] as $column) {
            $field = (string) ($column['field'] ?? '');
            $isAutoIncrement = str_contains((string) ($column['extra'] ?? ''), 'auto_increment');

            if ($field === '' || ($isUpdate && in_array($field, $primaryKeys, true)) || $isAutoIncrement) {
                continue;
            }

            // Handle file upload fields
            if (in_array($field, $fileFields, true)) {
                if ($request->hasFile($field) && $request->file($field)->isValid()) {
                    $file = $request->file($field);
                    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                    $uploadDir = base_path('../uploads/photos');
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $file->move($uploadDir, $filename);
                    $payload[$field] = $filename;
                }
                // If no new file uploaded, skip (keep existing value)
                continue;
            }

            if (!$request->has($field)) {
                continue;
            }

            $value = $request->input($field);
            $payload[$field] = $value === '' ? null : $value;
        }

        // Enforce MaNV from session (cannot be tampered by form input)
        if ($forceMaNV !== null) {
            $payload['MaNV'] = $forceMaNV;
        }

        return $payload;
    }

    private function syncEmployeeProfileToRelatedModules(array $profileData): void
    {
        $maNV = (int) ($profileData['MaNV'] ?? 0);
        if ($maNV <= 0) {
            return;
        }

        $connection = (string) config('service_registry.services.hr.connection', config('database.default'));

        $employeeUpdates = [];
        $this->mapFirstAvailableField($employeeUpdates, $profileData, 'HoTen', ['HoTen', 'TenNhanVien']);
        $this->mapFirstAvailableField($employeeUpdates, $profileData, 'GioiTinh', ['GioiTinh']);
        $this->mapFirstAvailableField($employeeUpdates, $profileData, 'NgaySinh', ['NgaySinh']);
        $this->mapFirstAvailableField($employeeUpdates, $profileData, 'Email', ['Email']);
        $this->mapFirstAvailableField($employeeUpdates, $profileData, 'DienThoai', ['DienThoai', 'SoDienThoai', 'SDT']);
        $this->mapFirstAvailableField($employeeUpdates, $profileData, 'MaBac', ['MaBac']);
        $this->mapFirstAvailableField($employeeUpdates, $profileData, 'TrangThai', ['TrangThai']);

        if ($employeeUpdates !== [] && Schema::connection($connection)->hasTable('nhanvien')) {
            foreach (array_keys($employeeUpdates) as $column) {
                if (!Schema::connection($connection)->hasColumn('nhanvien', $column)) {
                    unset($employeeUpdates[$column]);
                }
            }

            if ($employeeUpdates !== []) {
                DB::connection($connection)
                    ->table('nhanvien')
                    ->where('MaNV', $maNV)
                    ->update($employeeUpdates);
            }
        }

        $maPB = isset($profileData['MaPB']) && $profileData['MaPB'] !== '' ? (int) $profileData['MaPB'] : null;
        $maCV = isset($profileData['MaCV']) && $profileData['MaCV'] !== '' ? (int) $profileData['MaCV'] : null;

        if ($maPB !== null && $maCV !== null && Schema::connection($connection)->hasTable('phancong')) {
            $latest = DB::connection($connection)
                ->table('phancong')
                ->where('MaNV', $maNV)
                ->orderByDesc('MaQT')
                ->first();

            $isChanged = !$latest
                || (int) ($latest->MaPB ?? 0) !== $maPB
                || (int) ($latest->MaCV ?? 0) !== $maCV;

            if ($isChanged) {
                DB::connection($connection)
                    ->table('phancong')
                    ->insert([
                        'MaNV' => $maNV,
                        'MaPB' => $maPB,
                        'MaCV' => $maCV,
                        'NgayBatDau' => $profileData['NgayVaoLam'] ?? now()->toDateString(),
                        'NgayKetThuc' => null,
                        'LyDoThayDoi' => 'Cap nhat tu nhap nhanh ho so',
                    ]);
            }
        }
    }

    private function mapFirstAvailableField(array &$target, array $source, string $targetKey, array $candidateKeys): void
    {
        foreach ($candidateKeys as $key) {
            if (array_key_exists($key, $source)) {
                $target[$targetKey] = $source[$key] === '' ? null : $source[$key];
                return;
            }
        }
    }

    private function resolveModuleView(string $module, string $page): string
    {
        $meta = config("laravel_resource_modules.{$module}", []);
        $legacyFolder = (string) ($meta['legacy_name'] ?? $meta['legacy_prefix'] ?? '');

        $candidates = [
            $legacyFolder !== '' ? $legacyFolder . '.' . $page : null,
            $legacyFolder !== '' ? Str::replace('-', '_', $legacyFolder) . '.' . $page : null,
            $module . '.' . $page,
            Str::replace('-', '_', $module) . '.' . $page,
            'resource_modules.' . $page,
        ];

        foreach (array_unique(array_filter($candidates)) as $candidate) {
            if (ViewFacade::exists($candidate)) {
                return $candidate;
            }
        }

        return 'resource_modules.' . $page;
    }
}