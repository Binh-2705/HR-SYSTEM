<?php

use App\Http\Controllers\Admin\AccountAdminController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\DepartmentController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\PayrollController;
use App\Http\Controllers\Hr\RecruitmentController;
use App\Http\Controllers\Hr\TrainingController;
use App\Http\Controllers\Report\AuditLogExportController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\System\SystemHealthController;
use Illuminate\Support\Facades\Route;
Route::get('/nhanvien', [EmployeeController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_nhanvien'])
    ->name('nhanvien.index');
Route::get('/nhanvien/bacluong-theo-ngach', [EmployeeController::class, 'salaryGradesByBand'])
    ->middleware(['session.auth', 'permission:xem_nhanvien'])
    ->name('nhanvien.salary-grades-by-band');
Route::get('/nhanvien/create', [EmployeeController::class, 'create'])
    ->middleware(['session.auth', 'permission:them_nhanvien'])
    ->name('nhanvien.create');
Route::post('/nhanvien', [EmployeeController::class, 'store'])
    ->middleware(['session.auth', 'permission:them_nhanvien'])
    ->name('nhanvien.store');
Route::get('/nhanvien/{employee}/edit', [EmployeeController::class, 'edit'])
    ->middleware(['session.auth', 'permission:sua_nhanvien'])
    ->name('nhanvien.edit');
Route::put('/nhanvien/{employee}', [EmployeeController::class, 'update'])
    ->middleware(['session.auth', 'permission:sua_nhanvien'])
    ->name('nhanvien.update');
Route::post('/nhanvien/{employee}', [EmployeeController::class, 'update'])
    ->middleware(['session.auth', 'permission:sua_nhanvien'])
    ->name('nhanvien.update.legacy');
Route::delete('/nhanvien/{employee}', [EmployeeController::class, 'destroy'])
    ->middleware(['session.auth', 'permission:xoa_nhanvien'])
    ->name('nhanvien.destroy');
Route::get('/nhanvien/{employee}/delete-legacy', [EmployeeController::class, 'destroyLegacy'])
    ->middleware(['session.auth', 'permission:xoa_nhanvien'])
    ->name('nhanvien.destroy.legacy');

Route::get('/phongban', [DepartmentController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_phongban'])
    ->name('phongban.index');

Route::get('/chamcong', [AttendanceController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_chamcong'])
    ->name('chamcong.index');
Route::get('/chamcong/bang-thang', [AttendanceController::class, 'matrix'])
    ->middleware(['session.auth', 'permission:xem_chamcong'])
    ->name('chamcong.matrix');
Route::post('/chamcong/bang-thang/o', [AttendanceController::class, 'updateCell'])
    ->middleware(['session.auth', 'permission:them_chamcong'])
    ->name('chamcong.matrix.cell');
Route::get('/chamcong/so-ngay-cong', [AttendanceController::class, 'workedDays'])
    ->middleware(['session.auth', 'permission:xem_chamcong'])
    ->name('chamcong.worked-days');
Route::get('/chamcong/export-excel', [AttendanceController::class, 'exportExcel'])
    ->middleware(['session.auth', 'permission:xuat_bang_cham_cong'])
    ->name('chamcong.export-excel');
Route::get('/chamcong/create', [AttendanceController::class, 'create'])
    ->middleware(['session.auth', 'permission:them_chamcong'])
    ->name('chamcong.create');
Route::post('/chamcong', [AttendanceController::class, 'store'])
    ->middleware(['session.auth', 'permission:them_chamcong'])
    ->name('chamcong.store');
Route::get('/chamcong/{attendance}/edit', [AttendanceController::class, 'edit'])
    ->middleware(['session.auth', 'permission:sua_chamcong'])
    ->name('chamcong.edit');
Route::put('/chamcong/{attendance}', [AttendanceController::class, 'update'])
    ->middleware(['session.auth', 'permission:sua_chamcong'])
    ->name('chamcong.update');
Route::post('/chamcong/{attendance}', [AttendanceController::class, 'update'])
    ->middleware(['session.auth', 'permission:sua_chamcong'])
    ->name('chamcong.update.legacy');
Route::delete('/chamcong/{attendance}', [AttendanceController::class, 'destroy'])
    ->middleware(['session.auth', 'permission:xoa_chamcong'])
    ->name('chamcong.destroy');
Route::get('/chamcong/{attendance}/delete-legacy', [AttendanceController::class, 'destroyLegacy'])
    ->middleware(['session.auth', 'permission:xoa_chamcong'])
    ->name('chamcong.destroy.legacy');

Route::get('/luong', [PayrollController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_luong'])
    ->name('luong.index');
Route::post('/luong/tinh-thang', [PayrollController::class, 'runMonthly'])
    ->middleware(['session.auth', 'permission:tinh_luong_thang'])
    ->name('luong.run-monthly');
Route::get('/luong/job-status', [PayrollController::class, 'jobStatus'])
    ->middleware(['session.auth', 'permission:xem_luong'])
    ->name('luong.job-status');
Route::get('/luong/salary-components', [PayrollController::class, 'salaryComponents'])
    ->middleware(['session.auth', 'permission:tinh_luong_thang'])
    ->name('luong.salary-components');
Route::get('/luong/export-excel', [PayrollController::class, 'exportExcel'])
    ->middleware(['session.auth', 'permission:xem_luong'])
    ->name('luong.export-excel');
Route::get('/luong/create', [PayrollController::class, 'create'])
    ->middleware(['session.auth', 'permission:tinh_luong_thang'])
    ->name('luong.create');
Route::post('/luong', [PayrollController::class, 'store'])
    ->middleware(['session.auth', 'permission:tinh_luong_thang'])
    ->name('luong.store');
Route::get('/luong/{payroll}/edit', [PayrollController::class, 'edit'])
    ->middleware(['session.auth', 'permission:mo_chot_luong'])
    ->name('luong.edit');
Route::get('/luong/{payroll}', [PayrollController::class, 'show'])
    ->middleware(['session.auth', 'permission:xem_luong'])
    ->name('luong.show');
Route::put('/luong/{payroll}', [PayrollController::class, 'update'])
    ->middleware(['session.auth', 'permission:mo_chot_luong'])
    ->name('luong.update');
Route::post('/luong/{payroll}', [PayrollController::class, 'update'])
    ->middleware(['session.auth', 'permission:mo_chot_luong'])
    ->name('luong.update.legacy');
Route::get('/luong/{payroll}/lock-legacy', [PayrollController::class, 'lock'])
    ->middleware(['session.auth', 'permission:chot_luong'])
    ->name('luong.lock.legacy');
Route::get('/luong/{payroll}/unlock-legacy', [PayrollController::class, 'unlock'])
    ->middleware(['session.auth', 'permission:mo_chot_luong'])
    ->name('luong.unlock.legacy');

Route::get('/tuyendung', [RecruitmentController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_dot_tuyen'])
    ->name('tuyendung.index');
Route::get('/tuyendung/create', [RecruitmentController::class, 'create'])
    ->middleware(['session.auth', 'permission:them_dot_tuyen'])
    ->name('tuyendung.create');
Route::post('/tuyendung', [RecruitmentController::class, 'store'])
    ->middleware(['session.auth', 'permission:them_dot_tuyen'])
    ->name('tuyendung.store');
Route::get('/tuyendung/{recruitment}/edit', [RecruitmentController::class, 'edit'])
    ->middleware(['session.auth', 'permission:them_dot_tuyen'])
    ->name('tuyendung.edit');
Route::put('/tuyendung/{recruitment}', [RecruitmentController::class, 'update'])
    ->middleware(['session.auth', 'permission:them_dot_tuyen'])
    ->name('tuyendung.update');
Route::post('/tuyendung/{recruitment}', [RecruitmentController::class, 'update'])
    ->middleware(['session.auth', 'permission:them_dot_tuyen'])
    ->name('tuyendung.update.legacy');
Route::delete('/tuyendung/{recruitment}', [RecruitmentController::class, 'destroy'])
    ->middleware(['session.auth', 'permission:xoa_dot_tuyen'])
    ->name('tuyendung.destroy');
Route::get('/tuyendung/{recruitment}/delete-legacy', [RecruitmentController::class, 'destroyLegacy'])
    ->middleware(['session.auth', 'permission:xoa_dot_tuyen'])
    ->name('tuyendung.destroy.legacy');
Route::get('/tuyendung/ungvien', [RecruitmentController::class, 'candidates'])
    ->middleware(['session.auth', 'permission:xem_ung_vien'])
    ->name('tuyendung.ungvien.index');
Route::get('/tuyendung/ungvien/create', [RecruitmentController::class, 'createCandidate'])
    ->middleware(['session.auth', 'permission:them_ung_vien'])
    ->name('tuyendung.ungvien.create');
Route::post('/tuyendung/ungvien', [RecruitmentController::class, 'storeCandidate'])
    ->middleware(['session.auth', 'permission:them_ung_vien'])
    ->name('tuyendung.ungvien.store');
Route::get('/tuyendung/ungvien/{candidate}/chon-dot', [RecruitmentController::class, 'applyCandidate'])
    ->middleware(['session.auth', 'permission:them_ho_so'])
    ->name('tuyendung.ungvien.apply');
Route::post('/tuyendung/ungvien/{candidate}/tao-hoso', [RecruitmentController::class, 'attachCandidate'])
    ->middleware(['session.auth', 'permission:them_ho_so'])
    ->name('tuyendung.ungvien.attach');
Route::get('/tuyendung/{recruitment}/hoso', [RecruitmentController::class, 'applications'])
    ->middleware(['session.auth', 'permission:xem_ho_so'])
    ->name('tuyendung.hoso.index');
Route::post('/tuyendung/hoso/{application}/trang-thai', [RecruitmentController::class, 'updateApplicationStatus'])
    ->middleware(['session.auth', 'permission:capnhat_trangthai'])
    ->name('tuyendung.hoso.status');
Route::get('/tuyendung/hoso/{application}/phongvan', [RecruitmentController::class, 'interviews'])
    ->middleware(['session.auth', 'permission:xem_lich_phong_van'])
    ->name('tuyendung.hoso.phongvan');
Route::post('/tuyendung/hoso/{application}/phongvan', [RecruitmentController::class, 'storeInterview'])
    ->middleware(['session.auth', 'permission:them_lich_phong_van'])
    ->name('tuyendung.hoso.phongvan.store');
Route::post('/tuyendung/hoso/{application}/danhgia', [RecruitmentController::class, 'storeReview'])
    ->middleware(['session.auth', 'permission:them_danh_gia'])
    ->name('tuyendung.hoso.danhgia.store');
Route::post('/tuyendung/hoso/kanban-status', [RecruitmentController::class, 'updateKanban'])
    ->middleware(['session.auth', 'permission:capnhat_trangthai'])
    ->name('tuyendung.hoso.kanban-status');

Route::get('/daotao', [TrainingController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_khoa_dao_tao'])
    ->name('daotao.index');
Route::get('/daotao/create', [TrainingController::class, 'create'])
    ->middleware(['session.auth', 'permission:them_khoa_dao_tao'])
    ->name('daotao.create');
Route::post('/daotao', [TrainingController::class, 'store'])
    ->middleware(['session.auth', 'permission:them_khoa_dao_tao'])
    ->name('daotao.store');
Route::get('/daotao/{training}/edit', [TrainingController::class, 'edit'])
    ->middleware(['session.auth', 'permission:them_khoa_dao_tao'])
    ->name('daotao.edit');
Route::get('/daotao/{training}/hocvien', [TrainingController::class, 'participants'])
    ->middleware(['session.auth', 'permission:xem_tham_gia_dao_tao'])
    ->name('daotao.hocvien');
Route::post('/daotao/{training}/hocvien', [TrainingController::class, 'storeParticipant'])
    ->middleware(['session.auth', 'permission:them_tham_gia_dao_tao'])
    ->name('daotao.hocvien.store');
Route::post('/daotao/hocvien/{participant}/ketqua', [TrainingController::class, 'updateParticipantResult'])
    ->middleware(['session.auth', 'permission:capnhat_ketqua_dao_tao'])
    ->name('daotao.hocvien.ketqua');
Route::put('/daotao/{training}', [TrainingController::class, 'update'])
    ->middleware(['session.auth', 'permission:them_khoa_dao_tao'])
    ->name('daotao.update');
Route::post('/daotao/{training}', [TrainingController::class, 'update'])
    ->middleware(['session.auth', 'permission:them_khoa_dao_tao'])
    ->name('daotao.update.legacy');
Route::delete('/daotao/{training}', [TrainingController::class, 'destroy'])
    ->middleware(['session.auth', 'permission:xoa_khoa_dao_tao'])
    ->name('daotao.destroy');
Route::get('/daotao/{training}/delete-legacy', [TrainingController::class, 'destroy'])
    ->middleware(['session.auth', 'permission:xoa_khoa_dao_tao'])
    ->name('daotao.destroy.legacy');

Route::get('/baocao', [ReportController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_baocao'])
    ->name('baocao.index');
Route::get('/baocao/export-excel', [ReportController::class, 'exportExcel'])
    ->middleware(['session.auth', 'permission:xuatex_baocao'])
    ->name('baocao.export-excel');
Route::get('/baocao/export-json', [ReportController::class, 'exportJson'])
    ->middleware(['session.auth', 'permission:xuatex_baocao'])
    ->name('baocao.export-json');
Route::get('/baocao/create', [ReportController::class, 'create'])
    ->middleware(['session.auth', 'permission:them_baocao'])
    ->name('baocao.create');
Route::post('/baocao', [ReportController::class, 'store'])
    ->middleware(['session.auth', 'permission:them_baocao'])
    ->name('baocao.store');
Route::get('/baocao/{report}/edit', [ReportController::class, 'edit'])
    ->middleware(['session.auth', 'permission:sua_baocao'])
    ->name('baocao.edit');
Route::put('/baocao/{report}', [ReportController::class, 'update'])
    ->middleware(['session.auth', 'permission:sua_baocao'])
    ->name('baocao.update');
Route::post('/baocao/{report}', [ReportController::class, 'update'])
    ->middleware(['session.auth', 'permission:sua_baocao'])
    ->name('baocao.update.legacy');
Route::delete('/baocao/{report}', [ReportController::class, 'destroy'])
    ->middleware(['session.auth', 'permission:xoa_baocao'])
    ->name('baocao.destroy');
Route::get('/baocao/{report}/delete-legacy', [ReportController::class, 'destroyLegacy'])
    ->middleware(['session.auth', 'permission:xoa_baocao'])
    ->name('baocao.destroy.legacy');

Route::get('/systemhealth', [SystemHealthController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_taikhoan'])
    ->name('systemhealth.index');

Route::get('/phanquyen', [RolePermissionController::class, 'index'])
    ->middleware(['session.auth', 'permission:xem_phanquyen'])
    ->name('phanquyen.index');
Route::get('/phanquyen/taikhoan/{account}', [RolePermissionController::class, 'showAccount'])
    ->middleware(['session.auth', 'permission:xem_phanquyen'])
    ->name('phanquyen.taikhoan');
Route::post('/phanquyen/{role}', [RolePermissionController::class, 'update'])
    ->middleware(['session.auth', 'permission:sua_taikhoan'])
    ->name('phanquyen.update');
Route::post('/phanquyen/{role}/khoi-phuc-mac-dinh', [RolePermissionController::class, 'restoreDefaults'])
    ->middleware(['session.auth', 'permission:sua_taikhoan'])
    ->name('phanquyen.restore-defaults');

Route::get('/audit-log/export-csv', [AuditLogExportController::class, 'exportCsv'])
    ->middleware(['session.auth', 'permission:xem_taikhoan'])
    ->name('audit-log.export-csv');

Route::get('/audit-log/export-json', [AuditLogExportController::class, 'exportJson'])
    ->middleware(['session.auth', 'permission:xem_taikhoan'])
    ->name('audit-log.export-json');
