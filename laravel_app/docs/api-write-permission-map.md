# API Write Permission Map

Generated at: 2026-06-13 10:15:07

This document is generated from `php artisan route:list --json`.
It lists API write routes protected by `ApiWritePermissionMiddleware`.

| Method | Endpoint | Permission | Action |
| --- | --- | --- | --- |
| POST | /api/attendance | them_chamcong | App\Http\Controllers\Api\AttendanceController@store |
| DELETE | /api/attendance/{id} | xoa_chamcong | App\Http\Controllers\Api\AttendanceController@destroy |
| PUT | /api/attendance/{id} | sua_chamcong | App\Http\Controllers\Api\AttendanceController@update |
| POST | /api/attendance/paginate | (inferred by middleware/module config) | App\Http\Controllers\Api\AttendanceController@paginate |
| PATCH | /api/biz/accounts/{id}/password | sua_taikhoan | App\Http\Controllers\Api\AccountBizController@updatePassword |
| PATCH | /api/biz/accounts/{id}/username | sua_taikhoan | App\Http\Controllers\Api\AccountBizController@updateUsername |
| POST | /api/biz/accounts/reset-token | sua_taikhoan | App\Http\Controllers\Api\AccountBizController@createResetToken |
| POST | /api/biz/accounts/reset-token/{id}/used | sua_taikhoan | App\Http\Controllers\Api\AccountBizController@markResetTokenUsed |
| POST | /api/biz/accounts/sessions/register | (inferred by middleware/module config) | App\Http\Controllers\Api\AccountBizController@registerSession |
| POST | /api/biz/accounts/sessions/revoke | (inferred by middleware/module config) | App\Http\Controllers\Api\AccountBizController@revokeCurrentSession |
| POST | /api/biz/accounts/sessions/revoke-others | (inferred by middleware/module config) | App\Http\Controllers\Api\AccountBizController@revokeOtherSessions |
| POST | /api/biz/accounts/sessions/touch | (inferred by middleware/module config) | App\Http\Controllers\Api\AccountBizController@touchSession |
| POST | /api/biz/attendance | them_chamcong | App\Http\Controllers\Api\AttendanceBizController@store |
| DELETE | /api/biz/attendance/{id} | xoa_chamcong | App\Http\Controllers\Api\AttendanceBizController@destroy |
| PUT | /api/biz/attendance/{id} | sua_chamcong | App\Http\Controllers\Api\AttendanceBizController@update |
| POST | /api/biz/attendance/paginate | (inferred by middleware/module config) | App\Http\Controllers\Api\AttendanceBizController@paginate |
| POST | /api/biz/chatbot/drafts | su_dung_chatbot | App\Http\Controllers\Api\ChatbotBizController@createDraft |
| PATCH | /api/biz/chatbot/drafts/{id}/status | su_dung_chatbot | App\Http\Controllers\Api\ChatbotBizController@updateDraftStatus |
| POST | /api/biz/chatbot/execute-draft | su_dung_chatbot | App\Http\Controllers\Api\ChatbotBizController@executeDraft |
| POST | /api/biz/chatbot/messages | su_dung_chatbot | App\Http\Controllers\Api\ChatbotBizController@logMessage |
| POST | /api/biz/chatbot/paginate | (inferred by middleware/module config) | App\Http\Controllers\Api\ChatbotBizController@paginate |
| POST | /api/biz/chatbot/sessions/upsert | su_dung_chatbot | App\Http\Controllers\Api\ChatbotBizController@upsertSession |
| POST | /api/biz/contracts/{id}/renew | sua_hopdong | App\Http\Controllers\Api\ContractBizController@renew |
| POST | /api/biz/contracts/{id}/terminate | sua_hopdong | App\Http\Controllers\Api\ContractBizController@terminate |
| POST | /api/biz/dashboard/charts | (inferred by middleware/module config) | App\Http\Controllers\Api\DashboardBizController@charts |
| POST | /api/biz/dashboard/notifications/mark-read | (inferred by middleware/module config) | App\Http\Controllers\Api\DashboardBizController@markNotificationsRead |
| POST | /api/biz/departments/paginate | (inferred by middleware/module config) | App\Http\Controllers\Api\DepartmentBizController@paginate |
| POST | /api/biz/employee-profiles/requests/{id}/resolve | sua_nhanvien | App\Http\Controllers\Api\EmployeeProfileBizController@resolveRequest |
| POST | /api/biz/employees | them_nhanvien | App\Http\Controllers\Api\EmployeeBizController@store |
| DELETE | /api/biz/employees/{id} | xoa_nhanvien | App\Http\Controllers\Api\EmployeeBizController@destroy |
| PUT | /api/biz/employees/{id} | sua_nhanvien | App\Http\Controllers\Api\EmployeeBizController@update |
| POST | /api/biz/employees/paginate | (inferred by middleware/module config) | App\Http\Controllers\Api\EmployeeBizController@paginate |
| POST | /api/biz/insurances/{id}/deactivate | sua_baohiem | App\Http\Controllers\Api\InsuranceBizController@deactivate |
| POST | /api/biz/leave-requests | them_nghiphep | App\Http\Controllers\Api\LeaveRequestBizController@create |
| POST | /api/biz/leave-requests/{id}/approve | sua_nghiphep | App\Http\Controllers\Api\LeaveRequestBizController@approve |
| POST | /api/biz/leave-requests/{id}/reject | sua_nghiphep | App\Http\Controllers\Api\LeaveRequestBizController@reject |
| POST | /api/biz/payroll | tinh_luong_thang | App\Http\Controllers\Api\PayrollBizController@store |
| PUT | /api/biz/payroll/{id} | mo_chot_luong | App\Http\Controllers\Api\PayrollBizController@update |
| POST | /api/biz/payroll/{id}/lock | chot_luong | App\Http\Controllers\Api\PayrollBizController@lock |
| POST | /api/biz/payroll/{id}/unlock | mo_chot_luong | App\Http\Controllers\Api\PayrollBizController@unlock |
| POST | /api/biz/payroll/paginate | (inferred by middleware/module config) | App\Http\Controllers\Api\PayrollBizController@paginate |
| POST | /api/biz/payroll/run-monthly | tinh_luong_thang | App\Http\Controllers\Api\PayrollBizController@runMonthly |
| POST | /api/biz/recruitment | them_dot_tuyen | App\Http\Controllers\Api\RecruitmentBizController@store |
| POST | /api/biz/recruitment/{campaignId}/applications | them_ho_so | App\Http\Controllers\Api\RecruitmentBizController@attachCandidate |
| POST | /api/biz/recruitment/{campaignId}/applications/paginate | (inferred by middleware/module config) | App\Http\Controllers\Api\RecruitmentBizController@paginateApplications |
| DELETE | /api/biz/recruitment/{id} | xoa_dot_tuyen | App\Http\Controllers\Api\RecruitmentBizController@destroy |
| PUT | /api/biz/recruitment/{id} | them_dot_tuyen | App\Http\Controllers\Api\RecruitmentBizController@update |
| POST | /api/biz/recruitment/applications/{id}/interviews | them_lich_phong_van | App\Http\Controllers\Api\RecruitmentBizController@storeInterview |
| PUT | /api/biz/recruitment/applications/{id}/kanban | capnhat_trangthai | App\Http\Controllers\Api\RecruitmentBizController@updateKanban |
| PUT | /api/biz/recruitment/applications/{id}/status | capnhat_trangthai | App\Http\Controllers\Api\RecruitmentBizController@updateApplicationStatus |
| POST | /api/biz/recruitment/candidates | them_ung_vien | App\Http\Controllers\Api\RecruitmentBizController@storeCandidate |
| POST | /api/biz/recruitment/candidates/paginate | (inferred by middleware/module config) | App\Http\Controllers\Api\RecruitmentBizController@paginateCandidates |
| POST | /api/biz/recruitment/interviews/{id}/reviews | them_danh_gia | App\Http\Controllers\Api\RecruitmentBizController@storeReview |
| POST | /api/biz/recruitment/paginate | (inferred by middleware/module config) | App\Http\Controllers\Api\RecruitmentBizController@paginate |
| POST | /api/biz/reports | them_baocao | App\Http\Controllers\Api\ReportBizController@store |
| DELETE | /api/biz/reports/{id} | xoa_baocao | App\Http\Controllers\Api\ReportBizController@destroy |
| PUT | /api/biz/reports/{id} | sua_baocao | App\Http\Controllers\Api\ReportBizController@update |
| POST | /api/biz/reports/paginate | (inferred by middleware/module config) | App\Http\Controllers\Api\ReportBizController@paginate |
| POST | /api/biz/role-permissions/assign | sua_taikhoan | App\Http\Controllers\Api\RolePermissionBizController@assignAccountRole |
| POST | /api/biz/role-permissions/revoke | sua_taikhoan | App\Http\Controllers\Api\RolePermissionBizController@revokeAccountRole |
| POST | /api/biz/role-permissions/roles | sua_taikhoan | App\Http\Controllers\Api\RolePermissionBizController@storeRole |
| DELETE | /api/biz/role-permissions/roles/{id} | sua_taikhoan | App\Http\Controllers\Api\RolePermissionBizController@destroyRole |
| PUT | /api/biz/role-permissions/roles/{id}/permissions | sua_taikhoan | App\Http\Controllers\Api\RolePermissionBizController@updateRolePermissions |
| POST | /api/biz/role-permissions/roles/{id}/restore-defaults | sua_taikhoan | App\Http\Controllers\Api\RolePermissionBizController@restoreDefaultPermissions |
| POST | /api/biz/system-health/run-checks | (inferred by middleware/module config) | App\Http\Controllers\Api\SystemHealthBizController@runChecks |
| POST | /api/biz/training | them_khoa_dao_tao | App\Http\Controllers\Api\TrainingBizController@store |
| DELETE | /api/biz/training/{id} | xoa_khoa_dao_tao | App\Http\Controllers\Api\TrainingBizController@destroy |
| PUT | /api/biz/training/{id} | them_khoa_dao_tao | App\Http\Controllers\Api\TrainingBizController@update |
| POST | /api/biz/training/{id}/participants | them_tham_gia_dao_tao | App\Http\Controllers\Api\TrainingBizController@addParticipant |
| POST | /api/biz/training/paginate | (inferred by middleware/module config) | App\Http\Controllers\Api\TrainingBizController@paginate |
| PUT | /api/biz/training/participants/{participantId} | capnhat_ketqua_dao_tao | App\Http\Controllers\Api\TrainingBizController@updateParticipantResult |
| PATCH | /api/hr/accounts/{id}/password | sua_taikhoan | App\Http\Controllers\Api\HRController@updateAccountPassword |
| PATCH | /api/hr/accounts/{id}/username | sua_taikhoan | App\Http\Controllers\Api\HRController@updateAccountUsername |
| POST | /api/hr/employees | them_nhanvien | App\Http\Controllers\Api\HRController@storeEmployee |
| DELETE | /api/hr/employees/{id} | xoa_nhanvien | App\Http\Controllers\Api\HRController@destroyEmployee |
| PUT | /api/hr/employees/{id} | sua_nhanvien | App\Http\Controllers\Api\HRController@updateEmployee |
| DELETE | /api/hr/role-permissions/{role_id}/{feature_id} | sua_phanquyen | App\Http\Controllers\Api\HRController@revokeRolePermission |
| POST | /api/hr/role-permissions/{role_id}/{feature_id} | sua_phanquyen | App\Http\Controllers\Api\HRController@assignRolePermission |
| POST | /api/modules/{module} | (inferred by middleware/module config) | App\Http\Controllers\Api\ModuleResourceApiController@store |
| DELETE | /api/modules/{module}/{id} | (inferred by middleware/module config) | App\Http\Controllers\Api\ModuleResourceApiController@destroy |
| PUT | /api/modules/{module}/{id} | (inferred by middleware/module config) | App\Http\Controllers\Api\ModuleResourceApiController@update |
| POST | /api/payroll | tinh_luong_thang | App\Http\Controllers\Api\PayrollController@store |
| DELETE | /api/payroll/{id} | mo_chot_luong | App\Http\Controllers\Api\PayrollController@destroy |
| PUT | /api/payroll/{id} | mo_chot_luong | App\Http\Controllers\Api\PayrollController@update |
| POST | /api/payroll/{id}/lock | chot_luong | App\Http\Controllers\Api\PayrollController@lock |
| POST | /api/payroll/{id}/unlock | mo_chot_luong | App\Http\Controllers\Api\PayrollController@unlock |
| POST | /api/payroll/paginate | (inferred by middleware/module config) | App\Http\Controllers\Api\PayrollController@paginate |
| POST | /api/payroll/run-monthly | tinh_luong_thang | App\Http\Controllers\Api\PayrollController@runMonthly |
| PUT | /api/recruitment/applications/{id}/status | capnhat_trangthai | App\Http\Controllers\Api\RecruitmentController@updateApplicationStatus |
| POST | /api/recruitment/campaigns | them_dot_tuyen | App\Http\Controllers\Api\RecruitmentController@storeCampaign |
| DELETE | /api/recruitment/campaigns/{id} | xoa_dot_tuyen | App\Http\Controllers\Api\RecruitmentController@destroyCampaign |
| PUT | /api/recruitment/campaigns/{id} | them_dot_tuyen | App\Http\Controllers\Api\RecruitmentController@updateCampaign |
| POST | /api/recruitment/candidates | them_ung_vien | App\Http\Controllers\Api\RecruitmentController@storeCandidate |
| DELETE | /api/recruitment/candidates/{id} | them_ung_vien | App\Http\Controllers\Api\RecruitmentController@destroyCandidate |
| PUT | /api/recruitment/candidates/{id} | them_ung_vien | App\Http\Controllers\Api\RecruitmentController@updateCandidate |
| POST | /api/reports | them_baocao | App\Http\Controllers\Api\ReportController@store |
| DELETE | /api/reports/{id} | xoa_baocao | App\Http\Controllers\Api\ReportController@destroy |
| PUT | /api/reports/{id} | sua_baocao | App\Http\Controllers\Api\ReportController@update |
| POST | /api/training/courses | them_khoa_dao_tao | App\Http\Controllers\Api\TrainingController@storeCourse |
| DELETE | /api/training/courses/{id} | xoa_khoa_dao_tao | App\Http\Controllers\Api\TrainingController@destroyCourse |
| PUT | /api/training/courses/{id} | them_khoa_dao_tao | App\Http\Controllers\Api\TrainingController@updateCourse |
| POST | /api/training/courses/{id}/participants | them_tham_gia_dao_tao | App\Http\Controllers\Api\TrainingController@addParticipant |
| DELETE | /api/training/participants/{id} | them_tham_gia_dao_tao | App\Http\Controllers\Api\TrainingController@removeParticipant |
| PUT | /api/training/participants/{id} | capnhat_ketqua_dao_tao | App\Http\Controllers\Api\TrainingController@updateParticipant |

Total protected write routes: 104
