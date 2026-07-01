<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Chatbot\CreateDraftRequest;
use App\Http\Requests\Api\Chatbot\LogMessageRequest;
use App\Http\Requests\Api\Chatbot\PendingDraftRequest;
use App\Http\Requests\Api\Chatbot\UpdateDraftStatusRequest;
use App\Http\Requests\Api\Chatbot\UpsertSessionRequest;
use App\Http\Resources\Api\Chatbot\ChatbotSessionResource;
use App\Services\LeaveRequestApprovalService;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatbotBizController extends Controller
{
    public function __construct(
        private LeaveRequestApprovalService $leaveRequestApprovalService,
    ) {}

    private function conn(): string
    {
        return (string) config('service_registry.services.chatbot.connection', config('database.default'));
    }

    private function hrConn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    public function paginate(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->input('perPage', 15), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $query = DB::connection($this->conn())
            ->table('chatbot_sessions as cs')
            ->leftJoin('chatbot_messages as cm', 'cm.session_id', '=', 'cs.id')
            ->leftJoin('chatbot_action_drafts as cad', 'cad.session_id', '=', 'cs.id')
            ->select([
                'cs.id', 'cs.session_key', 'cs.ma_tk', 'cs.username', 'cs.role_name',
                'cs.created_at', 'cs.last_interaction_at',
                DB::raw('COUNT(DISTINCT cm.id) as MessageCount'),
                DB::raw('COUNT(DISTINCT cad.id) as DraftCount'),
            ])
            ->groupBy('cs.id', 'cs.session_key', 'cs.ma_tk', 'cs.username', 'cs.role_name', 'cs.created_at', 'cs.last_interaction_at')
            ->orderByDesc('cs.last_interaction_at');

        $total = DB::connection($this->conn())->table('chatbot_sessions')->count();
        $rows = (clone $query)->offset(($page - 1) * $perPage)->limit($perPage)->get();
        $data = ChatbotSessionResource::collection($rows)->resolve();

        return response()->json([
            'ok'           => true,
            'data'         => $data,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $session = DB::connection($this->conn())->table('chatbot_sessions')->where('id', $id)->first();

        if (!$session) {
            return response()->json(['ok' => false, 'message' => 'Phiên không tồn tại.'], 404);
        }

        return response()->json([
            'ok'      => true,
            'session' => (new ChatbotSessionResource($session))->resolve(),
            'messages' => DB::connection($this->conn())->table('chatbot_messages')->where('session_id', $id)->orderBy('created_at')->get()->map(fn ($r) => (array) $r)->all(),
            'drafts'   => DB::connection($this->conn())->table('chatbot_action_drafts')->where('session_id', $id)->orderByDesc('created_at')->get()->map(fn ($r) => (array) $r)->all(),
        ]);
    }

    public function upsertSession(UpsertSessionRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $sessionKey = trim((string) $payload['session_key']);
        $maTk = (int) ($payload['ma_tk'] ?? 0);
        $username = trim((string) ($payload['username'] ?? 'unknown'));
        $roleName = trim((string) ($payload['role_name'] ?? 'unknown'));

        $conn = DB::connection($this->conn());
        $existing = $conn->table('chatbot_sessions')->where('session_key', $sessionKey)->first();

        if ($existing !== null) {
            $conn->table('chatbot_sessions')->where('id', $existing->id)->update([
                'username'            => $username,
                'role_name'           => $roleName,
                'last_interaction_at' => now(),
            ]);

            return response()->json(['ok' => true, 'id' => (int) $existing->id]);
        }

        $id = $conn->table('chatbot_sessions')->insertGetId([
            'session_key'         => $sessionKey,
            'ma_tk'               => $maTk,
            'username'            => $username,
            'role_name'           => $roleName,
            'created_at'          => now(),
            'last_interaction_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function logMessage(LogMessageRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $sessionId = (int) $payload['session_id'];
        $role = trim((string) $payload['role_name']);
        $content = (string) ($payload['content'] ?? '');
        $source = trim((string) ($payload['source_name'] ?? ''));
        $actions = $payload['actions'] ?? [];
        $suggestions = $payload['suggestions'] ?? [];
        $actionDraftToken = $payload['action_draft_token'] ?? null;

        DB::connection($this->conn())->table('chatbot_messages')->insert([
            'session_id'         => $sessionId,
            'role_name'          => $role,
            'content'            => $content,
            'source_name'        => $source !== '' ? $source : null,
            'actions_json'       => !empty($actions) ? json_encode(array_values($actions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'suggestions_json'   => !empty($suggestions) ? json_encode(array_values($suggestions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'action_draft_token' => $actionDraftToken,
            'created_at'         => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function createDraft(CreateDraftRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $sessionId = (int) $payload['session_id'];
        $createdBy = (int) $payload['created_by'];
        $draft = (array) $payload['draft'];

        $token = bin2hex(random_bytes(16));

        DB::connection($this->conn())->table('chatbot_action_drafts')->insert([
            'session_id'          => $sessionId,
            'token'               => $token,
            'action_type'         => (string) ($draft['action_type'] ?? 'unknown'),
            'title'               => (string) ($draft['title'] ?? 'Hành động Chatbot'),
            'summary'             => (string) ($draft['summary'] ?? ''),
            'permission_required' => (string) ($draft['required_permission'] ?? ''),
            'payload_json'        => json_encode($draft['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status_name'         => 'pending',
            'created_by'          => $createdBy,
            'created_at'          => now(),
        ]);

        return response()->json(['ok' => true, 'token' => $token]);
    }

    public function getPendingDraft(PendingDraftRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $token = trim((string) $payload['token']);
        $accountId = (int) $payload['account_id'];

        $draft = DB::connection($this->conn())
            ->table('chatbot_action_drafts')
            ->where('token', $token)
            ->where('status_name', 'pending')
            ->where('created_by', $accountId)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->first();

        if ($draft === null) {
            return response()->json(['ok' => false, 'message' => 'Draft not found or expired.'], 404);
        }

        return response()->json(['ok' => true, 'draft' => (array) $draft]);
    }

    public function updateDraftStatus(UpdateDraftStatusRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();
        $data = array_filter([
            'status_name' => $validated['status_name'] ?? null,
            'confirmed'   => $validated['confirmed'] ?? null,
            'executed_at' => $validated['executed_at'] ?? null,
        ], fn ($v) => $v !== null);

        if (empty($data)) {
            return response()->json(['ok' => false, 'message' => 'Nothing to update.'], 422);
        }

        DB::connection($this->conn())->table('chatbot_action_drafts')->where('id', $id)->update($data);

        return response()->json(['ok' => true]);
    }

    public function executeDraft(Request $request): JsonResponse
    {
        $actionType    = trim((string) $request->input('action_type', ''));
        $payload       = (array) $request->input('payload', []);

        $connection = DB::connection($this->hrConn());

        if ($actionType === 'leave_approve') {
            $leaveId = (int) ($payload['ma_np'] ?? 0);
            if ($leaveId <= 0) {
                return response()->json(['ok' => false, 'message' => 'Thiếu mã đơn nghỉ phép để duyệt.'], 422);
            }

            try {
                $result = $this->leaveRequestApprovalService->approve($leaveId, $request);

                if (($result['ok'] ?? false) && ($result['pending'] ?? false)) {
                    $result['message'] = 'Đã ghi nhận phê duyệt từ chatbot. ' . ($result['message'] ?? '');
                }

                if (($result['ok'] ?? false) && ($result['finalized'] ?? false)) {
                    $result['message'] = 'Đã duyệt đơn nghỉ phép từ chatbot.';
                }

                return response()->json($result, (int) ($result['http_status'] ?? (($result['ok'] ?? false) ? 200 : 422)));
            } catch (\Throwable) {
                return response()->json(['ok' => false, 'message' => 'Không thể duyệt đơn nghỉ phép từ chatbot.'], 500);
            }
        }

        if ($actionType === 'leave_reject') {
            $leaveId = (int) ($payload['ma_np'] ?? 0);
            if ($leaveId <= 0) {
                return response()->json(['ok' => false, 'message' => 'Thiếu mã đơn nghỉ phép để từ chối.'], 422);
            }

            try {
                $result = $this->leaveRequestApprovalService->reject($leaveId, $request);
                if ($result['ok'] ?? false) {
                    $result['message'] = 'Đã từ chối đơn nghỉ phép từ chatbot.';
                }

                return response()->json($result, (int) ($result['http_status'] ?? (($result['ok'] ?? false) ? 200 : 422)));
            } catch (\Throwable) {
                return response()->json(['ok' => false, 'message' => 'Không thể từ chối đơn nghỉ phép từ chatbot.'], 500);
            }
        }

        if ($actionType === 'leave_create') {
            $employeeId = (int) ($payload['ma_nv'] ?? 0);
            $startDate  = trim((string) ($payload['tu_ngay'] ?? ''));
            $endDate    = trim((string) ($payload['den_ngay'] ?? ''));
            $reason     = trim((string) ($payload['ly_do'] ?? ''));
            $leaveType  = trim((string) ($payload['loai_nghi'] ?? 'Nghỉ phép năm'));

            if ($employeeId <= 0 || $startDate === '' || $endDate === '') {
                return response()->json(['ok' => false, 'message' => 'Thiếu dữ liệu để tạo đơn nghỉ phép.'], 422);
            }

            try {
                $start = new DateTime($startDate);
                $end   = new DateTime($endDate);
            } catch (\Throwable) {
                return response()->json(['ok' => false, 'message' => 'Ngày nghỉ phép không hợp lệ.'], 422);
            }

            if ($end < $start) {
                return response()->json(['ok' => false, 'message' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.'], 422);
            }

            $days  = (int) $start->diff($end)->days + 1;
            $newId = $connection->table('nghiphep')->insertGetId([
                'MaNV'       => $employeeId,
                'TuNgay'     => $startDate,
                'DenNgay'    => $endDate,
                'SoNgayNghi' => $days,
                'LyDo'       => $reason,
                'LoaiNghi'   => $leaveType,
            ]);

            return response()->json(['ok' => true, 'message' => 'Đã tạo đơn nghỉ phép từ chatbot.', 'state_diff' => 'Mã đơn mới: #' . $newId . ' | Trạng thái: Chờ duyệt']);
        }

        if ($actionType === 'contract_extend') {
            $contractId = (int) ($payload['ma_hop_dong'] ?? 0);
            $newEndDate = trim((string) ($payload['new_end_date'] ?? ''));

            if ($contractId <= 0 || $newEndDate === '') {
                return response()->json(['ok' => false, 'message' => 'Thiếu dữ liệu để gia hạn hợp đồng.'], 422);
            }

            $contract = $connection->table('hopdong')->where('MaHopDong', $contractId)->first();
            if ($contract === null) {
                return response()->json(['ok' => false, 'message' => 'Không tìm thấy hợp đồng.'], 404);
            }
            if (trim((string) ($contract->TrangThai ?? '')) !== 'Còn hiệu lực') {
                return response()->json(['ok' => false, 'message' => 'Chỉ gia hạn được hợp đồng còn hiệu lực.'], 422);
            }
            if (empty($contract->NgayKetThuc)) {
                return response()->json(['ok' => false, 'message' => 'Hợp đồng không xác định thời hạn không cần gia hạn theo cách này.'], 422);
            }

            try {
                $currentEnd = new DateTime((string) $contract->NgayKetThuc);
                $newEnd     = new DateTime($newEndDate);
            } catch (\Throwable) {
                return response()->json(['ok' => false, 'message' => 'Ngày gia hạn hợp đồng không hợp lệ.'], 422);
            }

            if ($newEnd <= $currentEnd) {
                return response()->json(['ok' => false, 'message' => 'Ngày kết thúc mới phải lớn hơn ngày kết thúc hiện tại.'], 422);
            }

            $newStartDate     = (clone $currentEnd)->modify('+1 day')->format('Y-m-d');
            $baseNumber       = trim((string) ($contract->SoHopDong ?? ''));
            $baseNumber       = $baseNumber !== '' ? $baseNumber : 'HD';
            $suffix           = $newEnd->format('Ymd');
            $candidate        = $baseNumber . '-GH-' . $suffix;
            $counter          = 1;
            while ($connection->table('hopdong')->where('SoHopDong', $candidate)->exists()) {
                $candidate = $baseNumber . '-GH-' . $suffix . '-' . $counter;
                $counter++;
            }

            $newContractId = $connection->table('hopdong')->insertGetId([
                'MaNV'         => (int) ($contract->MaNV ?? 0),
                'MaBac'        => (int) ($contract->MaBac ?? 0),
                'SoHopDong'    => $candidate,
                'LoaiHopDong'  => (string) ($contract->LoaiHopDong ?? 'Xác định thời hạn'),
                'NgayKy'       => now()->toDateString(),
                'NgayBatDau'   => $newStartDate,
                'NgayKetThuc'  => $newEnd->format('Y-m-d'),
                'TrangThai'    => 'Còn hiệu lực',
                'HopDongGoc'   => $contractId,
            ]);

            return response()->json(['ok' => true, 'message' => 'Đã tạo hợp đồng gia hạn từ chatbot.', 'state_diff' => 'Hợp đồng mới: #' . $newContractId . ' | Số HĐ: ' . $candidate]);
        }

        return response()->json(['ok' => false, 'message' => 'Loại hành động này chưa được hỗ trợ thực thi.'], 422);
    }
}
