<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DashboardBizController extends Controller
{
    private function conn(string $service): string
    {
        return (string) config("service_registry.services.{$service}.connection", config('database.default'));
    }

    public function metrics(): JsonResponse
    {
        $now = Carbon::now();
        $metrics = $this->rememberCache(
            'api:dashboard:metrics:' . $now->format('Y-m'),
            function () use ($now): array {
                return [
                    'employees'                  => $this->safe(fn () => DB::connection($this->conn('hr'))->table('nhanvien')->count()),
                    'departments'                => $this->safe(fn () => DB::connection($this->conn('hr'))->table('phongban')->count()),
                    'attendanceThisMonth'        => $this->safe(fn () => DB::connection($this->conn('attendance'))->table('chamcong')->whereMonth('Ngay', $now->month)->whereYear('Ngay', $now->year)->count()),
                    'payrollThisMonth'           => $this->safe(fn () => DB::connection($this->conn('payroll'))->table('bangluong')->where('Thang', $now->month)->where('Nam', $now->year)->count()),
                    'activeRecruitmentCampaigns' => $this->safe(fn () => DB::connection($this->conn('recruitment'))->table('dottuyendung')->where('TrangThai', 'Đang tuyển')->count()),
                    'activeTrainingCourses'      => $this->safe(fn () => DB::connection($this->conn('training'))->table('khoadaotao')->where('TrangThai', 'Đang đào tạo')->count()),
                    'reports'                    => $this->safe(fn () => DB::connection($this->conn('reporting'))->table('baocao')->count()),
                    'chatbotSessions'            => $this->safe(fn () => DB::connection($this->conn('chatbot'))->table('chatbot_sessions')->count()),
                    'chatbotMessagesToday'       => $this->safe(fn () => DB::connection($this->conn('chatbot'))->table('chatbot_messages')->where('created_at', '>=', $now->copy()->startOfDay()->toDateTimeString())->count()),
                    'chatbotDraftsPending'       => $this->safe(fn () => DB::connection($this->conn('chatbot'))->table('chatbot_action_drafts')->where('status_name', 'pending')->count()),
                ];
            },
            120
        );

        return response()->json(['ok' => true, 'data' => $metrics]);
    }

    public function recentActivity(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 8), 50));

        $items = $this->rememberCache(
            'api:dashboard:recent-activity:' . $limit,
            function () use ($limit): array {
                return array_merge(
                    $this->safeArray(fn () => $this->recentRecruitment($limit)),
                    $this->safeArray(fn () => $this->recentTraining($limit)),
                    $this->safeArray(fn () => $this->recentReports($limit)),
                    $this->safeArray(fn () => $this->recentChatbotSessions($limit)),
                );
            },
            120
        );

        usort($items, fn ($a, $b) => strcmp((string) $b['sort_at'], (string) $a['sort_at']));

        return response()->json(['ok' => true, 'data' => array_slice($items, 0, $limit)]);
    }

    public function charts(Request $request): JsonResponse
    {
        $permissions = array_values(array_filter((array) $request->input('permissions', []), 'is_string'));
        $result = $this->rememberCache(
            'api:dashboard:charts:' . md5(json_encode($permissions)),
            function () use ($permissions): array {
                $result = [];

                $hrConn      = $this->conn('hr');
                $recruitConn = $this->conn('recruitment');
                $payrollConn = $this->conn('payroll');
                $attendConn  = $this->conn('attendance');

                if (in_array('xem_phongban', $permissions, true) || in_array('xem_nhanvien', $permissions, true)) {
                    $rows = DB::connection($hrConn)
                        ->table('phancong as pc')->join('phongban as pb', 'pc.MaPB', '=', 'pb.MaPB')
                        ->selectRaw('pb.TenPB, COUNT(DISTINCT pc.MaNV) as total')
                        ->whereRaw('(pc.NgayKetThuc IS NULL OR pc.NgayKetThuc >= CURDATE())')
                        ->groupBy('pb.MaPB', 'pb.TenPB')->orderByDesc('total')->limit(8)->get();
                    $result['department'] = ['labels' => $rows->pluck('TenPB')->all(), 'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all()];
                }

                if (in_array('xem_nghiphep', $permissions, true) || in_array('duyet_nghiphep', $permissions, true)) {
                    $rows = DB::connection($hrConn)->table('nghiphep')->selectRaw('TrangThai, COUNT(*) as total')->groupBy('TrangThai')->orderByDesc('total')->get();
                    $result['leave'] = ['labels' => $rows->pluck('TrangThai')->all(), 'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all()];
                }

                if (in_array('xem_chamcong', $permissions, true)) {
                    $rows = DB::connection($attendConn)->table('chamcong')->selectRaw('DATE(Ngay) as ngay, COUNT(*) as total')->whereRaw('Ngay >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)')->groupBy('ngay')->orderBy('ngay')->get();
                    $result['attendance'] = ['labels' => $rows->pluck('ngay')->all(), 'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all()];
                }

                if (in_array('xem_luong', $permissions, true)) {
                    $rows = DB::connection($payrollConn)->table('bangluong')->selectRaw('TrangThai, COUNT(*) as total')->whereRaw('Thang = MONTH(CURDATE()) AND Nam = YEAR(CURDATE())')->groupBy('TrangThai')->orderByDesc('total')->get();
                    $result['payroll'] = ['labels' => $rows->pluck('TrangThai')->all(), 'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all()];
                }

                if (in_array('xem_ho_so', $permissions, true) || in_array('xem_dot_tuyen', $permissions, true)) {
                    $rows = DB::connection($recruitConn)->table('hosoungtuyen')->selectRaw('TrangThai, COUNT(*) as total')->groupBy('TrangThai')->orderByDesc('total')->get();
                    $result['recruitment'] = ['labels' => $rows->pluck('TrangThai')->all(), 'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all()];
                }

                return $result;
            },
            120
        );

        return response()->json(['ok' => true, 'charts' => $result]);
    }

    public function markNotificationsRead(Request $request): JsonResponse
    {
        $maTK = (int) $request->input('ma_tk', 0);
        $type = (string) $request->input('type', 'all');

        $hrConn      = $this->conn('hr');
        $recruitConn = $this->conn('recruitment');

        $leaveCount    = $this->rememberCache('api:dashboard:notif:leave', fn () => DB::connection($hrConn)->table('nghiphep')->where('TrangThai', 'Chờ duyệt')->count(), 30);
        $contractCount = $this->rememberCache('api:dashboard:notif:contract', fn () => DB::connection($hrConn)->table('hopdong')->whereNotNull('NgayKetThuc')->where('NgayKetThuc', '<=', now()->addDays(30)->toDateString())->count(), 30);
        $candidateCount = $this->rememberCache('api:dashboard:notif:candidate', fn () => DB::connection($recruitConn)->table('hosoungtuyen')->where('NgayNop', '>=', now()->subDays(7)->toDateString())->count(), 30);

        $existing = DB::connection($hrConn)->table('thongbao_daxem')->where('MaTK', $maTK)->first();
        $seenLeave    = (int) ($existing->DaXemNghiPhep ?? 0);
        $seenContract = (int) ($existing->DaXemHopDong ?? 0);
        $seenCandidate = (int) ($existing->DaXemUngVien ?? 0);

        if ($type === 'leave'    || $type === 'all') { $seenLeave    = $leaveCount; }
        if ($type === 'contract' || $type === 'all') { $seenContract = $contractCount; }
        if ($type === 'candidate'|| $type === 'all') { $seenCandidate = $candidateCount; }

        DB::connection($hrConn)->table('thongbao_daxem')->updateOrInsert(
            ['MaTK' => $maTK],
            ['DaXemNghiPhep' => $seenLeave, 'DaXemHopDong' => $seenContract, 'DaXemUngVien' => $seenCandidate, 'UpdatedAt' => now()]
        );

        return response()->json(['ok' => true]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function safe(callable $fn, int $default = 0): int
    {
        try {
            return (int) $fn();
        } catch (\Throwable) {
            return $default;
        }
    }

    private function safeArray(callable $fn): array
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return [];
        }
    }

    private function rememberCache(string $key, callable $resolver, int $ttlSeconds): mixed
    {
        try {
            return Cache::remember($key, $ttlSeconds, $resolver);
        } catch (Throwable $exception) {
            Log::warning('Dashboard cache remember failed', ['key' => $key, 'error' => $exception->getMessage()]);
        }

        try {
            return Cache::store((string) env('API_LIST_CACHE_FALLBACK_STORE', 'file'))->remember($key, $ttlSeconds, $resolver);
        } catch (Throwable $exception) {
            Log::warning('Dashboard fallback cache remember failed', ['key' => $key, 'error' => $exception->getMessage()]);
            return $resolver();
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
