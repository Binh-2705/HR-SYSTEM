<?php

namespace Tests\Feature;

use App\Services\DashboardOverviewService;
use App\Services\InternalApiClient;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_requires_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login.form'));
    }

    public function test_dashboard_renders_metrics_for_authenticated_session(): void
    {
        $this->mock(DashboardOverviewService::class, function ($mock) {
            $mock->shouldReceive('metrics')->andReturn([
                'employees' => 120,
                'departments' => 8,
                'attendanceThisMonth' => 540,
                'payrollThisMonth' => 118,
                'activeRecruitmentCampaigns' => 3,
                'activeTrainingCourses' => 2,
                'reports' => 25,
                'chatbotSessions' => 14,
                'chatbotMessagesToday' => 41,
                'chatbotDraftsPending' => 5,
            ]);
            $mock->shouldReceive('recentActivity')->andReturn([
                [
                    'type' => 'Bao cao',
                    'permission' => 'xem_baocao',
                    'title' => 'Bao cao tong hop',
                    'description' => 'Nhân sự • admin',
                    'at' => '2026-04-09 10:00:00',
                    'sort_at' => '2026-04-09 10:00:00',
                    'href' => '/baocao',
                ],
                [
                    'type' => 'Chatbot',
                    'permission' => 'su_dung_chatbot',
                    'title' => 'Session abc123',
                    'description' => 'admin • Admin',
                    'at' => '2026-04-09 09:30:00',
                    'sort_at' => '2026-04-09 09:30:00',
                    'href' => '/chatbot/1',
                ],
            ]);
        });

        $this->mock(InternalApiClient::class, function ($mock) {
            $mock->shouldReceive('post')->with('biz/dashboard/charts', \Mockery::any())->andReturn(['charts' => []]);
        });

        $response = $this->withSession([
            'MaTK' => 3,
            'taikhoan' => ['TenDangNhap' => 'admin'],
            'quyen' => ['xem_nhanvien', 'xem_baocao'],
        ])->get('/dashboard');

        $response->assertOk()
            ->assertSee('admin')
            ->assertSee('120')
            ->assertSee('Bao cao')
            ->assertDontSee('Cham cong thang nay')
            ->assertDontSee('Chatbot sessions')
            ->assertSee('Bao cao tong hop')
            ->assertDontSee('Session abc123')
            ->assertSee('Mo nhan vien')
            ->assertSee('Mo bao cao')
            ->assertDontSee('Mo nhat ky chatbot')
            ->assertDontSee('Mo bang dich vu');
    }
}