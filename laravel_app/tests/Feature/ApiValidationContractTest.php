<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiValidationContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.service_gateway.token' => 'test-token']);
    }

    public function test_reports_store_requires_ten_bao_cao(): void
    {
        $response = $this->withHeader('X-Service-Token', 'test-token')
            ->postJson('/api/reports', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['TenBaoCao']);
    }

    public function test_payroll_update_validates_month_range(): void
    {
        $response = $this->withHeader('X-Service-Token', 'test-token')
            ->putJson('/api/payroll/1', ['Thang' => 13]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['Thang']);
    }

    public function test_attendance_store_requires_basic_fields(): void
    {
        $response = $this->withHeader('X-Service-Token', 'test-token')
            ->postJson('/api/attendance', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['MaNV', 'Ngay', 'TrangThai']);
    }

    public function test_recruitment_campaign_store_requires_name_and_position(): void
    {
        $response = $this->withHeader('X-Service-Token', 'test-token')
            ->postJson('/api/recruitment/campaigns', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['TenDotTuyenDung', 'ViTriTuyenDung']);
    }

    public function test_training_course_store_requires_course_name(): void
    {
        $response = $this->withHeader('X-Service-Token', 'test-token')
            ->postJson('/api/training/courses', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['TenKhoaDaoTao']);
    }

    public function test_chatbot_upsert_session_requires_session_key(): void
    {
        $response = $this->withHeader('X-Service-Token', 'test-token')
            ->postJson('/api/biz/chatbot/sessions/upsert', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session_key']);
    }

    public function test_chatbot_log_message_requires_session_id_and_role_name(): void
    {
        $response = $this->withHeader('X-Service-Token', 'test-token')
            ->postJson('/api/biz/chatbot/messages', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session_id', 'role_name']);
    }
}
