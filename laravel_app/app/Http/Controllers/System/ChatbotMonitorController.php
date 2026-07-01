<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;

use App\Services\ChatbotMonitorService;
use App\Services\InternalApiClient;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class ChatbotMonitorController extends Controller
{
    public function __construct(
        private ChatbotMonitorService $chatbotMonitorService,
        private InternalApiClient $client,
    ) {}

    public function index(): View
    {
        return view('chatbot.index', [
            'sessions' => $this->chatbotMonitorService->paginateSessions(),
        ]);
    }

    public function show(int $session): View
    {
        $payload = $this->chatbotMonitorService->findSession($session);

        return view('chatbot.show', $payload);
    }

    public function ask(Request $request): JsonResponse
    {
        if (!session()->has('taikhoan')) {
            return response()->json(['ok' => false, 'message' => 'SESSION_EXPIRED'], 401);
        }

        $message = trim((string) $request->input('message', ''));
        if ($message === '') {
            return response()->json(['ok' => false, 'message' => 'EMPTY_MESSAGE'], 422);
        }

        $history = session('chatbot_history', []);
        if (!is_array($history)) {
            $history = [];
        }

        $history[] = [
            'role' => 'user',
            'content' => $message,
        ];
        $history = array_slice($history, -10);

        $account = (array) session('taikhoan', []);
        $sessionId = $this->getChatSessionId();
        $this->logMessage($sessionId, 'user', $message, 'user_input');

        $result = $this->callBotService($this->chatEndpoint(), [
            'message' => $message,
            'history' => $history,
            'user' => [
                'ma_tk' => (int) session('MaTK', 0),
                'username' => (string) ($account['TenDangNhap'] ?? ''),
                'role' => (string) ($account['VaiTro'] ?? ''),
                'permissions' => array_values(array_slice((array) session('quyen', []), 0, 80)),
            ],
        ]);

        if (!$result['ok']) {
            logger()->error('Chatbot service call failed', ['error' => $result['error']]);

            $fallback = 'Bot service chưa sẵn sàng. Hãy chạy Python service và thử lại.';
            $history[] = ['role' => 'assistant', 'content' => $fallback];
            session(['chatbot_history' => array_slice($history, -10)]);
            $this->logMessage($sessionId, 'assistant', $fallback, 'fallback');

            return response()->json([
                'ok' => true,
                'reply' => $fallback,
                'actions' => [],
                'suggestions' => [
                    'Tổng số nhân viên hiện tại là bao nhiêu?',
                    'Thống kê nghỉ phép',
                    'Hợp đồng sắp hết hạn',
                ],
                'source' => 'fallback',
            ]);
        }

        $reply = trim((string) ($result['data']['reply'] ?? ''));
        if ($reply === '') {
            $reply = 'Xin lỗi, tôi chưa có câu trả lời phù hợp.';
        }

        $actions = $result['data']['actions'] ?? [];
        if (!is_array($actions)) {
            $actions = [];
        }

        $suggestions = $result['data']['suggestions'] ?? [];
        if (!is_array($suggestions)) {
            $suggestions = [];
        }

        $actionDraftResponse = null;
        $draft = $result['data']['action_draft'] ?? null;
        if (is_array($draft) && !empty($draft['action_type'])) {
            $token = $this->createActionDraft($sessionId, (int) session('MaTK', 0), $draft);
            $actionDraftResponse = [
                'token' => $token,
                'title' => (string) ($draft['title'] ?? 'Xác nhận hành động'),
                'summary' => (string) ($draft['summary'] ?? ''),
                'confirm_label' => (string) ($draft['confirm_label'] ?? 'Xác nhận thực thi'),
                'action_type' => (string) ($draft['action_type'] ?? ''),
            ];
        }

        $history[] = ['role' => 'assistant', 'content' => $reply];
        session(['chatbot_history' => array_slice($history, -10)]);

        $this->logMessage(
            $sessionId,
            'assistant',
            $reply,
            (string) ($result['data']['source'] ?? 'bot_service'),
            $actions,
            $suggestions,
            $actionDraftResponse['token'] ?? null
        );

        return response()->json([
            'ok' => true,
            'reply' => $reply,
            'actions' => array_values($actions),
            'suggestions' => array_values($suggestions),
            'action_draft' => $actionDraftResponse,
            'source' => (string) ($result['data']['source'] ?? 'bot_service'),
        ]);
    }

    public function confirmDraft(Request $request): JsonResponse
    {
        if (!session()->has('taikhoan')) {
            return response()->json(['ok' => false, 'message' => 'SESSION_EXPIRED'], 401);
        }

        $token = trim((string) $request->input('action_token', ''));
        if ($token === '') {
            return response()->json(['ok' => false, 'message' => 'MISSING_ACTION_TOKEN'], 422);
        }

        $currentAccountId = (int) session('MaTK', 0);
        $draft = $this->getPendingActionDraft($token, $currentAccountId);
        if ($draft === null) {
            return response()->json(['ok' => false, 'message' => 'ACTION_DRAFT_NOT_FOUND_OR_EXPIRED'], 404);
        }

        $confirmReason = trim((string) $request->input('confirm_reason', ''));
        $actionType = (string) ($draft->action_type ?? '');
        $requiresReason = in_array($actionType, ['leave_approve', 'leave_reject'], true);
        if ($requiresReason && $confirmReason === '') {
            return response()->json(['ok' => false, 'message' => 'CONFIRM_REASON_REQUIRED'], 422);
        }

        $requiredPermission = $this->requiredPermissionForAction($actionType);
        if ($requiredPermission !== '' && !in_array($requiredPermission, (array) session('quyen', []), true)) {
            return response()->json(['ok' => false, 'message' => 'FORBIDDEN'], 403);
        }

        $payload = json_decode((string) ($draft->payload_json ?? '{}'), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $result = $this->executeDraft($actionType, $payload, $confirmReason);
        $status = $result['ok'] ? 'executed' : 'failed';
        $message = (string) ($result['message'] ?? 'Không xác định');

        if ($confirmReason !== '') {
            $message .= ' | Lý do xác nhận: ' . $confirmReason;
        }

        $stateDiff = trim((string) ($result['state_diff'] ?? ''));
        if ($stateDiff !== '') {
            $message .= ' | ' . $stateDiff;
        }

        $this->client->patch("biz/chatbot/drafts/{$draft->id}/status", [
            'status_name' => $status,
            'confirmed'   => $currentAccountId,
            'executed_at' => now()->toDateTimeString(),
        ]);

        $this->logMessage($this->getChatSessionId(), 'system', $message, 'action_execution');

        return response()->json([
            'ok' => (bool) $result['ok'],
            'reply' => $message,
            'actions' => [$result['ok'] ? 'Hành động đã được thực thi' : 'Hành động không thành công'],
            'suggestions' => [
                'Thống kê nghỉ phép',
                'Tổng số nhân viên hiện tại là bao nhiêu?',
                'Hợp đồng sắp hết hạn',
            ],
            'source' => 'action_execution',
        ], $result['ok'] ? 200 : 422);
    }

    public function clearHistory(): JsonResponse
    {
        if (!session()->has('taikhoan')) {
            return response()->json(['ok' => false, 'message' => 'SESSION_EXPIRED'], 401);
        }

        session()->forget('chatbot_history');
        session(['chatbot_session_key' => bin2hex(random_bytes(16))]);

        return response()->json([
            'ok' => true,
            'reply' => 'Đã xóa lịch sử hội thoại hiện tại.',
            'actions' => ['Lịch sử chat trong session đã được làm mới'],
            'suggestions' => ['Tổng số nhân viên hiện tại là bao nhiêu?', 'Thông tin cá nhân của tôi', 'Hợp đồng sắp hết hạn'],
        ]);
    }

    public function brief(): JsonResponse
    {
        if (!session()->has('taikhoan')) {
            return response()->json(['ok' => false, 'message' => 'SESSION_EXPIRED'], 401);
        }

        $account = (array) session('taikhoan', []);
        $result = $this->callBotService($this->briefEndpoint(), [
            'user' => [
                'ma_tk' => (int) session('MaTK', 0),
                'username' => (string) ($account['TenDangNhap'] ?? ''),
                'role' => (string) ($account['VaiTro'] ?? ''),
                'permissions' => array_values(array_slice((array) session('quyen', []), 0, 80)),
            ],
        ]);

        if (!$result['ok']) {
            return response()->json(['ok' => true, 'items' => [], 'source' => 'fallback']);
        }

        return response()->json([
            'ok' => true,
            'items' => array_values((array) ($result['data']['items'] ?? [])),
            'source' => (string) ($result['data']['source'] ?? 'brief'),
        ]);
    }

    private function executeDraft(string $actionType, array $payload, string $confirmReason = ''): array
    {
        $result = $this->client->post('biz/chatbot/execute-draft', [
            'action_type'    => $actionType,
            'payload'        => $payload,
            'confirm_reason' => $confirmReason,
        ]);

        return is_array($result) ? $result : ['ok' => false, 'message' => 'Không thể thực thi hành động.'];
    }

    private function requiredPermissionForAction(string $actionType): string
    {
        return [
            'leave_approve' => 'duyet_nghiphep',
            'leave_reject' => 'duyet_nghiphep',
            'leave_create' => 'them_nghiphep',
            'contract_extend' => 'giahan_hopdong',
        ][$actionType] ?? '';
    }

    private function currentEmployeeId(): int
    {
        $account = (array) session('taikhoan', []);
        if (!empty($account['MaNVRef'])) {
            return (int) $account['MaNVRef'];
        }

        $employeeCode = trim((string) ($account['MaNV'] ?? ''));
        if ($employeeCode !== '' && preg_match('/(\d+)/', $employeeCode, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function generateRenewalContractNumber(string $baseNumber, string $suffix): string
    {
        // Kept here only as a fallback — logic now lives in ChatbotBizController::executeDraft.
        return trim($baseNumber !== '' ? $baseNumber : 'HD') . '-GH-' . $suffix;
    }

    private function getChatSessionId(): int
    {
        $sessionKey = (string) session('chatbot_session_key', '');
        if ($sessionKey === '') {
            $sessionKey = bin2hex(random_bytes(16));
            session(['chatbot_session_key' => $sessionKey]);
        }

        $account = (array) session('taikhoan', []);
        $result = $this->client->post('biz/chatbot/sessions/upsert', [
            'session_key' => $sessionKey,
            'ma_tk'       => (int) session('MaTK', 0),
            'username'    => (string) ($account['TenDangNhap'] ?? 'unknown'),
            'role_name'   => (string) ($account['VaiTro'] ?? 'unknown'),
        ]);

        return (int) ($result['id'] ?? 0);
    }

    private function logMessage(int $sessionId, string $role, string $content, string $source = '', array $actions = [], array $suggestions = [], ?string $actionDraftToken = null): void
    {
        $this->client->post('biz/chatbot/messages', [
            'session_id'         => $sessionId,
            'role_name'          => $role,
            'content'            => $content,
            'source_name'        => $source !== '' ? $source : null,
            'actions'            => !empty($actions) ? array_values($actions) : [],
            'suggestions'        => !empty($suggestions) ? array_values($suggestions) : [],
            'action_draft_token' => $actionDraftToken,
        ]);
    }

    private function createActionDraft(int $sessionId, int $createdBy, array $draft): string
    {
        $result = $this->client->post('biz/chatbot/drafts', [
            'session_id' => $sessionId,
            'created_by' => $createdBy,
            'draft'      => $draft,
        ]);

        return (string) ($result['token'] ?? '');
    }

    private function getPendingActionDraft(string $token, int $currentAccountId): ?object
    {
        try {
            $result = $this->client->get('biz/chatbot/drafts/pending', [
                'token'      => $token,
                'account_id' => $currentAccountId,
            ]);
            $draft = $result['draft'] ?? null;
            return $draft !== null ? (object) $draft : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function callBotService(string $url, array $payload): array
    {
        try {
            $request = Http::acceptJson()->connectTimeout(3)->timeout(20);
            $sharedSecret = trim((string) env('APP_SHARED_SECRET', ''));
            if ($sharedSecret !== '') {
                $request = $request->withHeaders(['X-App-Secret' => $sharedSecret]);
            }

            $response = $request->post($url, $payload);
            if (!$response->successful()) {
                return ['ok' => false, 'error' => 'BOT_HTTP_' . $response->status()];
            }

            $data = $response->json();
            if (!is_array($data)) {
                return ['ok' => false, 'error' => 'INVALID_JSON_RESPONSE'];
            }

            return ['ok' => true, 'data' => $data];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage() !== '' ? $exception->getMessage() : 'BOT_CALL_FAILED'];
        }
    }

    private function chatEndpoint(): string
    {
        return (string) (env('BOT_SERVICE_URL') ?: 'http://127.0.0.1:8001/chat');
    }

    private function briefEndpoint(): string
    {
        return rtrim((string) preg_replace('#/chat$#', '', $this->chatEndpoint()), '/') . '/brief';
    }
}