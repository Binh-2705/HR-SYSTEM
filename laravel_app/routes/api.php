<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\AccountBizController;
use App\Http\Controllers\Api\AuditLogBizController;
use App\Http\Controllers\Api\AttendanceBizController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\ChatbotBizController;
use App\Http\Controllers\Api\ContractBizController;
use App\Http\Controllers\Api\DashboardBizController;
use App\Http\Controllers\Api\DepartmentBizController;
use App\Http\Controllers\Api\EmployeeBizController;
use App\Http\Controllers\Api\EmployeeProfileBizController;
use App\Http\Controllers\Api\InsuranceBizController;
use App\Http\Controllers\Api\LeaveRequestBizController;
use App\Http\Controllers\Api\ModuleResourceApiController;
use App\Http\Controllers\Api\PayrollBizController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PermissionBizController;
use App\Http\Controllers\Api\RecruitmentBizController;
use App\Http\Controllers\Api\ReportBizController;
use App\Http\Controllers\Api\RolePermissionBizController;
use App\Http\Controllers\Api\SearchBizController;
use App\Http\Controllers\Api\SystemHealthBizController;
use App\Http\Controllers\Api\TrainingBizController;
use App\Http\Controllers\Api\HRController;
use App\Http\Controllers\Api\RecruitmentController;
use App\Http\Controllers\Api\TrainingController;
use App\Http\Controllers\Api\ReportController;


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('auth/token', [AuthTokenController::class, 'issue'])
    ->middleware('throttle:api-auth');

Route::middleware('auth:sanctum')->post('auth/token/revoke', [AuthTokenController::class, 'revoke']);

// ─── DEDICATED SERVICE ROUTES (new structure) ───────────────────────────────

// HR Service
Route::prefix('hr')->middleware(['api.token', 'api.write.permission'])->group(function () {
    // Employees
    Route::get('employees', [HRController::class, 'indexEmployees']);
    Route::post('employees', [HRController::class, 'storeEmployee'])->middleware('api.write.permission:them_nhanvien');
    Route::get('employees/{id}', [HRController::class, 'showEmployee']);
    Route::put('employees/{id}', [HRController::class, 'updateEmployee'])->middleware('api.write.permission:sua_nhanvien');
    Route::delete('employees/{id}', [HRController::class, 'destroyEmployee'])->middleware('api.write.permission:xoa_nhanvien');

    // Accounts
    Route::get('accounts/{id}', [HRController::class, 'showAccount']);
    Route::get('accounts/by-username/{username}', [HRController::class, 'showAccountByUsername']);
    Route::patch('accounts/{id}/username', [HRController::class, 'updateAccountUsername'])->middleware('api.write.permission:sua_taikhoan');
    Route::patch('accounts/{id}/password', [HRController::class, 'updateAccountPassword'])->middleware('api.write.permission:sua_taikhoan');

    // Departments
    Route::get('departments', [HRController::class, 'indexDepartments']);
    Route::get('departments/{id}', [HRController::class, 'showDepartment']);

    // Roles & Permissions
    Route::get('roles', [HRController::class, 'indexRoles']);
    Route::get('roles/{id}', [HRController::class, 'showRole']);
    Route::get('features', [HRController::class, 'indexFeatures']);
    Route::get('role-permissions', [HRController::class, 'indexRolePermissions']);
    Route::post('role-permissions/{role_id}/{feature_id}', [HRController::class, 'assignRolePermission'])->middleware('api.write.permission:sua_phanquyen');
    Route::delete('role-permissions/{role_id}/{feature_id}', [HRController::class, 'revokeRolePermission'])->middleware('api.write.permission:sua_phanquyen');

    // Account-Roles
    Route::get('account-roles', [HRController::class, 'indexAccountRoles']);

    // Options
    Route::get('options', [HRController::class, 'options']);
});

// Attendance Service
Route::prefix('attendance')->middleware(['api.token', 'api.write.permission'])->group(function () {
    Route::get('/', [AttendanceController::class, 'index']);
    Route::post('/', [AttendanceController::class, 'store'])->middleware('api.write.permission:them_chamcong');
    Route::get('{id}', [AttendanceController::class, 'show']);
    Route::put('{id}', [AttendanceController::class, 'update'])->middleware('api.write.permission:sua_chamcong');
    Route::delete('{id}', [AttendanceController::class, 'destroy'])->middleware('api.write.permission:xoa_chamcong');

    // Business logic
    Route::get('employees/options', [AttendanceController::class, 'employeeOptions']);
    Route::post('paginate', [AttendanceController::class, 'paginate']);
    Route::get('worked-days', [AttendanceController::class, 'workedDays']);
    Route::get('export-rows', [AttendanceController::class, 'exportRows']);
    Route::get('monthly-matrix', [AttendanceController::class, 'monthlyMatrix']);
});

// Payroll Service
Route::prefix('payroll')->middleware(['api.token', 'api.write.permission'])->group(function () {
    Route::get('/', [PayrollController::class, 'index']);
    Route::post('/', [PayrollController::class, 'store'])->middleware('api.write.permission:tinh_luong_thang');
    Route::get('{id}', [PayrollController::class, 'show']);
    Route::put('{id}', [PayrollController::class, 'update'])->middleware('api.write.permission:mo_chot_luong');
    Route::delete('{id}', [PayrollController::class, 'destroy'])->middleware('api.write.permission:mo_chot_luong');

    // Business logic
    Route::get('employees/options', [PayrollController::class, 'employeeOptions']);
    Route::post('paginate', [PayrollController::class, 'paginate']);
    Route::post('run-monthly', [PayrollController::class, 'runMonthly'])->middleware('api.write.permission:tinh_luong_thang');
    Route::get('salary-components', [PayrollController::class, 'salaryComponents']);
    Route::get('export', [PayrollController::class, 'export']);
    Route::post('{id}/lock', [PayrollController::class, 'lock'])->middleware('api.write.permission:chot_luong');
    Route::post('{id}/unlock', [PayrollController::class, 'unlock'])->middleware('api.write.permission:mo_chot_luong');
});

// Recruitment Service
Route::prefix('recruitment')->middleware(['api.token', 'api.write.permission'])->group(function () {
    // Campaigns
    Route::get('campaigns', [RecruitmentController::class, 'indexCampaigns']);
    Route::post('campaigns', [RecruitmentController::class, 'storeCampaign'])->middleware('api.write.permission:them_dot_tuyen');
    Route::get('campaigns/{id}', [RecruitmentController::class, 'showCampaign']);
    Route::put('campaigns/{id}', [RecruitmentController::class, 'updateCampaign'])->middleware('api.write.permission:them_dot_tuyen');
    Route::delete('campaigns/{id}', [RecruitmentController::class, 'destroyCampaign'])->middleware('api.write.permission:xoa_dot_tuyen');

    // Candidates
    Route::get('candidates', [RecruitmentController::class, 'indexCandidates']);
    Route::post('candidates', [RecruitmentController::class, 'storeCandidate'])->middleware('api.write.permission:them_ung_vien');
    Route::get('candidates/{id}', [RecruitmentController::class, 'showCandidate']);
    Route::put('candidates/{id}', [RecruitmentController::class, 'updateCandidate'])->middleware('api.write.permission:them_ung_vien');
    Route::delete('candidates/{id}', [RecruitmentController::class, 'destroyCandidate'])->middleware('api.write.permission:them_ung_vien');

    // Applications
    Route::get('applications', [RecruitmentController::class, 'indexApplications']);
    Route::get('applications/{id}', [RecruitmentController::class, 'showApplication']);
    Route::put('applications/{id}/status', [RecruitmentController::class, 'updateApplicationStatus'])->middleware('api.write.permission:capnhat_trangthai');

    // Options
    Route::get('campaign-options', [RecruitmentController::class, 'campaignOptions']);
});

// Training Service
Route::prefix('training')->middleware(['api.token', 'api.write.permission'])->group(function () {
    // Courses
    Route::get('courses', [TrainingController::class, 'indexCourses']);
    Route::post('courses', [TrainingController::class, 'storeCourse'])->middleware('api.write.permission:them_khoa_dao_tao');
    Route::get('courses/{id}', [TrainingController::class, 'showCourse']);
    Route::put('courses/{id}', [TrainingController::class, 'updateCourse'])->middleware('api.write.permission:them_khoa_dao_tao');
    Route::delete('courses/{id}', [TrainingController::class, 'destroyCourse'])->middleware('api.write.permission:xoa_khoa_dao_tao');

    // Participants
    Route::get('courses/{id}/participants', [TrainingController::class, 'indexParticipants']);
    Route::post('courses/{id}/participants', [TrainingController::class, 'addParticipant'])->middleware('api.write.permission:them_tham_gia_dao_tao');
    Route::put('participants/{id}', [TrainingController::class, 'updateParticipant'])->middleware('api.write.permission:capnhat_ketqua_dao_tao');
    Route::delete('participants/{id}', [TrainingController::class, 'removeParticipant'])->middleware('api.write.permission:them_tham_gia_dao_tao');
});

// Reporting Service
Route::prefix('reports')->middleware(['api.token', 'api.write.permission'])->group(function () {
    Route::get('/', [ReportController::class, 'index']);
    Route::post('/', [ReportController::class, 'store'])->middleware('api.write.permission:them_baocao');
    Route::get('{id}', [ReportController::class, 'show']);
    Route::put('{id}', [ReportController::class, 'update'])->middleware('api.write.permission:sua_baocao');
    Route::delete('{id}', [ReportController::class, 'destroy'])->middleware('api.write.permission:xoa_baocao');
    Route::get('export', [ReportController::class, 'export']);
});

// Resource Modules API (module-specific endpoints)
Route::prefix('modules')->middleware(['api.token', 'api.write.permission'])->group(function () {
    Route::get('{module}/meta', [ModuleResourceApiController::class, 'meta']);
    Route::get('{module}/export', [ModuleResourceApiController::class, 'export']);
    Route::get('{module}', [ModuleResourceApiController::class, 'index']);
    Route::post('{module}', [ModuleResourceApiController::class, 'store']);
    Route::get('{module}/{id}', [ModuleResourceApiController::class, 'show']);
    Route::put('{module}/{id}', [ModuleResourceApiController::class, 'update']);
    Route::delete('{module}/{id}', [ModuleResourceApiController::class, 'destroy']);
});

// ─── Internal Business API (/api/biz/*) ──────────────────────────────────────
Route::prefix('biz')->middleware(['api.token', 'api.write.permission'])->group(function () {

    // Dashboard
    Route::get('dashboard/metrics',                        [DashboardBizController::class, 'metrics']);
    Route::get('dashboard/recent-activity',                [DashboardBizController::class, 'recentActivity']);
    Route::post('dashboard/charts',                        [DashboardBizController::class, 'charts']);
    Route::post('dashboard/notifications/mark-read',       [DashboardBizController::class, 'markNotificationsRead']);

    // Employees
    Route::post('employees/paginate',         [EmployeeBizController::class, 'paginate']);
    Route::get('employees/options',           [EmployeeBizController::class, 'options']);
    Route::get('employees/salary-grades',     [EmployeeBizController::class, 'salaryGrades']);
    Route::post('employees',                  [EmployeeBizController::class, 'store'])->middleware('api.write.permission:them_nhanvien');
    Route::get('employees/{id}',              [EmployeeBizController::class, 'show']);
    Route::put('employees/{id}',              [EmployeeBizController::class, 'update'])->middleware('api.write.permission:sua_nhanvien');
    Route::delete('employees/{id}',           [EmployeeBizController::class, 'destroy'])->middleware('api.write.permission:xoa_nhanvien');

    // Departments
    Route::post('departments/paginate', [DepartmentBizController::class, 'paginate']);
    Route::get('departments/{id}',      [DepartmentBizController::class, 'show']);

    // Attendance
    Route::post('attendance/paginate',         [AttendanceBizController::class, 'paginate']);
    Route::get('attendance/employee-options',  [AttendanceBizController::class, 'employeeOptions']);
    Route::get('attendance/export-rows',       [AttendanceBizController::class, 'exportRows']);
    Route::get('attendance/worked-days',       [AttendanceBizController::class, 'workedDays']);
    Route::get('attendance/monthly-matrix',    [AttendanceBizController::class, 'monthlyMatrix']);
    Route::post('attendance',                  [AttendanceBizController::class, 'store'])->middleware('api.write.permission:them_chamcong');
    Route::get('attendance/{id}',              [AttendanceBizController::class, 'show']);
    Route::put('attendance/{id}',              [AttendanceBizController::class, 'update'])->middleware('api.write.permission:sua_chamcong');
    Route::delete('attendance/{id}',           [AttendanceBizController::class, 'destroy'])->middleware('api.write.permission:xoa_chamcong');

    // Payroll
    Route::post('payroll/paginate',         [PayrollBizController::class, 'paginate']);
    Route::get('payroll/employee-options',  [PayrollBizController::class, 'employeeOptions']);
    Route::get('payroll/salary-components', [PayrollBizController::class, 'salaryComponents']);
    Route::post('payroll/run-monthly',      [PayrollBizController::class, 'runMonthly'])->middleware('api.write.permission:tinh_luong_thang');
    Route::get('payroll/export',            [PayrollBizController::class, 'export']);
    Route::post('payroll',                  [PayrollBizController::class, 'store'])->middleware('api.write.permission:tinh_luong_thang');
    Route::get('payroll/{id}',              [PayrollBizController::class, 'show']);
    Route::put('payroll/{id}',              [PayrollBizController::class, 'update'])->middleware('api.write.permission:mo_chot_luong');
    Route::post('payroll/{id}/lock',        [PayrollBizController::class, 'lock'])->middleware('api.write.permission:chot_luong');
    Route::post('payroll/{id}/unlock',      [PayrollBizController::class, 'unlock'])->middleware('api.write.permission:mo_chot_luong');

    // Recruitment – campaigns
    Route::post('recruitment/paginate',                            [RecruitmentBizController::class, 'paginate']);
    Route::get('recruitment/campaign-options',                     [RecruitmentBizController::class, 'campaignOptions']);
    Route::post('recruitment',                                     [RecruitmentBizController::class, 'store'])->middleware('api.write.permission:them_dot_tuyen');
    Route::get('recruitment/{id}',                                 [RecruitmentBizController::class, 'show']);
    Route::put('recruitment/{id}',                                 [RecruitmentBizController::class, 'update'])->middleware('api.write.permission:them_dot_tuyen');
    Route::delete('recruitment/{id}',                              [RecruitmentBizController::class, 'destroy'])->middleware('api.write.permission:xoa_dot_tuyen');
    // Candidates
    Route::post('recruitment/candidates/paginate',                 [RecruitmentBizController::class, 'paginateCandidates']);
    Route::post('recruitment/candidates',                          [RecruitmentBizController::class, 'storeCandidate'])->middleware('api.write.permission:them_ung_vien');
    Route::get('recruitment/candidates/{id}',                      [RecruitmentBizController::class, 'showCandidate']);
    // Applications
    Route::post('recruitment/{campaignId}/applications/paginate',  [RecruitmentBizController::class, 'paginateApplications']);
    Route::post('recruitment/{campaignId}/applications',           [RecruitmentBizController::class, 'attachCandidate'])->middleware('api.write.permission:them_ho_so');
    Route::get('recruitment/applications/{id}',                    [RecruitmentBizController::class, 'showApplication']);
    Route::put('recruitment/applications/{id}/status',             [RecruitmentBizController::class, 'updateApplicationStatus'])->middleware('api.write.permission:capnhat_trangthai');
    Route::put('recruitment/applications/{id}/kanban',             [RecruitmentBizController::class, 'updateKanban'])->middleware('api.write.permission:capnhat_trangthai');
    // Interviews & reviews
    Route::get('recruitment/applications/{id}/interviews',         [RecruitmentBizController::class, 'listInterviews']);
    Route::get('recruitment/applications/{id}/reviews',            [RecruitmentBizController::class, 'listReviews']);
    Route::post('recruitment/applications/{id}/interviews',        [RecruitmentBizController::class, 'storeInterview'])->middleware('api.write.permission:them_lich_phong_van');
    Route::post('recruitment/interviews/{id}/reviews',             [RecruitmentBizController::class, 'storeReview'])->middleware('api.write.permission:them_danh_gia');

    // Training
    Route::post('training/paginate',                     [TrainingBizController::class, 'paginate']);
    Route::post('training',                              [TrainingBizController::class, 'store'])->middleware('api.write.permission:them_khoa_dao_tao');
    Route::get('training/{id}',                          [TrainingBizController::class, 'show']);
    Route::put('training/{id}',                          [TrainingBizController::class, 'update'])->middleware('api.write.permission:them_khoa_dao_tao');
    Route::delete('training/{id}',                       [TrainingBizController::class, 'destroy'])->middleware('api.write.permission:xoa_khoa_dao_tao');
    Route::get('training/{id}/participants-page',        [TrainingBizController::class, 'participantsPageData']);
    Route::post('training/{id}/participants',            [TrainingBizController::class, 'addParticipant'])->middleware('api.write.permission:them_tham_gia_dao_tao');
    Route::put('training/participants/{participantId}',  [TrainingBizController::class, 'updateParticipantResult'])->middleware('api.write.permission:capnhat_ketqua_dao_tao');

    // Reports
    Route::post('reports/paginate', [ReportBizController::class, 'paginate']);
    Route::get('reports/export',    [ReportBizController::class, 'export']);
    Route::post('reports',          [ReportBizController::class, 'store'])->middleware('api.write.permission:them_baocao');
    Route::get('reports/{id}',      [ReportBizController::class, 'show']);
    Route::put('reports/{id}',      [ReportBizController::class, 'update'])->middleware('api.write.permission:sua_baocao');
    Route::delete('reports/{id}',   [ReportBizController::class, 'destroy'])->middleware('api.write.permission:xoa_baocao');

    // Contracts
    Route::get('contracts/{id}',             [ContractBizController::class, 'show']);
    Route::get('contracts/{id}/salary-history', [ContractBizController::class, 'salaryHistory']);
    Route::post('contracts/{id}/renew',      [ContractBizController::class, 'renew'])->middleware('api.write.permission:sua_hopdong');
    Route::post('contracts/{id}/terminate',  [ContractBizController::class, 'terminate'])->middleware('api.write.permission:sua_hopdong');

    // Employee profiles
    Route::get('employee-profiles/pending-requests',         [EmployeeProfileBizController::class, 'pendingRequests']);
    Route::post('employee-profiles/requests/{id}/resolve',   [EmployeeProfileBizController::class, 'resolveRequest'])->middleware('api.write.permission:sua_nhanvien');
    Route::get('employee-profiles/{id}',                     [EmployeeProfileBizController::class, 'show']);
    Route::get('employee-profiles/employee/{eid}/info',      [EmployeeProfileBizController::class, 'employeeInfo']);

    // Accounts
    Route::get('accounts/by-username',          [AccountBizController::class, 'showByUsername']);
    Route::get('accounts/check-username',        [AccountBizController::class, 'checkUsernameAvailable']);
    Route::get('accounts/employee-for-account',  [AccountBizController::class, 'findEmployeeForAccount']);
    Route::get('accounts/sessions/is-revoked',   [AccountBizController::class, 'isSessionRevoked']);
    Route::get('accounts/{id}',                  [AccountBizController::class, 'show']);
    Route::patch('accounts/{id}/username',       [AccountBizController::class, 'updateUsername'])->middleware('api.write.permission:sua_taikhoan');
    Route::patch('accounts/{id}/password',       [AccountBizController::class, 'updatePassword'])->middleware('api.write.permission:sua_taikhoan');
    Route::get('accounts/{id}/sessions',         [AccountBizController::class, 'listSessions']);
    // Session audit
    Route::post('accounts/sessions/register',    [AccountBizController::class, 'registerSession']);
    Route::post('accounts/sessions/touch',       [AccountBizController::class, 'touchSession']);
    Route::post('accounts/sessions/revoke-others', [AccountBizController::class, 'revokeOtherSessions']);
    Route::post('accounts/sessions/revoke',      [AccountBizController::class, 'revokeCurrentSession']);
    // Password reset
    Route::post('accounts/reset-token',          [AccountBizController::class, 'createResetToken'])->middleware('api.write.permission:sua_taikhoan');
    Route::get('accounts/reset-token/find',      [AccountBizController::class, 'findValidResetToken']);
    Route::post('accounts/reset-token/{id}/used', [AccountBizController::class, 'markResetTokenUsed'])->middleware('api.write.permission:sua_taikhoan');

    // Role permissions
    Route::get('role-permissions',                       [RolePermissionBizController::class, 'indexData']);
    Route::get('role-permissions/accounts/{id}',         [RolePermissionBizController::class, 'accountDetail']);
    Route::get('role-permissions/roles',                 [RolePermissionBizController::class, 'listRoles']);
    Route::post('role-permissions/roles',                [RolePermissionBizController::class, 'storeRole'])->middleware('api.write.permission:sua_taikhoan');
    Route::delete('role-permissions/roles/{id}',         [RolePermissionBizController::class, 'destroyRole'])->middleware('api.write.permission:sua_taikhoan');
    Route::put('role-permissions/roles/{id}/permissions', [RolePermissionBizController::class, 'updateRolePermissions'])->middleware('api.write.permission:sua_taikhoan');
    Route::post('role-permissions/assign',               [RolePermissionBizController::class, 'assignAccountRole'])->middleware('api.write.permission:sua_taikhoan');
    Route::post('role-permissions/revoke',               [RolePermissionBizController::class, 'revokeAccountRole'])->middleware('api.write.permission:sua_taikhoan');
    Route::post('role-permissions/roles/{id}/restore-defaults', [RolePermissionBizController::class, 'restoreDefaultPermissions'])->middleware('api.write.permission:sua_taikhoan');

    // Permissions
    Route::get('permissions', [PermissionBizController::class, 'byAccount']);

    // Chatbot
    Route::post('chatbot/paginate',               [ChatbotBizController::class, 'paginate']);
    Route::post('chatbot/sessions/upsert',        [ChatbotBizController::class, 'upsertSession'])->middleware('api.write.permission:su_dung_chatbot');
    Route::post('chatbot/messages',               [ChatbotBizController::class, 'logMessage'])->middleware('api.write.permission:su_dung_chatbot');
    Route::post('chatbot/drafts',                 [ChatbotBizController::class, 'createDraft'])->middleware('api.write.permission:su_dung_chatbot');
    Route::get('chatbot/drafts/pending',          [ChatbotBizController::class, 'getPendingDraft']);
    Route::patch('chatbot/drafts/{id}/status',    [ChatbotBizController::class, 'updateDraftStatus'])->middleware('api.write.permission:su_dung_chatbot');
    Route::post('chatbot/execute-draft',          [ChatbotBizController::class, 'executeDraft'])->middleware('api.write.permission:su_dung_chatbot');
    Route::get('chatbot/{id}',                    [ChatbotBizController::class, 'show']);

    // Leave requests
    Route::post('leave-requests/{id}/approve',    [LeaveRequestBizController::class, 'approve'])->middleware('api.write.permission:sua_nghiphep');
    Route::post('leave-requests/{id}/reject',     [LeaveRequestBizController::class, 'reject'])->middleware('api.write.permission:sua_nghiphep');
    Route::get('leave-requests/{id}/approval-progress', [LeaveRequestBizController::class, 'approvalProgress']);
    Route::post('leave-requests',                 [LeaveRequestBizController::class, 'create'])->middleware('api.write.permission:them_nghiphep');

    // Insurances
    Route::post('insurances/{id}/deactivate',     [InsuranceBizController::class, 'deactivate'])->middleware('api.write.permission:sua_baohiem');

    // Search
    Route::get('search', [SearchBizController::class, 'index']);

    // Audit log
    Route::get('audit-log', [AuditLogBizController::class, 'index']);

    // System health
    Route::get('system-health/status', [SystemHealthBizController::class, 'status']);
    Route::post('system-health/run-checks', [SystemHealthBizController::class, 'runChecks']);
});
