<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Throwable;

class DashboardOverviewService
{
    public function __construct(private InternalApiClient $client) {}

    private function conn(string $service): string
    {
        return (string) config("service_registry.services.{$service}.connection", config('database.default'));
    }

    public function metrics(): array
    {
        $cacheKey = 'dashboard_metrics';
        $fallbackKey = 'dashboard_metrics_last_good';
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $now = Carbon::now();
            $data = [
                'employees' => DB::connection($this->conn('hr'))->table('nhanvien')->count(),
                'departments' => DB::connection($this->conn('hr'))->table('phongban')->count(),
                'attendanceThisMonth' => DB::connection($this->conn('attendance'))->table('chamcong')->whereMonth('Ngay', $now->month)->whereYear('Ngay', $now->year)->count(),
                'payrollThisMonth' => DB::connection($this->conn('payroll'))->table('bangluong')->where('Thang', $now->month)->where('Nam', $now->year)->count(),
                'activeRecruitmentCampaigns' => DB::connection($this->conn('recruitment'))->table('dottuyendung')->where('TrangThai', 'Đang tuyển')->count(),
                'activeTrainingCourses' => DB::connection($this->conn('training'))->table('khoadaotao')->where('TrangThai', 'Đang đào tạo')->count(),
                'reports' => DB::connection($this->conn('reporting'))->table('baocao')->count(),
                'chatbotSessions' => DB::connection($this->conn('chatbot'))->table('chatbot_sessions')->count(),
                'chatbotMessagesToday' => DB::connection($this->conn('chatbot'))->table('chatbot_messages')->where('created_at', '>=', $now->copy()->startOfDay()->toDateTimeString())->count(),
                'chatbotDraftsPending' => DB::connection($this->conn('chatbot'))->table('chatbot_action_drafts')->where('status_name', 'pending')->count(),
            ];

            Cache::put($cacheKey, $data, 300);
            if ($data !== []) {
                Cache::put($fallbackKey, $data, 1800);
            }

            return $data;
        } catch (Throwable $e) {
            report($e);
            $fallback = Cache::get($fallbackKey, []);
            return is_array($fallback) ? $fallback : [];
        }
    }

    public function recentActivity(int $limit = 8): array
    {
        $cacheKey = 'dashboard_recent_activity_' . $limit;
        $fallbackKey = 'dashboard_recent_activity_last_good_' . $limit;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $data = array_merge(
                $this->recentRecruitment($limit),
                $this->recentTraining($limit),
                $this->recentReports($limit),
                $this->recentChatbotSessions($limit),
            );

            usort($data, fn ($a, $b) => strcmp((string) $b['sort_at'], (string) $a['sort_at']));
            $data = array_slice($data, 0, $limit);

            Cache::put($cacheKey, $data, 120);
            if ($data !== []) {
                Cache::put($fallbackKey, $data, 900);
            }

            return $data;
        } catch (Throwable $e) {
            report($e);
            $fallback = Cache::get($fallbackKey, []);
            return is_array($fallback) ? $fallback : [];
        }
    }

    private function recentRecruitment(int $limit): array
    {
        return DB::connection($this->conn('recruitment'))->table('dottuyendung')
            ->select(['MaDTD', 'TenDotTuyenDung', 'ViTriTuyenDung', 'TuNgay', 'TrangThai'])
            ->orderByDesc('TuNgay')->limit($limit)->get()
            ->map(fn ($i) => [
                'type' => 'Tuyển dụng', 'permission' => 'xem_dot_tuyen',
                'title' => $i->TenDotTuyenDung,
                'description' => trim($i->ViTriTuyenDung . ' • ' . $i->TrangThai),
                'at' => (string) $i->TuNgay, 'sort_at' => (string) $i->TuNgay,
                'href' => route('tuyendung.index'),
            ])->all();
    }

    private function recentTraining(int $limit): array
    {
        return DB::connection($this->conn('training'))->table('khoadaotao')
            ->select(['MaKDT', 'TenKhoaDaoTao', 'DonViToChuc', 'TuNgay', 'TrangThai'])
            ->orderByDesc('TuNgay')->limit($limit)->get()
            ->map(fn ($i) => [
                'type' => 'Đào tạo', 'permission' => 'xem_khoa_dao_tao',
                'title' => $i->TenKhoaDaoTao,
                'description' => trim(($i->DonViToChuc ?: 'Nội bộ') . ' • ' . $i->TrangThai),
                'at' => (string) $i->TuNgay, 'sort_at' => (string) $i->TuNgay,
                'href' => route('daotao.index'),
            ])->all();
    }

    private function recentReports(int $limit): array
    {
        return DB::connection($this->conn('reporting'))->table('baocao')
            ->select(['MaBC', 'TenBaoCao', 'LoaiBaoCao', 'NguoiTao', 'ThoiDiemTao'])
            ->orderByDesc('ThoiDiemTao')->limit($limit)->get()
            ->map(fn ($i) => [
                'type' => 'Báo cáo', 'permission' => 'xem_baocao',
                'title' => $i->TenBaoCao,
                'description' => trim($i->LoaiBaoCao . ' • ' . ($i->NguoiTao ?: 'system')),
                'at' => (string) $i->ThoiDiemTao, 'sort_at' => (string) $i->ThoiDiemTao,
                'href' => route('baocao.index'),
            ])->all();
    }

    private function recentChatbotSessions(int $limit): array
    {
        return DB::connection($this->conn('chatbot'))->table('chatbot_sessions')
            ->select(['id', 'username', 'role_name', 'session_key', 'last_interaction_at'])
            ->orderByDesc('last_interaction_at')->limit($limit)->get()
            ->map(fn ($i) => [
                'type' => 'Chatbot', 'permission' => 'su_dung_chatbot',
                'title' => 'Session ' . $i->session_key,
                'description' => trim($i->username . ' • ' . $i->role_name),
                'at' => (string) $i->last_interaction_at, 'sort_at' => (string) $i->last_interaction_at,
                'href' => route('chatbot.show', ['session' => $i->id]),
            ])->all();
    }
}
