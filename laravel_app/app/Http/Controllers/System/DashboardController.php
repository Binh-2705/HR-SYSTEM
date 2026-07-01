<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;

use App\Services\DashboardOverviewService;
use App\Services\InternalApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardOverviewService $dashboardOverviewService,
        private InternalApiClient $client,
    ) {}

    public function index(): View
    {
        $permissions  = (array) session('quyen', []);
        $maTK         = (int) session('MaTK', 0);
        $metrics      = (array) $this->dashboardOverviewService->metrics();
        $recentActivity = (array) $this->dashboardOverviewService->recentActivity();

        // Avoid blocking first paint with an internal API call.
        $cacheKey    = "dashboard_charts_{$maTK}";
        $inlineCharts = Cache::get($cacheKey, []);
        if (!is_array($inlineCharts)) {
            $inlineCharts = [];
        }

        return view('trangchu.index', [
            'taiKhoan'     => session('taikhoan'),
            'quyen'        => $permissions,
            'metricCards'  => $this->buildMetricCards($metrics, $permissions),
            'moduleLinks'  => $this->buildModuleLinks($permissions),
            'quickSignals' => $this->buildQuickSignals($metrics, $permissions),
            'recentActivity' => $this->filterRecentActivity($recentActivity, $permissions),
            'inlineCharts' => $inlineCharts,
        ]);
    }

    public function chartData(Request $request): JsonResponse
    {
        if (!session()->has('taikhoan')) {
            return response()->json(['ok' => false, 'message' => 'UNAUTHORIZED'], 401);
        }

        $permissions = (array) session('quyen', []);
        $maTK        = (int) session('MaTK', 0);

        $cacheKey = "dashboard_charts_{$maTK}";
        if ($request->query('refresh') === '1') {
            Cache::forget($cacheKey);
        }

        try {
            $data = Cache::remember($cacheKey, 300, function () use ($permissions) {
                $response = $this->client->post('biz/dashboard/charts', ['permissions' => $permissions], 4);
                return $response['charts'] ?? [];
            });
        } catch (Throwable $e) {
            report($e);
            $data = [];
        }

        return response()->json(['ok' => true, 'charts' => $data])
            ->header('Cache-Control', 'private, max-age=300');
    }

    public function markNotificationsRead(Request $request): JsonResponse
    {
        $type = (string) $request->input('type', 'all');
        $allowed = ['all', 'leave', 'contract', 'candidate'];

        if (!in_array($type, $allowed, true)) {
            return response()->json(['ok' => false, 'message' => 'INVALID_TYPE'], 422);
        }

        $accountId = (int) session('MaTK', 0);
        if ($accountId <= 0) {
            return response()->json(['ok' => false, 'message' => 'UNAUTHORIZED'], 401);
        }

        try {
            $this->client->post('biz/dashboard/notifications/mark-read', [
                'ma_tk' => $accountId,
                'type'  => $type,
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => 'SERVICE_UNAVAILABLE'], 503);
        }

        return response()->json(['ok' => true]);
    }

    private function buildMetricCards(array $metrics, array $permissions): array
    {
        $cards = [
            ['permission' => 'xem_nhanvien', 'label' => 'Nhân viên', 'value' => $metrics['employees'] ?? 0],
            ['permission' => 'xem_phongban', 'label' => 'Phòng ban', 'value' => $metrics['departments'] ?? 0],
            ['permission' => 'xem_chamcong', 'label' => 'Chấm công tháng này', 'value' => $metrics['attendanceThisMonth'] ?? 0],
            ['permission' => 'xem_luong', 'label' => 'Bảng lương tháng này', 'value' => $metrics['payrollThisMonth'] ?? 0],
            ['permission' => 'xem_dot_tuyen', 'label' => 'Đợt tuyển đang mở', 'value' => $metrics['activeRecruitmentCampaigns'] ?? 0],
            ['permission' => 'xem_khoa_dao_tao', 'label' => 'Khóa đào tạo đang chạy', 'value' => $metrics['activeTrainingCourses'] ?? 0],
            ['permission' => 'xem_baocao', 'label' => 'Báo cáo', 'value' => $metrics['reports'] ?? 0],
            ['permission' => 'su_dung_chatbot', 'label' => 'Lượt trò chuyện chatbot', 'value' => $metrics['chatbotSessions'] ?? 0],
        ];

        return array_values(array_filter($cards, fn (array $card) => in_array($card['permission'], $permissions, true)));
    }

    private function buildModuleLinks(array $permissions): array
    {
        $links = [
            ['permission' => 'xem_nhanvien', 'label' => 'Mở nhân viên', 'route' => route('nhanvien.index'), 'secondary' => false],
            ['permission' => 'xem_phongban', 'label' => 'Mở phòng ban', 'route' => route('phongban.index'), 'secondary' => true],
            ['permission' => 'xem_chamcong', 'label' => 'Mở chấm công', 'route' => route('chamcong.index'), 'secondary' => true],
            ['permission' => 'xem_luong', 'label' => 'Mở lương', 'route' => route('luong.index'), 'secondary' => true],
            ['permission' => 'xem_dot_tuyen', 'label' => 'Mở tuyển dụng', 'route' => route('tuyendung.index'), 'secondary' => true],
            ['permission' => 'xem_khoa_dao_tao', 'label' => 'Mở đào tạo', 'route' => route('daotao.index'), 'secondary' => true],
            ['permission' => 'xem_baocao', 'label' => 'Mở báo cáo', 'route' => route('baocao.index'), 'secondary' => true],
            ['permission' => 'su_dung_chatbot', 'label' => 'Mở nhật ký chatbot', 'route' => route('chatbot.index'), 'secondary' => true],
            ['permission' => 'xem_phanquyen', 'label' => 'Mở phân quyền', 'route' => route('phanquyen.index'), 'secondary' => true],
            ['permission' => 'xem_phanquyen', 'label' => 'Mở bảng dịch vụ', 'route' => route('services.index'), 'secondary' => true],
        ];

        return array_values(array_filter($links, fn (array $link) => in_array($link['permission'], $permissions, true)));
    }

    private function buildQuickSignals(array $metrics, array $permissions): array
    {
        $signals = [];

        if (in_array('su_dung_chatbot', $permissions, true)) {
            $signals[] = ['label' => 'Tin nhắn chatbot hôm nay', 'value' => $metrics['chatbotMessagesToday'] ?? 0, 'note' => null];
            $signals[] = ['label' => 'Bản nháp chatbot đang chờ', 'value' => $metrics['chatbotDraftsPending'] ?? 0, 'note' => 'Kiểm tra các thao tác AI đang chờ xác nhận.'];
        }

        if (in_array('xem_dot_tuyen', $permissions, true)) {
            $signals[] = ['label' => 'Đợt tuyển đang mở', 'value' => $metrics['activeRecruitmentCampaigns'] ?? 0, 'note' => 'Theo dõi các đợt cần đóng lịch và xử lý hồ sơ.'];
        }

        if (in_array('xem_khoa_dao_tao', $permissions, true)) {
            $signals[] = ['label' => 'Khóa đào tạo đang chạy', 'value' => $metrics['activeTrainingCourses'] ?? 0, 'note' => 'Rà soát tiến độ các khóa đang đào tạo.'];
        }

        $signals[] = ['label' => 'Quyền hiện tại', 'value' => count($permissions), 'note' => null];

        return $signals;
    }

    private function filterRecentActivity(array $recentActivity, array $permissions): array
    {
        return array_values(array_filter($recentActivity, function (array $item) use ($permissions) {
            $permission = (string) ($item['permission'] ?? '');

            return $permission !== '' && in_array($permission, $permissions, true);
        }));
    }
}
