<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\Admin\AccountAdminController;
use App\Http\Controllers\Admin\AccountSettingsController;
use App\Http\Controllers\Admin\ContractAdminController;
use App\Http\Controllers\Admin\EmployeeProfileAdminController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\DepartmentController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\PayrollController;
use App\Http\Controllers\Hr\RecruitmentController;
use App\Http\Controllers\Hr\TrainingController;
use App\Http\Controllers\Report\AuditLogExportController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\System\ChatbotMonitorController;
use App\Http\Controllers\System\DashboardController;
use App\Http\Controllers\System\ResourceModuleController;
use App\Http\Controllers\System\SearchController;
use App\Http\Controllers\System\ServiceConsoleController;
use App\Http\Controllers\System\SystemHealthController;
use Illuminate\Support\Facades\Route;

// Bảng bậc lương gom nhóm — phải đăng ký đầu tiên để ưu tiên hơn route generic
Route::get('/bacluong', function () {
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

    return view('bacluong.index', compact('rows', 'groupSizes', 'luongCoSo'));
})->middleware(['session.auth', 'permission:xem_bacluong'])
  ->name('bacluong.index');

/*
|--------------------------------------------------------------------------
    return app(ResourceModuleController::class)->edit('insurances', $record);
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect()->route('login.form');
});

Route::get('/uploads/{path}', function (string $path) {
    $uploadsRoot = realpath(base_path('../uploads'));
    $resolvedPath = realpath(base_path('../uploads/' . $path));

    if ($uploadsRoot === false || $resolvedPath === false) {
        abort(404);
    }

    $normalizedRoot = rtrim(str_replace('\\', '/', $uploadsRoot), '/') . '/';
    $normalizedResolved = str_replace('\\', '/', $resolvedPath);

    // Prevent path traversal by ensuring the resolved file stays inside uploads root.
    if (strpos($normalizedResolved, $normalizedRoot) !== 0 || !is_file($resolvedPath)) {
        abort(404);
    }

    return response()->file($resolvedPath);
})->name('legacy.upload')->where('path', '.+');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login.form');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::get('/forgot-password', [PasswordRecoveryController::class, 'showForgot'])->name('password.forgot');
Route::post('/forgot-password', [PasswordRecoveryController::class, 'handleForgot'])->middleware('throttle:5,1')->name('password.forgot.submit');
Route::get('/reset-password', [PasswordRecoveryController::class, 'showReset'])->name('password.reset');
Route::post('/reset-password', [PasswordRecoveryController::class, 'handleReset'])->middleware('throttle:5,1')->name('password.reset.submit');
Route::get('/force-password-change', [PasswordRecoveryController::class, 'showForcedChange'])->middleware('session.auth')->name('password.force');
Route::post('/force-password-change', [PasswordRecoveryController::class, 'handleForcedChange'])->middleware('session.auth')->name('password.force.submit');
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('session.auth')
    ->name('logout');
Route::get('/logout', [AuthController::class, 'logoutBridge'])
    ->middleware('session.auth')
    ->name('logout.get');
Route::get('/logout-bridge', [AuthController::class, 'logoutBridge'])->middleware('session.auth')->name('logout.bridge');

Route::get('/settings', [AccountSettingsController::class, 'show'])
    ->middleware('session.auth')
    ->name('settings.show');
Route::post('/settings/username', [AccountSettingsController::class, 'updateUsername'])
    ->middleware('session.auth')
    ->name('settings.username');
Route::post('/settings/password', [AccountSettingsController::class, 'updatePassword'])
    ->middleware('session.auth')
    ->name('settings.password');
Route::post('/settings/refresh-session', [AccountSettingsController::class, 'refreshSession'])
    ->middleware('session.auth')
    ->name('settings.refresh-session');
Route::post('/settings/revoke-other-sessions', [AccountSettingsController::class, 'revokeOtherSessions'])
    ->middleware('session.auth')
    ->name('settings.revoke-other-sessions');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('session.auth')
    ->name('dashboard');
Route::post('/dashboard/notifications/read', [DashboardController::class, 'markNotificationsRead'])
    ->middleware('session.auth')
    ->name('dashboard.notifications.read');
Route::get('/dashboard/charts', [DashboardController::class, 'chartData'])
    ->middleware('session.auth')
    ->name('dashboard.charts');

Route::get('/employees', [EmployeeController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_nhanvien'])
    ->name('employees.index');

Route::get('/employees/salary-grades-by-band', [EmployeeController::class, 'salaryGradesByBand'])
    ->middleware(['session.auth', 'permission:xem_nhanvien'])
    ->name('employees.salary-grades-by-band');

Route::get('/employees/create', [EmployeeController::class, 'create'])
    ->middleware(['session.auth', 'permission:them_nhanvien'])
    ->name('employees.create');

Route::post('/employees', [EmployeeController::class, 'store'])
    ->middleware(['session.auth', 'permission:them_nhanvien'])
    ->name('employees.store');

Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])
    ->middleware(['session.auth', 'permission:sua_nhanvien'])
    ->name('employees.edit');

Route::put('/employees/{employee}', [EmployeeController::class, 'update'])
    ->middleware(['session.auth', 'permission:sua_nhanvien'])
    ->name('employees.update');

Route::post('/employees/{employee}', [EmployeeController::class, 'update'])
    ->middleware(['session.auth', 'permission:sua_nhanvien'])
    ->name('employees.update.legacy');

Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])
    ->middleware(['session.auth', 'permission:xoa_nhanvien'])
    ->name('employees.destroy');

Route::get('/departments', [DepartmentController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_phongban'])
    ->name('departments.index');

Route::get('/attendance', [AttendanceController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_chamcong'])
    ->name('attendance.index');

Route::get('/attendance/matrix', [AttendanceController::class, 'matrix'])
    ->middleware(['session.auth', 'permission:xem_chamcong'])
    ->name('attendance.matrix');

Route::post('/attendance/matrix/cell', [AttendanceController::class, 'updateCell'])
    ->middleware(['session.auth', 'permission:them_chamcong'])
    ->name('attendance.matrix.cell');

Route::get('/attendance/worked-days', [AttendanceController::class, 'workedDays'])
    ->middleware(['session.auth', 'permission:xem_chamcong'])
    ->name('attendance.worked-days');

Route::get('/attendance/export-excel', [AttendanceController::class, 'exportExcel'])
    ->middleware(['session.auth', 'permission:xuat_bang_cham_cong'])
    ->name('attendance.export-excel');

Route::get('/attendance/create', [AttendanceController::class, 'create'])
    ->middleware(['session.auth', 'permission:them_chamcong'])
    ->name('attendance.create');

Route::post('/attendance', [AttendanceController::class, 'store'])
    ->middleware(['session.auth', 'permission:them_chamcong'])
    ->name('attendance.store');

Route::get('/attendance/{attendance}/edit', [AttendanceController::class, 'edit'])
    ->middleware(['session.auth', 'permission:sua_chamcong'])
    ->name('attendance.edit');

Route::put('/attendance/{attendance}', [AttendanceController::class, 'update'])
    ->middleware(['session.auth', 'permission:sua_chamcong'])
    ->name('attendance.update');

Route::post('/attendance/{attendance}', [AttendanceController::class, 'update'])
    ->middleware(['session.auth', 'permission:sua_chamcong'])
    ->name('attendance.update.legacy');

Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy'])
    ->middleware(['session.auth', 'permission:xoa_chamcong'])
    ->name('attendance.destroy');

Route::get('/payroll', [PayrollController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_luong'])
    ->name('payroll.index');

Route::post('/payroll/run-monthly', [PayrollController::class, 'runMonthly'])
    ->middleware(['session.auth', 'permission:tinh_luong_thang'])
    ->name('payroll.run-monthly');
Route::get('/payroll/job-status', [PayrollController::class, 'jobStatus'])
    ->middleware(['session.auth', 'permission:xem_luong'])
    ->name('payroll.job-status');

Route::get('/payroll/salary-components', [PayrollController::class, 'salaryComponents'])
    ->middleware(['session.auth', 'permission:tinh_luong_thang'])
    ->name('payroll.salary-components');

Route::get('/payroll/create', [PayrollController::class, 'create'])
    ->middleware(['session.auth', 'permission:tinh_luong_thang'])
    ->name('payroll.create');

Route::post('/payroll', [PayrollController::class, 'store'])
    ->middleware(['session.auth', 'permission:tinh_luong_thang'])
    ->name('payroll.store');

Route::get('/payroll/{payroll}/edit', [PayrollController::class, 'edit'])
    ->middleware(['session.auth', 'permission:mo_chot_luong'])
    ->name('payroll.edit');

Route::get('/payroll/{payroll}', [PayrollController::class, 'show'])
    ->middleware(['session.auth', 'permission:xem_luong'])
    ->name('payroll.show');

Route::put('/payroll/{payroll}', [PayrollController::class, 'update'])
    ->middleware(['session.auth', 'permission:mo_chot_luong'])
    ->name('payroll.update');

Route::post('/payroll/{payroll}', [PayrollController::class, 'update'])
    ->middleware(['session.auth', 'permission:mo_chot_luong'])
    ->name('payroll.update.legacy');

Route::get('/recruitment', [RecruitmentController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_dot_tuyen'])
    ->name('recruitment.index');

Route::get('/recruitment/candidates', [RecruitmentController::class, 'candidates'])
    ->middleware(['session.auth', 'permission:xem_ung_vien'])
    ->name('recruitment.candidates.index');

Route::get('/recruitment/candidates/create', [RecruitmentController::class, 'createCandidate'])
    ->middleware(['session.auth', 'permission:them_ung_vien'])
    ->name('recruitment.candidates.create');

Route::post('/recruitment/candidates', [RecruitmentController::class, 'storeCandidate'])
    ->middleware(['session.auth', 'permission:them_ung_vien'])
    ->name('recruitment.candidates.store');

Route::get('/recruitment/candidates/{candidate}/apply', [RecruitmentController::class, 'applyCandidate'])
    ->middleware(['session.auth', 'permission:them_ho_so'])
    ->name('recruitment.candidates.apply');

Route::post('/recruitment/candidates/{candidate}/apply', [RecruitmentController::class, 'attachCandidate'])
    ->middleware(['session.auth', 'permission:them_ho_so'])
    ->name('recruitment.candidates.attach');

Route::get('/recruitment/{recruitment}/applications', [RecruitmentController::class, 'applications'])
    ->middleware(['session.auth', 'permission:xem_ho_so'])
    ->name('recruitment.applications.index');

Route::post('/recruitment/applications/{application}/status', [RecruitmentController::class, 'updateApplicationStatus'])
    ->middleware(['session.auth', 'permission:capnhat_trangthai'])
    ->name('recruitment.applications.status');

Route::get('/recruitment/applications/{application}/interviews', [RecruitmentController::class, 'interviews'])
    ->middleware(['session.auth', 'permission:xem_lich_phong_van'])
    ->name('recruitment.applications.interviews');

Route::post('/recruitment/applications/{application}/interviews', [RecruitmentController::class, 'storeInterview'])
    ->middleware(['session.auth', 'permission:them_lich_phong_van'])
    ->name('recruitment.applications.interviews.store');

Route::post('/recruitment/applications/{application}/reviews', [RecruitmentController::class, 'storeReview'])
    ->middleware(['session.auth', 'permission:them_danh_gia'])
    ->name('recruitment.applications.reviews.store');

Route::post('/recruitment/applications/kanban-status', [RecruitmentController::class, 'updateKanban'])
    ->middleware(['session.auth', 'permission:capnhat_trangthai'])
    ->name('recruitment.applications.kanban-status');

Route::get('/recruitment/create', [RecruitmentController::class, 'create'])
    ->middleware(['session.auth', 'permission:them_dot_tuyen'])
    ->name('recruitment.create');

Route::post('/recruitment', [RecruitmentController::class, 'store'])
    ->middleware(['session.auth', 'permission:them_dot_tuyen'])
    ->name('recruitment.store');

Route::get('/recruitment/{recruitment}/edit', [RecruitmentController::class, 'edit'])
    ->middleware(['session.auth', 'permission:them_dot_tuyen'])
    ->name('recruitment.edit');

Route::put('/recruitment/{recruitment}', [RecruitmentController::class, 'update'])
    ->middleware(['session.auth', 'permission:them_dot_tuyen'])
    ->name('recruitment.update');

Route::post('/recruitment/{recruitment}', [RecruitmentController::class, 'update'])
    ->middleware(['session.auth', 'permission:them_dot_tuyen'])
    ->name('recruitment.update.legacy');

Route::delete('/recruitment/{recruitment}', [RecruitmentController::class, 'destroy'])
    ->middleware(['session.auth', 'permission:xoa_dot_tuyen'])
    ->name('recruitment.destroy');

Route::get('/training', [TrainingController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_khoa_dao_tao'])
    ->name('training.index');

Route::get('/training/create', [TrainingController::class, 'create'])
    ->middleware(['session.auth', 'permission:them_khoa_dao_tao'])
    ->name('training.create');

Route::post('/training', [TrainingController::class, 'store'])
    ->middleware(['session.auth', 'permission:them_khoa_dao_tao'])
    ->name('training.store');

Route::get('/training/{training}/edit', [TrainingController::class, 'edit'])
    ->middleware(['session.auth', 'permission:them_khoa_dao_tao'])
    ->name('training.edit');

Route::get('/training/{training}/participants', [TrainingController::class, 'participants'])
    ->middleware(['session.auth', 'permission:xem_tham_gia_dao_tao'])
    ->name('training.participants');

Route::post('/training/{training}/participants', [TrainingController::class, 'storeParticipant'])
    ->middleware(['session.auth', 'permission:them_tham_gia_dao_tao'])
    ->name('training.participants.store');

Route::post('/training/participants/{participant}/result', [TrainingController::class, 'updateParticipantResult'])
    ->middleware(['session.auth', 'permission:capnhat_ketqua_dao_tao'])
    ->name('training.participants.result');

Route::put('/training/{training}', [TrainingController::class, 'update'])
    ->middleware(['session.auth', 'permission:them_khoa_dao_tao'])
    ->name('training.update');

Route::post('/training/{training}', [TrainingController::class, 'update'])
    ->middleware(['session.auth', 'permission:them_khoa_dao_tao'])
    ->name('training.update.legacy');

Route::delete('/training/{training}', [TrainingController::class, 'destroy'])
    ->middleware(['session.auth', 'permission:xoa_khoa_dao_tao'])
    ->name('training.destroy');

Route::get('/training/{training}/delete-legacy', [TrainingController::class, 'destroy'])
    ->middleware(['session.auth', 'permission:xoa_khoa_dao_tao'])
    ->name('training.destroy.legacy');

Route::get('/reports', [ReportController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_baocao'])
    ->name('reports.index');

Route::get('/reports/export-excel', [ReportController::class, 'exportExcel'])
    ->middleware(['session.auth', 'permission:xuatex_baocao'])
    ->name('reports.export-excel');

Route::get('/reports/export-json', [ReportController::class, 'exportJson'])
    ->middleware(['session.auth', 'permission:xuatex_baocao'])
    ->name('reports.export-json');

Route::get('/reports/create', [ReportController::class, 'create'])
    ->middleware(['session.auth', 'permission:them_baocao'])
    ->name('reports.create');

Route::post('/reports', [ReportController::class, 'store'])
    ->middleware(['session.auth', 'permission:them_baocao'])
    ->name('reports.store');

Route::get('/reports/{report}/edit', [ReportController::class, 'edit'])
    ->middleware(['session.auth', 'permission:sua_baocao'])
    ->name('reports.edit');

Route::put('/reports/{report}', [ReportController::class, 'update'])
    ->middleware(['session.auth', 'permission:sua_baocao'])
    ->name('reports.update');

Route::post('/reports/{report}', [ReportController::class, 'update'])
    ->middleware(['session.auth', 'permission:sua_baocao'])
    ->name('reports.update.legacy');

Route::delete('/reports/{report}', [ReportController::class, 'destroy'])
    ->middleware(['session.auth', 'permission:xoa_baocao'])
    ->name('reports.destroy');

Route::get('/chatbot', [ChatbotMonitorController::class, 'index'])
    ->middleware(['session.auth', 'permission:su_dung_chatbot'])
    ->name('chatbot.index');

Route::get('/chatbot/{session}', [ChatbotMonitorController::class, 'show'])
    ->middleware(['session.auth', 'permission:su_dung_chatbot'])
    ->whereNumber('session')
    ->name('chatbot.show');

Route::post('/chatbot/ask', [ChatbotMonitorController::class, 'ask'])
    ->middleware(['session.auth', 'permission:su_dung_chatbot'])
    ->name('chatbot.ask');

Route::post('/chatbot/confirm-draft', [ChatbotMonitorController::class, 'confirmDraft'])
    ->middleware(['session.auth', 'permission:su_dung_chatbot'])
    ->name('chatbot.confirm-draft');

Route::get('/chatbot/brief', [ChatbotMonitorController::class, 'brief'])
    ->middleware(['session.auth', 'permission:su_dung_chatbot'])
    ->name('chatbot.brief');

Route::post('/chatbot/clear-history', [ChatbotMonitorController::class, 'clearHistory'])
    ->middleware(['session.auth', 'permission:su_dung_chatbot'])
    ->name('chatbot.clear-history');

Route::get('/search', [SearchController::class, 'index'])
    ->middleware(['session.auth'])
    ->name('search.index');

Route::get('/system-health', [SystemHealthController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_taikhoan'])
    ->name('system-health.index');
Route::post('/system-health/run-checks', [SystemHealthController::class, 'runChecks'])
    ->middleware(['session.auth', 'permission:xem_taikhoan'])
    ->name('system-health.run-checks');

Route::get('/services', [ServiceConsoleController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_phanquyen'])
    ->name('services.index');

Route::get('/permission-matrix', [RolePermissionController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_phanquyen'])
    ->name('permission-matrix.index');

Route::get('/permission-matrix/accounts/{account}', [RolePermissionController::class, 'showAccount'])
    ->middleware(['session.auth', 'permission:xem_phanquyen'])
    ->name('permission-matrix.show-account');

Route::post('/permission-matrix/{role}', [RolePermissionController::class, 'update'])
    ->middleware(['session.auth', 'permission:sua_taikhoan'])
    ->name('permission-matrix.update');

Route::post('/permission-matrix/{role}/restore-defaults', [RolePermissionController::class, 'restoreDefaults'])
    ->middleware(['session.auth', 'permission:sua_taikhoan'])
    ->name('permission-matrix.restore-defaults');

Route::get('/services/{service}/{resource}', [ServiceConsoleController::class, 'show'])
    ->middleware(['session.auth', 'permission:xem_phanquyen'])
    ->name('services.show');

Route::get('/services/{service}/{resource}/create', [ServiceConsoleController::class, 'create'])
    ->middleware(['session.auth', 'permission:xem_phanquyen'])
    ->name('services.create');

Route::post('/services/{service}/{resource}', [ServiceConsoleController::class, 'store'])
    ->middleware(['session.auth', 'permission:xem_phanquyen'])
    ->name('services.store');

Route::get('/services/{service}/{resource}/{id}/edit', [ServiceConsoleController::class, 'edit'])
    ->middleware(['session.auth', 'permission:xem_phanquyen'])
    ->name('services.edit');

Route::get('/contracts/{contract}/renew', [ContractAdminController::class, 'renewForm'])
    ->middleware(['session.auth', 'permission:giahan_hopdong'])
    ->name('contracts.renew');
Route::get('/hopdong/{contract}/renew', [ContractAdminController::class, 'renewForm'])
    ->middleware(['session.auth', 'permission:giahan_hopdong'])
    ->name('hopdong.renew');

Route::post('/contracts/{contract}/renew', [ContractAdminController::class, 'renewStore'])
    ->middleware(['session.auth', 'permission:giahan_hopdong'])
    ->name('contracts.renew.store');
Route::post('/hopdong/{contract}/renew', [ContractAdminController::class, 'renewStore'])
    ->middleware(['session.auth', 'permission:giahan_hopdong'])
    ->name('hopdong.renew.store');

Route::post('/contracts/{contract}/terminate', [ContractAdminController::class, 'terminate'])
    ->middleware(['session.auth', 'permission:chamdut_hopdong'])
    ->name('contracts.terminate');
Route::post('/hopdong/{contract}/terminate', [ContractAdminController::class, 'terminate'])
    ->middleware(['session.auth', 'permission:chamdut_hopdong'])
    ->name('hopdong.terminate');

Route::get('/contracts/{contract}/terminate-legacy', [ContractAdminController::class, 'terminate'])
    ->middleware(['session.auth', 'permission:chamdut_hopdong'])
    ->name('contracts.terminate.legacy');
Route::get('/hopdong/{contract}/terminate-legacy', [ContractAdminController::class, 'terminate'])
    ->middleware(['session.auth', 'permission:chamdut_hopdong'])
    ->name('hopdong.terminate.legacy');

Route::get('/contracts/{contract}/salary-history', [ContractAdminController::class, 'salaryHistory'])
    ->middleware(['session.auth', 'permission:xem_lich_su_luong'])
    ->name('contracts.salary-history');
Route::get('/hopdong/{contract}/salary-history', [ContractAdminController::class, 'salaryHistory'])
    ->middleware(['session.auth', 'permission:xem_lich_su_luong'])
    ->name('hopdong.salary-history');

Route::get('/contracts/{contract}/delete-legacy', [ContractAdminController::class, 'destroyLegacy'])
    ->middleware(['session.auth', 'permission:xoa_hopdong'])
    ->name('contracts.admin.destroy.legacy');
Route::get('/hopdong/{contract}/delete-legacy', [ContractAdminController::class, 'destroyLegacy'])
    ->middleware(['session.auth', 'permission:xoa_hopdong'])
    ->name('hopdong.admin.destroy.legacy');

Route::get('/employee-profiles/review-requests', [EmployeeProfileAdminController::class, 'reviewRequests'])
    ->middleware(['session.auth', 'permission:xem_nhanvien'])
    ->name('employee-profiles.review-requests');
Route::get('/hosocanhan/review-requests', [EmployeeProfileAdminController::class, 'reviewRequests'])
    ->middleware(['session.auth', 'permission:xem_nhanvien'])
    ->name('hosocanhan.review-requests');

Route::post('/employee-profiles/review-requests/{requestId}', [EmployeeProfileAdminController::class, 'resolveRequest'])
    ->middleware(['session.auth', 'permission:sua_nhanvien'])
    ->name('employee-profiles.review-requests.resolve');
Route::post('/hosocanhan/review-requests/{requestId}', [EmployeeProfileAdminController::class, 'resolveRequest'])
    ->middleware(['session.auth', 'permission:sua_nhanvien'])
    ->name('hosocanhan.review-requests.resolve');

Route::get('/employee-profiles/employee-info', [EmployeeProfileAdminController::class, 'employeeInfo'])
    ->middleware(['session.auth', 'permission:xem_nhanvien'])
    ->name('employee-profiles.employee-info');
Route::get('/hosocanhan/employee-info', [EmployeeProfileAdminController::class, 'employeeInfo'])
    ->middleware(['session.auth', 'permission:xem_nhanvien'])
    ->name('hosocanhan.employee-info');

Route::get('/employee-profiles/{profile}/detail', [EmployeeProfileAdminController::class, 'show'])
    ->middleware(['session.auth', 'permission:xem_nhanvien'])
    ->name('employee-profiles.show');
Route::get('/hosocanhan/{profile}/detail', [EmployeeProfileAdminController::class, 'show'])
    ->middleware(['session.auth', 'permission:xem_nhanvien'])
    ->name('hosocanhan.show');
Route::put('/services/{service}/{resource}/{id}', [ServiceConsoleController::class, 'update'])
    ->middleware(['session.auth', 'permission:xem_phanquyen'])
    ->name('services.update');

Route::delete('/services/{service}/{resource}/{id}', [ServiceConsoleController::class, 'destroy'])
    ->middleware(['session.auth', 'permission:xem_phanquyen'])
    ->name('services.destroy');

Route::get('/admin/phanquyen', function () {
    return response()->json([
        'ok' => true,
        'message' => 'Ban co quyen xem_phanquyen trong Laravel middleware.',
    ]);
})->middleware(['session.auth', 'permission:xem_phanquyen']);

Route::post('/accounts/{account}/reset-temporary', [AccountAdminController::class, 'resetTemporaryPassword'])
    ->middleware(['session.auth', 'permission:sua_taikhoan'])
    ->name('accounts.reset-temporary');
Route::post('/taikhoan/{account}/reset-temporary', [AccountAdminController::class, 'resetTemporaryPassword'])
    ->middleware(['session.auth', 'permission:sua_taikhoan'])
    ->name('taikhoan.reset-temporary');

Route::get('/accounts/{account}/delete-legacy', [AccountAdminController::class, 'destroyLegacy'])
    ->middleware(['session.auth', 'permission:xoa_taikhoan'])
    ->name('accounts.admin.destroy.legacy');
Route::get('/taikhoan/{account}/delete-legacy', [AccountAdminController::class, 'destroyLegacy'])
    ->middleware(['session.auth', 'permission:xoa_taikhoan'])
    ->name('taikhoan.admin.destroy.legacy');

$registerResourceModuleRoutes = function (string $prefix, string $namePrefix, array $moduleConfig, string $moduleKey) {
    Route::prefix($prefix)->name($namePrefix . '.')->group(function () use ($moduleConfig, $moduleKey) {
        $isReadOnly = (bool) ($moduleConfig['read_only'] ?? false);
        $disableExport = (bool) ($moduleConfig['disable_export'] ?? false);

        Route::get('/', [ResourceModuleController::class, 'index'])
            ->name('index')
            ->middleware(['session.auth', 'permission:' . $moduleConfig['permission']['view']])
            ->defaults('module', $moduleKey);

        if (!$disableExport) {
            Route::get('/export-excel', [ResourceModuleController::class, 'exportExcel'])
                ->name('export-excel')
                ->middleware(['session.auth', 'permission:' . $moduleConfig['permission']['view']])
                ->defaults('module', $moduleKey);
        }

        if (($moduleConfig['legacy_name'] ?? $moduleKey) === 'quyphep') {
            Route::post('/seed-annual-balances', [ResourceModuleController::class, 'seedAnnualLeaveBalances'])
                ->name('seed-annual')
                ->middleware(['session.auth', 'permission:sua_quyphep'])
                ->defaults('module', $moduleKey);
        }

        if (!$isReadOnly) {
            Route::get('/create', [ResourceModuleController::class, 'create'])
                ->name('create')
                ->middleware(['session.auth', 'permission:' . $moduleConfig['permission']['create']])
                ->defaults('module', $moduleKey);

            Route::post('/', [ResourceModuleController::class, 'store'])
                ->name('store')
                ->middleware(['session.auth', 'permission:' . $moduleConfig['permission']['create']])
                ->defaults('module', $moduleKey);

            Route::get('/{record}/edit', [ResourceModuleController::class, 'edit'])
                ->name('edit')
                ->middleware(['session.auth', 'permission:' . $moduleConfig['permission']['update']])
                ->defaults('module', $moduleKey);

            Route::put('/{record}', [ResourceModuleController::class, 'update'])
                ->name('update')
                ->middleware(['session.auth', 'permission:' . $moduleConfig['permission']['update']])
                ->defaults('module', $moduleKey);

            Route::post('/{record}', [ResourceModuleController::class, 'update'])
                ->name('update.legacy')
                ->middleware(['session.auth', 'permission:' . $moduleConfig['permission']['update']])
                ->defaults('module', $moduleKey);

            Route::delete('/{record}', [ResourceModuleController::class, 'destroy'])
                ->name('destroy')
                ->middleware(['session.auth', 'permission:' . $moduleConfig['permission']['delete']])
                ->defaults('module', $moduleKey);

            Route::get('/{record}/delete-legacy', [ResourceModuleController::class, 'destroyLegacy'])
                ->name('destroy.legacy')
                ->middleware(['session.auth', 'permission:' . $moduleConfig['permission']['delete']])
                ->defaults('module', $moduleKey);
        }

        if (($moduleConfig['legacy_name'] ?? $moduleKey) === 'phancong') {
            Route::get('/history', [ResourceModuleController::class, 'assignmentHistory'])
                ->name('history')
                ->middleware(['session.auth', 'permission:xem_lichsu_phancong'])
                ->defaults('module', $moduleKey);
        }

        if (($moduleConfig['legacy_name'] ?? $moduleKey) === 'baohiem') {
            Route::get('/{record}/deactivate-legacy', [ResourceModuleController::class, 'deactivateInsurance'])
                ->name('deactivate.legacy')
                ->middleware(['session.auth', 'permission:dung_baohiem'])
                ->defaults('module', $moduleKey);
        }

        if (($moduleConfig['legacy_name'] ?? $moduleKey) === 'nghiphep') {
            Route::get('/{record}/approve-legacy', [ResourceModuleController::class, 'approveLeaveRequest'])
                ->name('approve.legacy')
                ->middleware(['session.auth', 'permission:duyet_nghiphep'])
                ->defaults('module', $moduleKey);

            Route::get('/{record}/reject-legacy', [ResourceModuleController::class, 'rejectLeaveRequest'])
                ->name('reject.legacy')
                ->middleware(['session.auth', 'permission:tuchoi_nghiphep'])
                ->defaults('module', $moduleKey);
        }

    });
};

foreach (config('laravel_resource_modules', []) as $moduleKey => $moduleConfig) {
    $registerResourceModuleRoutes($moduleKey, $moduleKey, $moduleConfig, $moduleKey);

    $legacyPrefix = (string) ($moduleConfig['legacy_prefix'] ?? '');
    $legacyName = (string) ($moduleConfig['legacy_name'] ?? $legacyPrefix);

    if ($legacyPrefix !== '' && ($legacyPrefix !== $moduleKey || $legacyName !== $moduleKey)) {
        $registerResourceModuleRoutes($legacyPrefix, $legacyName, $moduleConfig, $moduleKey);
    }
}

// Explicit override for leave-balances edit to tolerate legacy identifiers and avoid false 404.
Route::get('/quyphep/{record}/edit', function (string $record) {
    $moduleKey = 'leave-balances';
    $moduleConfig = (array) config('laravel_resource_modules.leave-balances', []);

    abort_if($moduleConfig === [], 404);

    $service = (string) ($moduleConfig['service'] ?? 'hr');
    $resource = (string) ($moduleConfig['resource'] ?? 'leave-balances');
    $conn = (string) config("service_registry.services.{$service}.connection", config('database.default'));
    $resourceDef = (array) config("service_registry.services.{$service}.resources.{$resource}", []);
    $primaryKey = (string) ($resourceDef['primary_key'] ?? 'id');

    $item = \Illuminate\Support\Facades\DB::connection($conn)
        ->table('leave_balances')
        ->where($primaryKey, urldecode($record))
        ->first();

    if ($item === null && ctype_digit($record)) {
        $item = \Illuminate\Support\Facades\DB::connection($conn)
            ->table('leave_balances')
            ->where('MaNV', (int) $record)
            ->orderByDesc('Nam')
            ->orderByDesc('id')
            ->first();
    }

    abort_if($item === null, 404);

    $recordData = (array) $item;
    $recordId = (string) ($recordData['id'] ?? $record);
    $recordData['__resource_id'] = $recordId;

    $columns = \Illuminate\Support\Facades\DB::connection($conn)
        ->select('SHOW COLUMNS FROM `leave_balances`');

    $resourceConfig = [
        'primary_key' => $primaryKey,
        'read_only' => false,
        'columns' => array_map(static function ($column) {
            $column = (array) $column;

            return [
                'field' => (string) ($column['Field'] ?? ''),
                'type' => (string) ($column['Type'] ?? 'text'),
                'nullable' => (($column['Null'] ?? 'NO') === 'YES'),
                'key' => (string) ($column['Key'] ?? ''),
                'default' => $column['Default'] ?? null,
                'extra' => (string) ($column['Extra'] ?? ''),
            ];
        }, $columns),
    ];

    $fieldLookups = [];
    foreach ((array) ($moduleConfig['field_lookups'] ?? []) as $field => $lookup) {
        $lookupConn = (string) config("service_registry.services.{$lookup['service']}.connection", config('database.default'));
        $rows = \Illuminate\Support\Facades\DB::connection($lookupConn)
            ->table($lookup['table'])
            ->orderBy($lookup['label_col'])
            ->get([$lookup['value_col'], $lookup['label_col']]);

        $fieldLookups[$field] = $rows->map(fn ($row) => [
            'value' => $row->{$lookup['value_col']},
            'label' => $row->{$lookup['label_col']},
        ])->all();
    }

    return view('resource_modules.form', [
        'mode' => 'edit',
        'moduleKey' => $moduleKey,
        'routeKey' => 'quyphep',
        'moduleConfig' => $moduleConfig,
        'resourceConfig' => $resourceConfig,
        'record' => $recordData,
        'recordId' => $recordId,
        'fieldLookups' => $fieldLookups,
    ]);
})->middleware(['session.auth', 'permission:sua_quyphep'])->name('quyphep.edit');

// Explicitly override edit route for employee profiles to guarantee module mapping.
Route::get('/hosocanhan/create', function () {
    $moduleConfig = config('laravel_resource_modules.employee-profiles');
    $resourceConfig = [
        'columns' => [
            ['field' => 'MaHoSo', 'type' => 'int', 'nullable' => false, 'default' => null, 'extra' => 'auto_increment', 'key' => 'PRI'],
            ['field' => 'MaNV', 'type' => 'int', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => 'UNI'],
            ['field' => 'CCCD', 'type' => 'varchar(20)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NoiCap', 'type' => 'varchar(100)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NgayCap', 'type' => 'date', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'DiaChi', 'type' => 'text', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'DanToc', 'type' => 'varchar(50)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'TonGiao', 'type' => 'varchar(50)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'TrinhDo', 'type' => 'varchar(100)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'ChuyenMon', 'type' => 'varchar(100)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NgayVaoLam', 'type' => 'date', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'MaPB', 'type' => 'int', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => 'MUL'],
            ['field' => 'MaCV', 'type' => 'int', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => 'MUL'],
            ['field' => 'TrangThaiHonNhan', 'type' => 'varchar(50)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'Anh', 'type' => 'varchar(255)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
        ],
        'primary_key' => 'MaHoSo',
    ];

    $fieldLookups = [];
    if (!empty($moduleConfig['field_lookups'])) {
        foreach ($moduleConfig['field_lookups'] as $field => $lookup) {
            $lookupConn = config("service_registry.services.{$lookup['service']}.connection", config('database.default'));
            $options = \Illuminate\Support\Facades\DB::connection($lookupConn)
                ->table($lookup['table'])
                ->select($lookup['value_col'], $lookup['label_col'])
                ->get()
                ->map(fn($row) => ['value' => $row->{$lookup['value_col']}, 'label' => $row->{$lookup['label_col']}])
                ->all();
            $fieldLookups[$field] = $options;
        }
    }

    return view('resource_modules.form', [
        'mode' => 'create',
        'moduleKey' => 'employee-profiles',
        'routeKey' => 'hosocanhan',
        'moduleConfig' => $moduleConfig,
        'resourceConfig' => $resourceConfig,
        'record' => [],
        'recordId' => null,
        'fieldLookups' => $fieldLookups,
    ]);
})->middleware(['session.auth', 'permission:them_hoso'])
    ->name('hosocanhan.create');

Route::post('/hosocanhan', function (\Illuminate\Http\Request $request) {
    $conn = config('service_registry.services.hr.connection', config('database.default'));
    $payload = $request->all();
    unset($payload['_method'], $payload['_token'], $payload['_csrf_token'], $payload['created_at'], $payload['updated_at']);

    if ($request->hasFile('Anh') && $request->file('Anh')->isValid()) {
        $file = $request->file('Anh');
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $uploadDir = base_path('../uploads/photos');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $file->move($uploadDir, $filename);
        $payload['Anh'] = $filename;
    } else {
        unset($payload['Anh']);
    }

    $payload = array_filter($payload, fn($v) => $v !== '' && $v !== null);

    try {
        $id = \Illuminate\Support\Facades\DB::connection($conn)
            ->table('hosonhanvien')
            ->insertGetId($payload, 'MaHoSo');

        if (!empty($payload['MaNV'])) {
            \Illuminate\Support\Facades\DB::connection($conn)
                ->table('nhanvien')
                ->where('MaNV', $payload['MaNV'])
                ->update(['MaHS' => $id]);
        }

        return redirect()->route('hosocanhan.edit', ['record' => $id])
            ->with('success', 'Thêm hồ sơ cá nhân thành công.');
    } catch (\Illuminate\Database\QueryException $e) {
        $msg = 'Không thể thêm hồ sơ cá nhân.';
        if (($e->errorInfo[1] ?? null) === 1062) {
            $msg = 'Nhân viên này đã có hồ sơ cá nhân.';
        }
        return back()->withInput()->withErrors(['form' => $msg]);
    }
})->middleware(['session.auth', 'permission:them_hoso'])
    ->name('hosocanhan.store');

Route::get('/hosocanhan/{record}/edit', function (string $record) {
    $conn = config('service_registry.services.hr.connection', config('database.default'));
    $item = \Illuminate\Support\Facades\DB::connection($conn)
        ->table('hosonhanvien')
        ->where('MaHoSo', $record)
        ->first();

    if ($item === null && ctype_digit($record)) {
        $item = \Illuminate\Support\Facades\DB::connection($conn)
            ->table('hosonhanvien')
            ->where('MaNV', (int) $record)
            ->first();
    }

    abort_if($item === null, 404, 'Không tìm thấy hồ sơ cá nhân');

    $recordData = (array) $item;
    $recordId = (string) ($recordData['MaHoSo'] ?? $record);
    $recordData['__resource_id'] = $recordId;

    $moduleConfig = config('laravel_resource_modules.employee-profiles');
    $resourceConfig = [
        'columns' => [
            ['field' => 'MaHoSo', 'type' => 'int', 'nullable' => false, 'default' => null, 'extra' => 'auto_increment', 'key' => 'PRI'],
            ['field' => 'MaNV', 'type' => 'int', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => 'UNI'],
            ['field' => 'CCCD', 'type' => 'varchar(20)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NoiCap', 'type' => 'varchar(100)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NgayCap', 'type' => 'date', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'DiaChi', 'type' => 'text', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'DanToc', 'type' => 'varchar(50)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'TonGiao', 'type' => 'varchar(50)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'TrinhDo', 'type' => 'varchar(100)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'ChuyenMon', 'type' => 'varchar(100)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NgayVaoLam', 'type' => 'date', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'MaPB', 'type' => 'int', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => 'MUL'],
            ['field' => 'MaCV', 'type' => 'int', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => 'MUL'],
            ['field' => 'TrangThaiHonNhan', 'type' => 'varchar(50)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'Anh', 'type' => 'varchar(255)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
        ],
        'primary_key' => 'MaHoSo',
    ];

    $fieldLookups = [];
    if (!empty($moduleConfig['field_lookups'])) {
        foreach ($moduleConfig['field_lookups'] as $field => $lookup) {
            $lookupConn = config("service_registry.services.{$lookup['service']}.connection", config('database.default'));
            $options = \Illuminate\Support\Facades\DB::connection($lookupConn)
                ->table($lookup['table'])
                ->select($lookup['value_col'], $lookup['label_col'])
                ->get()
                ->map(fn($row) => ['value' => $row->{$lookup['value_col']}, 'label' => $row->{$lookup['label_col']}])
                ->all();
            $fieldLookups[$field] = $options;
        }
    }

    return view('resource_modules.form', [
        'mode' => 'edit',
        'moduleKey' => 'employee-profiles',
        'routeKey' => 'hosocanhan',
        'moduleConfig' => $moduleConfig,
        'resourceConfig' => $resourceConfig,
        'record' => $recordData,
        'recordId' => $recordId,
        'fieldLookups' => $fieldLookups,
    ]);
})->middleware(['session.auth', 'permission:sua_nhanvien'])
    ->name('hosocanhan.edit');

Route::put('/hosocanhan/{record}', function (\Illuminate\Http\Request $request, string $record) {
    $conn = config('service_registry.services.hr.connection', config('database.default'));
    $query = \Illuminate\Support\Facades\DB::connection($conn)->table('hosonhanvien');

    $item = $query->where('MaHoSo', $record)->first();
    if ($item === null && ctype_digit($record)) {
        $item = \Illuminate\Support\Facades\DB::connection($conn)
            ->table('hosonhanvien')
            ->where('MaNV', (int) $record)
            ->first();
    }

    abort_if($item === null, 404, 'Không tìm thấy hồ sơ cá nhân');

    $profileId = (string) ($item->MaHoSo ?? $record);
    $payload = $request->all();
    unset($payload['_method'], $payload['_token'], $payload['_csrf_token'], $payload['created_at'], $payload['updated_at']);

    if ($request->hasFile('Anh') && $request->file('Anh')->isValid()) {
        $file = $request->file('Anh');
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $uploadDir = base_path('../uploads/photos');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $file->move($uploadDir, $filename);
        $payload['Anh'] = $filename;
    } else {
        unset($payload['Anh']);
    }

    $payload = array_filter($payload, fn($v) => $v !== '' && $v !== null);

    if (!empty($payload)) {
        \Illuminate\Support\Facades\DB::connection($conn)
            ->table('hosonhanvien')
            ->where('MaHoSo', $profileId)
            ->update($payload);
    }

    return redirect()->route('hosocanhan.edit', ['record' => $profileId])
        ->with('success', 'Cập nhật hồ sơ cá nhân thành công.');
})->middleware(['session.auth', 'permission:sua_nhanvien'])
    ->name('hosocanhan.update');

Route::get('/hosocanhan/{record}', function (string $record) {
    return redirect()->route('hosocanhan.edit', ['record' => $record]);
})->middleware(['session.auth', 'permission:sua_nhanvien'])
    ->name('hosocanhan.show.legacy');

// Explicitly override edit route for assignments to guarantee module mapping.
Route::get('/phancong/{record}/edit', function (string $record) {
    return app(ResourceModuleController::class)->edit('assignments', $record);
})->middleware(['session.auth', 'permission:sua_phancong'])
    ->name('phancong.edit');

// Dedicated save endpoint for assignment edit form to avoid legacy POST ambiguity.
Route::post('/phancong/{record}/save', function (\Illuminate\Http\Request $request, string $record) {
    return app(ResourceModuleController::class)->update($request, 'assignments', $record);
})->middleware(['session.auth', 'permission:sua_phancong'])
    ->name('phancong.save');

// Keep legacy submit endpoints working for pages loaded before the /save rollout.
Route::put('/phancong/{record}', function (\Illuminate\Http\Request $request, string $record) {
    return app(ResourceModuleController::class)->update($request, 'assignments', $record);
})->middleware(['session.auth', 'permission:sua_phancong'])
    ->name('phancong.update');

Route::post('/phancong/{record}', function (\Illuminate\Http\Request $request, string $record) {
    return app(ResourceModuleController::class)->update($request, 'assignments', $record);
})->middleware(['session.auth', 'permission:sua_phancong'])
    ->name('phancong.update.legacy');

// Compatibility fallback: some legacy flows may land on /phancong/{record} after save.
Route::get('/phancong/{record}', function (string $record) {
    return redirect()->route('phancong.edit', ['record' => $record]);
})->middleware(['session.auth', 'permission:sua_phancong'])
    ->name('phancong.show.legacy');

// Explicit routes for accounts with direct DB access to bypass API issues.
Route::get('/taikhoan/create', function () {
    $moduleConfig = config('laravel_resource_modules.accounts');
    $resourceConfig = [
        'columns' => [
            ['field' => 'MaTK', 'type' => 'int', 'nullable' => false, 'default' => null, 'extra' => 'auto_increment', 'key' => 'PRI'],
            ['field' => 'TenDangNhap', 'type' => 'varchar(50)', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'MatKhau', 'type' => 'varchar(255)', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'MaNV', 'type' => 'varchar(10)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'MaNVRef', 'type' => 'int', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'TrangThai', 'type' => 'varchar(20)', 'nullable' => true, 'default' => 'Hoạt động', 'extra' => '', 'key' => ''],
            ['field' => 'BuocDoiMatKhau', 'type' => 'tinyint(1)', 'nullable' => false, 'default' => 0, 'extra' => '', 'key' => ''],
        ],
        'primary_key' => 'MaTK',
    ];

    $fieldLookups = [];
    if (!empty($moduleConfig['field_lookups'])) {
        foreach ($moduleConfig['field_lookups'] as $field => $lookup) {
            $lookupConn = config("service_registry.services.{$lookup['service']}.connection", config('database.default'));
            $options = \Illuminate\Support\Facades\DB::connection($lookupConn)
                ->table($lookup['table'])
                ->select($lookup['value_col'], $lookup['label_col'])
                ->get()
                ->map(fn($row) => ['value' => $row->{$lookup['value_col']}, 'label' => $row->{$lookup['label_col']}])
                ->all();
            $fieldLookups[$field] = $options;
        }
    }

    return view('resource_modules.form', [
        'mode' => 'create',
        'moduleKey' => 'accounts',
        'routeKey' => 'taikhoan',
        'moduleConfig' => $moduleConfig,
        'resourceConfig' => $resourceConfig,
        'record' => [],
        'recordId' => null,
        'fieldLookups' => $fieldLookups,
    ]);
})->middleware(['session.auth', 'permission:them_taikhoan'])
    ->name('taikhoan.create');

Route::post('/taikhoan', function (\Illuminate\Http\Request $request) {
    $conn = config('service_registry.services.hr.connection', config('database.default'));

    $payload = $request->all();
    unset(
        $payload['_method'],
        $payload['_token'],
        $payload['_csrf_token'],
        $payload['MaTK'],
        $payload['NgayTao'],
        $payload['NgayCapMatKhauTam'],
        $payload['created_at'],
        $payload['updated_at']
    );
    $payload = array_filter($payload, fn($v) => $v !== '' && $v !== null);

    if (!empty($payload['MatKhau'])) {
        $payload['MatKhau'] = \Illuminate\Support\Facades\Hash::make((string) $payload['MatKhau']);
    }

    if (!isset($payload['BuocDoiMatKhau'])) {
        $payload['BuocDoiMatKhau'] = 0;
    }

    try {
        $id = \Illuminate\Support\Facades\DB::connection($conn)
            ->table('taikhoan')
            ->insertGetId($payload, 'MaTK');

        return redirect()->route('taikhoan.edit', ['record' => $id])
            ->with('success', 'Thêm tài khoản thành công.');
    } catch (\Illuminate\Database\QueryException $e) {
        $msg = 'Không thể thêm tài khoản.';
        if (($e->errorInfo[1] ?? null) === 1062) {
            $detail = (string) ($e->errorInfo[2] ?? '');
            if (stripos($detail, 'TenDangNhap') !== false) {
                $msg = 'Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.';
            } elseif (stripos($detail, 'MaNV') !== false) {
                $msg = 'Nhân viên này đã có tài khoản.';
            } else {
                $msg = 'Dữ liệu bị trùng (tên đăng nhập hoặc nhân viên).';
            }
        }

        return back()->withInput()->withErrors(['form' => $msg]);
    }
})->middleware(['session.auth', 'permission:them_taikhoan'])
    ->name('taikhoan.store');

Route::get('/taikhoan/{record}/edit', function (string $record) {
    $conn = config('service_registry.services.hr.connection', config('database.default'));
    $item = \Illuminate\Support\Facades\DB::connection($conn)
        ->table('taikhoan')
        ->where('MaTK', $record)
        ->first();

    abort_if($item === null, 404, 'Không tìm thấy tài khoản');

    $recordData = (array) $item;
    $recordData['__resource_id'] = $record;

    $moduleConfig = config('laravel_resource_modules.accounts');
    $resourceConfig = [
        'columns' => [
            ['field' => 'MaTK', 'type' => 'int', 'nullable' => false, 'default' => null, 'extra' => 'auto_increment', 'key' => 'PRI'],
            ['field' => 'TenDangNhap', 'type' => 'varchar(50)', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'MatKhau', 'type' => 'varchar(255)', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'MaNV', 'type' => 'varchar(10)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'MaNVRef', 'type' => 'int', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'TrangThai', 'type' => 'varchar(20)', 'nullable' => true, 'default' => 'Hoạt động', 'extra' => '', 'key' => ''],
            ['field' => 'BuocDoiMatKhau', 'type' => 'tinyint(1)', 'nullable' => false, 'default' => 0, 'extra' => '', 'key' => ''],
        ],
        'primary_key' => 'MaTK',
    ];

    $fieldLookups = [];
    if (!empty($moduleConfig['field_lookups'])) {
        foreach ($moduleConfig['field_lookups'] as $field => $lookup) {
            $lookupConn = config("service_registry.services.{$lookup['service']}.connection", config('database.default'));
            $options = \Illuminate\Support\Facades\DB::connection($lookupConn)
                ->table($lookup['table'])
                ->select($lookup['value_col'], $lookup['label_col'])
                ->get()
                ->map(fn($row) => ['value' => $row->{$lookup['value_col']}, 'label' => $row->{$lookup['label_col']}])
                ->all();
            $fieldLookups[$field] = $options;
        }
    }

    return view('resource_modules.form', [
        'mode' => 'edit',
        'moduleKey' => 'accounts',
        'routeKey' => 'taikhoan',
        'moduleConfig' => $moduleConfig,
        'resourceConfig' => $resourceConfig,
        'record' => $recordData,
        'recordId' => $record,
        'fieldLookups' => $fieldLookups,
    ]);
})->middleware(['session.auth', 'permission:sua_taikhoan'])
    ->name('taikhoan.edit');

Route::get('/taikhoan/{record}', function (string $record) {
    return redirect()->route('taikhoan.edit', ['record' => $record]);
})->middleware(['session.auth', 'permission:sua_taikhoan'])
    ->name('taikhoan.show.legacy');

// Explicitly override edit route for contracts to guarantee module mapping.
Route::get('/hopdong/create', function () {
    $moduleConfig = config('laravel_resource_modules.contracts');
    $conn = config('service_registry.services.payroll.connection', config('database.default'));
    $resourceConfig = [
        'columns' => [
            ['field' => 'MaHopDong',   'type' => 'int',         'nullable' => false, 'default' => null, 'extra' => 'auto_increment', 'key' => 'PRI'],
            ['field' => 'SoHopDong',   'type' => 'varchar(50)', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'MaNV',        'type' => 'int',         'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'MaBac',       'type' => 'int',         'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'LoaiHopDong', 'type' => 'varchar(50)', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NgayKy',      'type' => 'date',        'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NgayBatDau',  'type' => 'date',        'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NgayKetThuc', 'type' => 'date',        'nullable' => true,  'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'TrangThai',   'type' => 'varchar(50)', 'nullable' => true,  'default' => 'Còn hiệu lực', 'extra' => '', 'key' => ''],
            ['field' => 'GhiChu',      'type' => 'text',        'nullable' => true,  'default' => null, 'extra' => '', 'key' => ''],
        ],
        'primary_key' => 'MaHopDong',
    ];

    $fieldLookups = [];
    if (!empty($moduleConfig['field_lookups'])) {
        foreach ($moduleConfig['field_lookups'] as $field => $lookup) {
            $lookupConn = config("service_registry.services.{$lookup['service']}.connection", config('database.default'));
            $options = \Illuminate\Support\Facades\DB::connection($lookupConn)
                ->table($lookup['table'])
                ->select($lookup['value_col'], $lookup['label_col'])
                ->get()
                ->map(fn($row) => ['value' => $row->{$lookup['value_col']}, 'label' => $row->{$lookup['label_col']}])
                ->all();
            $fieldLookups[$field] = $options;
        }
    }

    return view('resource_modules.form', [
        'mode' => 'create',
        'moduleKey' => 'contracts',
        'routeKey' => 'hopdong',
        'moduleConfig' => $moduleConfig,
        'resourceConfig' => $resourceConfig,
        'record' => [],
        'recordId' => null,
        'fieldLookups' => $fieldLookups,
    ]);
})->middleware(['session.auth', 'permission:them_hopdong'])
    ->name('hopdong.create');

Route::post('/hopdong', function (\Illuminate\Http\Request $request) {
    $conn = config('service_registry.services.payroll.connection', config('database.default'));

    $payload = $request->all();
    unset($payload['_method'], $payload['_token'], $payload['_csrf_token'], $payload['created_at'], $payload['updated_at']);
    $payload = array_filter($payload, fn($v) => $v !== '' && $v !== null);

    // Validate dates
    if (empty($payload['NgayBatDau']) || !strtotime((string) $payload['NgayBatDau'])) {
        return back()->withInput()->withErrors(['NgayBatDau' => 'Ngày bắt đầu không hợp lệ.']);
    }
    if (!empty($payload['NgayKetThuc'])) {
        if (!strtotime((string) $payload['NgayKetThuc'])) {
            return back()->withInput()->withErrors(['NgayKetThuc' => 'Ngày kết thúc không hợp lệ.']);
        }
        if (strtotime((string) $payload['NgayKetThuc']) < strtotime((string) $payload['NgayBatDau'])) {
            return back()->withInput()->withErrors(['NgayKetThuc' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.']);
        }
    }

    $id = \Illuminate\Support\Facades\DB::connection($conn)
        ->table('hopdong')
        ->insertGetId($payload, 'MaHopDong');

    return redirect()->route('hopdong.edit', ['record' => $id])
        ->with('success', 'Thêm hợp đồng thành công.');
})->middleware(['session.auth', 'permission:them_hopdong'])
    ->name('hopdong.store');

Route::get('/hopdong/{record}/edit', function (string $record) {
    return app(ResourceModuleController::class)->edit('contracts', $record);
})->middleware(['session.auth', 'permission:sua_hopdong'])
    ->name('hopdong.edit');

// Dedicated save endpoint for contract edit form to avoid legacy POST ambiguity.
Route::post('/hopdong/{record}/save', function (\Illuminate\Http\Request $request, string $record) {
    return app(ResourceModuleController::class)->update($request, 'contracts', $record);
})->middleware(['session.auth', 'permission:sua_hopdong'])
    ->name('hopdong.save');

// Keep legacy submit endpoints working for pages loaded before the /save rollout.
Route::put('/hopdong/{record}', function (\Illuminate\Http\Request $request, string $record) {
    return app(ResourceModuleController::class)->update($request, 'contracts', $record);
})->middleware(['session.auth', 'permission:sua_hopdong'])
    ->name('hopdong.update');

Route::post('/hopdong/{record}', function (\Illuminate\Http\Request $request, string $record) {
    return app(ResourceModuleController::class)->update($request, 'contracts', $record);
})->middleware(['session.auth', 'permission:sua_hopdong'])
    ->name('hopdong.update.legacy');

// Compatibility fallback: some legacy flows may land on /hopdong/{record} after save.
Route::get('/hopdong/{record}', function (string $record) {
    return redirect()->route('hopdong.edit', ['record' => $record]);
})->middleware(['session.auth', 'permission:sua_hopdong'])
    ->name('hopdong.show.legacy');

// Explicitly override edit route for leave requests with DB fallback.
Route::get('/nghiphep/{record}/edit', function (string $record) {
    $conn = config('service_registry.services.hr.connection', config('database.default'));
    $item = \Illuminate\Support\Facades\DB::connection($conn)
        ->table('nghiphep')
        ->where('MaNP', $record)
        ->first();
    
    abort_if($item === null, 404, 'Không tìm thấy đơn nghỉ phép');
    
    // Convert to array and add resource_id for template
    $record_data = (array) $item;
    $record_data['__resource_id'] = $record;
    
    // Get module config and resource config
    $moduleConfig = config('laravel_resource_modules.leave-requests');
    $resourceConfig = [
        'columns' => [
            ['field' => 'MaNP', 'type' => 'int', 'nullable' => false, 'default' => null, 'extra' => 'auto_increment', 'key' => 'PRI'],
            ['field' => 'MaNV', 'type' => 'int', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'TuNgay', 'type' => 'date', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'DenNgay', 'type' => 'date', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'SoNgayNghi', 'type' => 'int', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'LyDo', 'type' => 'varchar(255)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'LoaiNghi', 'type' => 'varchar(100)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'TrangThai', 'type' => 'varchar(50)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NgayNopDon', 'type' => 'date', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NgayDuyet', 'type' => 'date', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
        ],
        'primary_key' => 'MaNP',
    ];
    
    // Get field lookups
    $fieldLookups = [];
    if (!empty($moduleConfig['field_lookups'])) {
        foreach ($moduleConfig['field_lookups'] as $field => $lookup) {
            $lookupConn = config("service_registry.services.{$lookup['service']}.connection", config('database.default'));
            $options = \Illuminate\Support\Facades\DB::connection($lookupConn)
                ->table($lookup['table'])
                ->select($lookup['value_col'], $lookup['label_col'])
                ->get()
                ->map(fn($row) => ['value' => $row->{$lookup['value_col']}, 'label' => $row->{$lookup['label_col']}])
                ->all();
            $fieldLookups[$field] = $options;
        }
    }
    
    return view('nghiphep.form', [
        'mode' => 'edit',
        'moduleKey' => 'leave-requests',
        'routeKey' => 'nghiphep',
        'moduleConfig' => $moduleConfig,
        'resourceConfig' => $resourceConfig,
        'record' => $record_data,
        'recordId' => $record,
        'fieldLookups' => $fieldLookups,
    ]);
})->middleware(['session.auth', 'permission:sua_nghiphep'])
    ->name('nghiphep.edit');

// Explicit PUT route for leave requests update: direct DB update to avoid API issues.
Route::put('/nghiphep/{record}', function (\Illuminate\Http\Request $request, string $record) {
    $conn = config('service_registry.services.hr.connection', config('database.default'));
    
    // Validate record exists
    $item = \Illuminate\Support\Facades\DB::connection($conn)->table('nghiphep')->where('MaNP', $record)->first();
    abort_if($item === null, 404, 'Không tìm thấy đơn nghỉ phép');
    
    // Prepare payload: only keep actual database fields, remove Laravel special fields
    $payload = $request->all();
    unset($payload['_method'], $payload['_token'], $payload['_csrf_token']); // Remove Laravel's special fields
    
    // Also remove empty values from the payload to avoid updating with NULLs
    $payload = array_filter($payload, function($value) {
        return $value !== '' && $value !== null;
    });
    
    // Update the record if there's data to update
    if (!empty($payload)) {
        \Illuminate\Support\Facades\DB::connection($conn)
            ->table('nghiphep')
            ->where('MaNP', $record)
            ->update($payload);
    }
    
    return redirect()->route('nghiphep.edit', ['record' => $record])
        ->with('success', 'Cập nhật đơn nghỉ phép thành công.');
})->middleware(['session.auth', 'permission:sua_nghiphep'])
    ->name('nghiphep.update');

// Compatibility fallback: redirect /nghiphep/{record} to /nghiphep/{record}/edit
Route::get('/nghiphep/{record}', function (string $record) {
    return redirect()->route('nghiphep.edit', ['record' => $record]);
})->middleware(['session.auth', 'permission:sua_nghiphep'])
    ->name('nghiphep.show.legacy');

// Override DELETE for leave requests: direct DB delete to avoid generic API chain errors.
Route::delete('/nghiphep/{record}', function (string $record) {
    $conn = config('service_registry.services.hr.connection', config('database.default'));
    $deleted = \Illuminate\Support\Facades\DB::connection($conn)
        ->table('nghiphep')
        ->where('MaNP', $record)
        ->delete();

    if ($deleted === 0) {
        return back()->withErrors(['form' => 'Không tìm thấy đơn nghỉ phép hoặc đã bị xóa.']);
    }

    return redirect()->route('nghiphep.index')->with('success', 'Đã xóa đơn nghỉ phép thành công.');
})->middleware(['session.auth', 'permission:xoa_nghiphep'])
    ->name('nghiphep.destroy');

// GET-based delete fallback for browsers that don't support DELETE method spoofing.
Route::get('/nghiphep/{record}/delete-legacy', function (string $record) {
    $conn = config('service_registry.services.hr.connection', config('database.default'));
    $deleted = \Illuminate\Support\Facades\DB::connection($conn)
        ->table('nghiphep')
        ->where('MaNP', $record)
        ->delete();

    if ($deleted === 0) {
        return back()->withErrors(['form' => 'Không tìm thấy đơn nghỉ phép hoặc đã bị xóa.']);
    }

    return redirect()->route('nghiphep.index')->with('success', 'Đã xóa đơn nghỉ phép thành công.');
})->middleware(['session.auth', 'permission:xoa_nghiphep'])
    ->name('nghiphep.destroy.legacy');

// Approve route for leave requests: update TrangThai to approved status and create attendance records.
Route::post('/nghiphep/{record}/approve', function (string $record) {
    $hrConn = config('service_registry.services.hr.connection', config('database.default'));
    $attendConn = config('service_registry.services.attendance.connection', config('database.default'));
    
    // Get the leave request
    $leave = \Illuminate\Support\Facades\DB::connection($hrConn)
        ->table('nghiphep')
        ->where('MaNP', $record)
        ->first();
    
    if (!$leave) {
        return back()->withErrors(['form' => 'Không tìm thấy đơn nghỉ phép.']);
    }
    
    // Update TrangThai to "Đã duyệt" and set NgayDuyet to today
    \Illuminate\Support\Facades\DB::connection($hrConn)
        ->table('nghiphep')
        ->where('MaNP', $record)
        ->update([
            'TrangThai' => 'Đã duyệt',
            'NgayDuyet' => \Illuminate\Support\Facades\DB::raw('CURDATE()'),
        ]);
    
    // Create attendance records for each day of the leave period
    $tuNgay = strtotime((string) $leave->TuNgay);
    $denNgay = strtotime((string) $leave->DenNgay);
    
    if ($tuNgay !== false && $denNgay !== false) {
        $cursor = $tuNgay;
        while ($cursor <= $denNgay) {
            $date = date('Y-m-d', $cursor);
            \Illuminate\Support\Facades\DB::connection($attendConn)
                ->table('chamcong')
                ->updateOrInsert(
                    ['MaNV' => (int) $leave->MaNV, 'Ngay' => $date],
                    ['TrangThai' => 'Nghi phep', 'GioVao' => null, 'GioRa' => null]
                );
            $cursor = strtotime('+1 day', $cursor);
        }
    }

    return redirect()->route('nghiphep.index')->with('success', 'Đã duyệt đơn nghỉ phép thành công và cập nhật chấm công.');
})->middleware(['session.auth', 'permission:sua_nghiphep'])
    ->name('nghiphep.approve');

// Explicit routes for insurance with direct DB access to bypass API issues.
Route::get('/baohiem/create', function () {
    $moduleConfig = config('laravel_resource_modules.insurances');
    $resourceConfig = [
        'columns' => [
            ['field' => 'MaBH', 'type' => 'int', 'nullable' => false, 'default' => null, 'extra' => 'auto_increment', 'key' => 'PRI'],
            ['field' => 'MaNV', 'type' => 'int', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'SoBHXH', 'type' => 'varchar(50)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'LoaiBaoHiem', 'type' => 'varchar(50)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NgayThamGia', 'type' => 'date', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'MucDong', 'type' => 'decimal(10,2)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'CongTyDong', 'type' => 'decimal(10,2)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NhanVienDong', 'type' => 'decimal(10,2)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'TrangThai', 'type' => 'varchar(50)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
        ],
        'primary_key' => 'MaBH',
    ];

    $fieldLookups = [];
    if (!empty($moduleConfig['field_lookups'])) {
        foreach ($moduleConfig['field_lookups'] as $field => $lookup) {
            $lookupConn = config("service_registry.services.{$lookup['service']}.connection", config('database.default'));
            $options = \Illuminate\Support\Facades\DB::connection($lookupConn)
                ->table($lookup['table'])
                ->select($lookup['value_col'], $lookup['label_col'])
                ->get()
                ->map(fn($row) => ['value' => $row->{$lookup['value_col']}, 'label' => $row->{$lookup['label_col']}])
                ->all();
            $fieldLookups[$field] = $options;
        }
    }

    return view('baohiem.form', [
        'mode' => 'create',
        'moduleKey' => 'insurances',
        'routeKey' => 'baohiem',
        'moduleConfig' => $moduleConfig,
        'resourceConfig' => $resourceConfig,
        'record' => [],
        'recordId' => null,
        'fieldLookups' => $fieldLookups,
    ]);
})->middleware(['session.auth', 'permission:them_baohiem'])
    ->name('baohiem.create');

Route::post('/baohiem', function (\Illuminate\Http\Request $request) {
    $conn = config('service_registry.services.hr.connection', config('database.default'));
    
    // Prepare payload and remove special Laravel fields
    $payload = $request->all();
    unset($payload['_method'], $payload['_token'], $payload['_csrf_token'], $payload['created_at'], $payload['updated_at']);
    $payload = array_filter($payload, fn($v) => $v !== '' && $v !== null);

    if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('baohiem', 'created_at')) {
        $payload['created_at'] = now();
    }
    
    try {
        $id = \Illuminate\Support\Facades\DB::connection($conn)
            ->table('baohiem')
            ->insertGetId($payload, 'MaBH');

        return redirect()->route('baohiem.edit', ['record' => $id])
            ->with('success', 'Thêm bảo hiểm thành công.');
    } catch (\Illuminate\Database\QueryException $e) {
        $msg = 'Không thể thêm bảo hiểm.';
        if ($e->errorInfo[1] === 1062) {
            $msg = 'Số BHXH này đã tồn tại trong hệ thống. Vui lòng kiểm tra lại.';
        }
        return back()->withInput()->withErrors(['form' => $msg]);
    }
})->middleware(['session.auth', 'permission:them_baohiem'])
    ->name('baohiem.store');

Route::get('/baohiem/{record}/edit', function (string $record) {
    $conn = config('service_registry.services.hr.connection', config('database.default'));
    $item = \Illuminate\Support\Facades\DB::connection($conn)
        ->table('baohiem')
        ->where('MaBH', $record)
        ->first();
    
    abort_if($item === null, 404, 'Không tìm thấy bảo hiểm');
    
    $record_data = (array) $item;
    $record_data['__resource_id'] = $record;
    
    $moduleConfig = config('laravel_resource_modules.insurances');
    $resourceConfig = [
        'columns' => [
            ['field' => 'MaBH', 'type' => 'int', 'nullable' => false, 'default' => null, 'extra' => 'auto_increment', 'key' => 'PRI'],
            ['field' => 'MaNV', 'type' => 'int', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'SoBHXH', 'type' => 'varchar(50)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'LoaiBaoHiem', 'type' => 'varchar(50)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NgayThamGia', 'type' => 'date', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'MucDong', 'type' => 'decimal(10,2)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'CongTyDong', 'type' => 'decimal(10,2)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NhanVienDong', 'type' => 'decimal(10,2)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'TrangThai', 'type' => 'varchar(50)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
        ],
        'primary_key' => 'MaBH',
    ];
    
    $fieldLookups = [];
    if (!empty($moduleConfig['field_lookups'])) {
        foreach ($moduleConfig['field_lookups'] as $field => $lookup) {
            $lookupConn = config("service_registry.services.{$lookup['service']}.connection", config('database.default'));
            $options = \Illuminate\Support\Facades\DB::connection($lookupConn)
                ->table($lookup['table'])
                ->select($lookup['value_col'], $lookup['label_col'])
                ->get()
                ->map(fn($row) => ['value' => $row->{$lookup['value_col']}, 'label' => $row->{$lookup['label_col']}])
                ->all();
            $fieldLookups[$field] = $options;
        }
    }
    
    return view('baohiem.form', [
        'mode' => 'edit',
        'moduleKey' => 'insurances',
        'routeKey' => 'baohiem',
        'moduleConfig' => $moduleConfig,
        'resourceConfig' => $resourceConfig,
        'record' => $record_data,
        'recordId' => $record,
        'fieldLookups' => $fieldLookups,
    ]);
})->middleware(['session.auth', 'permission:sua_baohiem'])
    ->name('baohiem.edit');

Route::put('/baohiem/{record}', function (\Illuminate\Http\Request $request, string $record) {
    $conn = config('service_registry.services.hr.connection', config('database.default'));
    
    $item = \Illuminate\Support\Facades\DB::connection($conn)->table('baohiem')->where('MaBH', $record)->first();
    abort_if($item === null, 404, 'Không tìm thấy bảo hiểm');
    
    $payload = $request->all();
    unset($payload['_method'], $payload['_token'], $payload['_csrf_token'], $payload['created_at'], $payload['updated_at']);
    $payload = array_filter($payload, fn($v) => $v !== '' && $v !== null);
    
    if (!empty($payload)) {
        \Illuminate\Support\Facades\DB::connection($conn)
            ->table('baohiem')
            ->where('MaBH', $record)
            ->update($payload);
    }
    
    return redirect()->route('baohiem.edit', ['record' => $record])
        ->with('success', 'Cập nhật bảo hiểm thành công.');
})->middleware(['session.auth', 'permission:sua_baohiem'])
    ->name('baohiem.update');

// Explicit routes for reward-discipline records with direct DB access to bypass API issues.
Route::get('/khenthuong/create', function () {
    $moduleConfig = config('laravel_resource_modules.reward-records');
    $resourceConfig = [
        'columns' => [
            ['field' => 'MaKTKL', 'type' => 'int', 'nullable' => false, 'default' => null, 'extra' => 'auto_increment', 'key' => 'PRI'],
            ['field' => 'MaNV', 'type' => 'int', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'MaLoai', 'type' => 'int', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NgayQuyetDinh', 'type' => 'date', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'HinhThuc', 'type' => 'varchar(150)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'SoTien', 'type' => 'decimal(15,2)', 'nullable' => true, 'default' => '0.00', 'extra' => '', 'key' => ''],
            ['field' => 'LyDo', 'type' => 'text', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'GhiChu', 'type' => 'text', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
        ],
        'primary_key' => 'MaKTKL',
    ];

    $fieldLookups = [];
    if (!empty($moduleConfig['field_lookups'])) {
        foreach ($moduleConfig['field_lookups'] as $field => $lookup) {
            $lookupConn = config("service_registry.services.{$lookup['service']}.connection", config('database.default'));
            $options = \Illuminate\Support\Facades\DB::connection($lookupConn)
                ->table($lookup['table'])
                ->select($lookup['value_col'], $lookup['label_col'])
                ->get()
                ->map(fn($row) => ['value' => $row->{$lookup['value_col']}, 'label' => $row->{$lookup['label_col']}])
                ->all();
            $fieldLookups[$field] = $options;
        }
    }

    return view('resource_modules.form', [
        'mode' => 'create',
        'moduleKey' => 'reward-records',
        'routeKey' => 'khenthuong',
        'moduleConfig' => $moduleConfig,
        'resourceConfig' => $resourceConfig,
        'record' => [],
        'recordId' => null,
        'fieldLookups' => $fieldLookups,
    ]);
})->middleware(['session.auth', 'permission:them_khenthuong'])
    ->name('khenthuong.create');

Route::post('/khenthuong', function (\Illuminate\Http\Request $request) {
    $conn = config('service_registry.services.hr.connection', config('database.default'));

    $payload = $request->all();
    unset($payload['_method'], $payload['_token'], $payload['_csrf_token'], $payload['created_at'], $payload['updated_at']);
    $payload = array_filter($payload, fn($v) => $v !== '' && $v !== null);

    try {
        $id = \Illuminate\Support\Facades\DB::connection($conn)
            ->table('khenthuongkyluat')
            ->insertGetId($payload, 'MaKTKL');

        return redirect()->route('khenthuong.edit', ['record' => $id])
            ->with('success', 'Thêm khen thưởng/kỷ luật thành công.');
    } catch (\Illuminate\Database\QueryException $e) {
        return back()->withInput()->withErrors(['form' => 'Không thể thêm bản ghi: ' . $e->getMessage()]);
    }
})->middleware(['session.auth', 'permission:them_khenthuong'])
    ->name('khenthuong.store');

Route::get('/khenthuong/{record}/edit', function (string $record) {
    $conn = config('service_registry.services.hr.connection', config('database.default'));
    $item = \Illuminate\Support\Facades\DB::connection($conn)
        ->table('khenthuongkyluat')
        ->where('MaKTKL', $record)
        ->first();

    abort_if($item === null, 404, 'Không tìm thấy bản ghi khen thưởng/kỷ luật');

    $recordData = (array) $item;
    $recordData['__resource_id'] = $record;

    $moduleConfig = config('laravel_resource_modules.reward-records');
    $resourceConfig = [
        'columns' => [
            ['field' => 'MaKTKL', 'type' => 'int', 'nullable' => false, 'default' => null, 'extra' => 'auto_increment', 'key' => 'PRI'],
            ['field' => 'MaNV', 'type' => 'int', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'MaLoai', 'type' => 'int', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'NgayQuyetDinh', 'type' => 'date', 'nullable' => false, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'HinhThuc', 'type' => 'varchar(150)', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'SoTien', 'type' => 'decimal(15,2)', 'nullable' => true, 'default' => '0.00', 'extra' => '', 'key' => ''],
            ['field' => 'LyDo', 'type' => 'text', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
            ['field' => 'GhiChu', 'type' => 'text', 'nullable' => true, 'default' => null, 'extra' => '', 'key' => ''],
        ],
        'primary_key' => 'MaKTKL',
    ];

    $fieldLookups = [];
    if (!empty($moduleConfig['field_lookups'])) {
        foreach ($moduleConfig['field_lookups'] as $field => $lookup) {
            $lookupConn = config("service_registry.services.{$lookup['service']}.connection", config('database.default'));
            $options = \Illuminate\Support\Facades\DB::connection($lookupConn)
                ->table($lookup['table'])
                ->select($lookup['value_col'], $lookup['label_col'])
                ->get()
                ->map(fn($row) => ['value' => $row->{$lookup['value_col']}, 'label' => $row->{$lookup['label_col']}])
                ->all();
            $fieldLookups[$field] = $options;
        }
    }

    return view('resource_modules.form', [
        'mode' => 'edit',
        'moduleKey' => 'reward-records',
        'routeKey' => 'khenthuong',
        'moduleConfig' => $moduleConfig,
        'resourceConfig' => $resourceConfig,
        'record' => $recordData,
        'recordId' => $record,
        'fieldLookups' => $fieldLookups,
    ]);
})->middleware(['session.auth', 'permission:sua_khenthuong'])
    ->name('khenthuong.edit');

Route::put('/khenthuong/{record}', function (\Illuminate\Http\Request $request, string $record) {
    $conn = config('service_registry.services.hr.connection', config('database.default'));

    $item = \Illuminate\Support\Facades\DB::connection($conn)->table('khenthuongkyluat')->where('MaKTKL', $record)->first();
    abort_if($item === null, 404, 'Không tìm thấy bản ghi khen thưởng/kỷ luật');

    $payload = $request->all();
    unset($payload['_method'], $payload['_token'], $payload['_csrf_token'], $payload['created_at'], $payload['updated_at']);
    $payload = array_filter($payload, fn($v) => $v !== '' && $v !== null);

    if (!empty($payload)) {
        \Illuminate\Support\Facades\DB::connection($conn)
            ->table('khenthuongkyluat')
            ->where('MaKTKL', $record)
            ->update($payload);
    }

    return redirect()->route('khenthuong.edit', ['record' => $record])
        ->with('success', 'Cập nhật bản ghi thành công.');
})->middleware(['session.auth', 'permission:sua_khenthuong'])
    ->name('khenthuong.update');

Route::delete('/khenthuong/{record}', function (string $record) {
    $conn = config('service_registry.services.hr.connection', config('database.default'));

    $deleted = \Illuminate\Support\Facades\DB::connection($conn)
        ->table('khenthuongkyluat')
        ->where('MaKTKL', $record)
        ->delete();

    abort_if($deleted === 0, 404, 'Không tìm thấy bản ghi khen thưởng/kỷ luật để xóa');

    return redirect()->route('khenthuong.index')
        ->with('success', 'Đã xóa bản ghi thành công.');
})->middleware(['session.auth', 'permission:xoa_khenthuong'])
    ->name('khenthuong.destroy');

Route::get('/khenthuong/{record}', function (string $record) {
    return redirect()->route('khenthuong.edit', ['record' => $record]);
})->middleware(['session.auth', 'permission:sua_khenthuong'])
    ->name('khenthuong.show.legacy');


require __DIR__ . '/web_legacy.php';
