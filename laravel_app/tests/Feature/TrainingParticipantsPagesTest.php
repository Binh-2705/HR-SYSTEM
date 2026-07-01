<?php

namespace Tests\Feature;

use App\Services\PermissionService;
use App\Services\TrainingService;
use Tests\TestCase;

class TrainingParticipantsPagesTest extends TestCase
{
    public function test_training_participants_page_renders_for_authorized_session(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(TrainingService::class, function ($mock) {
            $mock->shouldReceive('participantsPageData')->once()->with(5)->andReturn([
                'course' => [
                    'MaKDT' => 5,
                    'TenKhoaDaoTao' => 'Laravel nang cao',
                    'TuNgay' => '2026-04-01',
                    'DenNgay' => '2026-04-10',
                    'DonViToChuc' => 'Noi bo',
                ],
                'participants' => [
                    ['MaTGDT' => 3, 'MaNV' => 7, 'HoTen' => 'Nguyen Van A', 'KetQua' => 'Đạt', 'DiemDanhGia' => 8.5, 'GhiChu' => 'Tot'],
                ],
                'employees' => [
                    ['MaNV' => 8, 'HoTen' => 'Tran Thi B'],
                ],
                'canEvaluate' => true,
            ]);
        });

        $this->withSession(['MaTK' => 3, 'quyen' => ['xem_tham_gia_dao_tao', 'them_tham_gia_dao_tao', 'capnhat_ketqua_dao_tao']])
            ->get('/daotao/5/hocvien')
            ->assertOk()
            ->assertSee('Laravel nang cao')
            ->assertSee('Nguyen Van A');
    }

    public function test_add_training_participant_redirects_back_to_participants_page(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(TrainingService::class, function ($mock) {
            $mock->shouldReceive('addParticipant')->once()->with(6, 12)->andReturnTrue();
        });

        $this->withSession(['MaTK' => 3, 'quyen' => ['them_tham_gia_dao_tao']])
            ->post('/daotao/6/hocvien', ['MaNV' => 12])
            ->assertRedirect('/daotao/6/hocvien');
    }

    public function test_update_training_result_redirects_back_to_participants_page(): void
    {
        $this->mock(PermissionService::class, fn ($mock) => $mock->shouldReceive('hasPermission', 'hasPermissionFromCache')->andReturnTrue());
        $this->mock(TrainingService::class, function ($mock) {
            $mock->shouldReceive('updateParticipantResult')->once()->with(21, [
                'KetQua' => 'Đạt',
                'DiemDanhGia' => 9.5,
                'GhiChu' => 'Hoan thanh tot',
            ]);
        });

        $this->withSession(['MaTK' => 3, 'quyen' => ['capnhat_ketqua_dao_tao']])
            ->post('/daotao/hocvien/21/ketqua', [
                'MaKDT' => 6,
                'KetQua' => 'Đạt',
                'DiemDanhGia' => 9.5,
                'GhiChu' => 'Hoan thanh tot',
            ])
            ->assertRedirect('/daotao/6/hocvien');
    }
}