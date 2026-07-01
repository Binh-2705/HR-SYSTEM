<?php

namespace Tests\Feature;

use App\Services\PermissionService;
use App\Services\RecruitmentService;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class RecruitmentFlowPagesTest extends TestCase
{
    public function test_recruitment_candidates_page_renders_for_authorized_session(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(RecruitmentService::class, function ($mock) {
            $mock->shouldReceive('paginateCandidates')->once()->with([])->andReturn(new LengthAwarePaginator([
                (object) ['MaUV' => 9, 'HoTen' => 'Ung vien A', 'NgaySinh' => '2000-01-01', 'GioiTinh' => 'Nam', 'Email' => 'uva@example.com', 'DienThoai' => '0900000001', 'TrinhDo' => 'Dai hoc', 'FileCV' => 'uva.pdf', 'DiemCV' => 8, 'SoHoSo' => 2],
            ], 1, 12));
        });

        $this->withSession(['MaTK' => 3])->get('/tuyendung/ungvien')->assertOk()->assertSee('Ung vien A');
    }

    public function test_recruitment_applications_page_renders_for_authorized_session(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(RecruitmentService::class, function ($mock) {
            $mock->shouldReceive('find')->once()->with(2)->andReturn([
                'MaDTD' => 2,
                'TenDotTuyenDung' => 'Dot Backend',
                'ViTriTuyenDung' => 'Backend Developer',
                'TrangThai' => 'Đang tuyển',
                'TuNgay' => '2026-04-01',
                'DenNgay' => '2026-04-30',
            ]);
            $mock->shouldReceive('paginateApplications')->once()->with(2, [])->andReturn(new LengthAwarePaginator([
                (object) ['MaHS' => 11, 'HoTen' => 'Tran B', 'Email' => 'tranb@example.com', 'DienThoai' => '0900000002', 'DiemCV' => 7, 'NgayNop' => '2026-04-02', 'TrangThai' => 'Phỏng vấn', 'GhiChu' => null, 'SoLichPhongVan' => 1, 'FileCV' => 'tranb.pdf'],
            ], 1, 12));
        });

        $this->withSession(['MaTK' => 3])->get('/tuyendung/2/hoso')->assertOk()->assertSee('Tran B');
    }

    public function test_recruitment_interviews_page_renders_for_authorized_session(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(RecruitmentService::class, function ($mock) {
            $mock->shouldReceive('findApplication')->once()->with(11)->andReturn([
                'MaHS' => 11,
                'MaDTD' => 2,
                'HoTen' => 'Tran B',
                'TrangThai' => 'Phỏng vấn',
                'TenDotTuyenDung' => 'Dot Backend',
                'DiemCV' => 7,
                'FileCV' => 'tranb.pdf',
            ]);
            $mock->shouldReceive('listInterviews')->once()->with(11)->andReturn([
                (object) ['NgayPhongVan' => '2026-04-10', 'GioPhongVan' => '09:00:00', 'DiaDiem' => 'Phong hop 2', 'GhiChu' => 'Mang laptop', 'KetQua' => 'Dat'],
            ]);
            $mock->shouldReceive('listReviews')->once()->with(11)->andReturn([
                (object) ['DiemKyNang' => 8, 'DiemKinhNghiem' => 7, 'DiemThaiDo' => 9, 'NhanXet' => 'Ung vien tot'],
            ]);
        });

        $this->withSession(['MaTK' => 3])->get('/tuyendung/hoso/11/phongvan')->assertOk()->assertSee('Phong hop 2')->assertSee('Ung vien tot');
    }
}