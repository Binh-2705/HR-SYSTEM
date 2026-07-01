<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AccountBizController extends Controller
{
    private function conn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    public function show(int $id): JsonResponse //	Lấy tài khoản theo MaTK, kèm tên vai trò (JOIN 3 bảng)
    {
        $ttlSeconds = max(5, (int) env('API_ACCOUNT_LOOKUP_CACHE_TTL', 60));
        $payload = $this->rememberCache(
            'api:account:show:v' . $this->accountLookupVersion() . ':id:' . $id,
            function () use ($id): array {
                $row = DB::connection($this->conn())
                    ->table('taikhoan as tk')
                    ->leftJoin('taikhoanvaitro as tkvt', 'tk.MaTK', '=', 'tkvt.MaTK')
                    ->leftJoin('vaitro as vt', 'tkvt.MaVaiTro', '=', 'vt.MaVaiTro')
                    ->select('tk.*', DB::raw("COALESCE(vt.TenVaiTro, 'NhanVien') as VaiTro"))
                    ->where('tk.MaTK', $id)
                    ->first();

                if (!$row) {
                    return ['found' => false];
                }

                return ['found' => true, 'data' => (array) $row];
            },
            $ttlSeconds
        );

        if (!(bool) ($payload['found'] ?? false)) {
            return response()->json(['ok' => false, 'message' => 'Tài khoản không tồn tại.'], 404);
        }

        return response()->json(['ok' => true, 'data' => $payload['data']]);
    }

    public function showByUsername(Request $request): JsonResponse  //	Lấy tài khoản theo TenDangNhap 
    {
        $username = trim((string) $request->query('username', ''));
        $ttlSeconds = max(5, (int) env('API_ACCOUNT_LOOKUP_CACHE_TTL', 60));
        $cacheKey = 'api:account:show-username:v' . $this->accountLookupVersion() . ':' . md5(mb_strtolower($username));

        $payload = $this->rememberCache(
            $cacheKey,
            function () use ($username): array {
                $row = DB::connection($this->conn())
                    ->table('taikhoan as tk')
                    ->leftJoin('taikhoanvaitro as tkvt', 'tk.MaTK', '=', 'tkvt.MaTK')
                    ->leftJoin('vaitro as vt', 'tkvt.MaVaiTro', '=', 'vt.MaVaiTro')
                    ->select('tk.*', DB::raw("COALESCE(vt.TenVaiTro, 'NhanVien') as VaiTro"))
                    ->where('tk.TenDangNhap', $username)
                    ->first();

                if (!$row) {
                    return ['found' => false];
                }

                return ['found' => true, 'data' => (array) $row];
            },
            $ttlSeconds
        );

        if (!(bool) ($payload['found'] ?? false)) {
            return response()->json(['ok' => false, 'message' => 'Tài khoản không tồn tại.'], 404);
        }

        return response()->json(['ok' => true, 'data' => $payload['data']]);
    }

    public function checkUsernameAvailable(Request $request): JsonResponse //Kiểm tra tên đăng nhập có bị trùng không, hỗ trợ exclude_ma_tk để bỏ qua chính mình khi đổi tên 
    {
        $username    = trim((string) $request->query('username', ''));
        $excludeMaTK = (int) $request->query('exclude_ma_tk', 0);
        $ttlSeconds = max(5, (int) env('API_ACCOUNT_LOOKUP_CACHE_TTL', 60));
        $cacheKey = 'api:account:username-available:v' . $this->accountLookupVersion() . ':' . md5(json_encode([
            'username' => mb_strtolower($username),
            'exclude_ma_tk' => $excludeMaTK,
        ]));

        $payload = $this->rememberCache(
            $cacheKey,
            function () use ($username, $excludeMaTK): array {
                $query = DB::connection($this->conn())->table('taikhoan')->where('TenDangNhap', $username);
                if ($excludeMaTK > 0) {
                    $query->where('MaTK', '<>', $excludeMaTK);
                }

                return ['available' => !$query->exists()];
            },
            $ttlSeconds
        );

        return response()->json(['ok' => true, 'available' => (bool) ($payload['available'] ?? false)]);
    }

    public function updateUsername(Request $request, int $id): JsonResponse 
    {
        $username = trim((string) $request->input('TenDangNhap', ''));
        $affected = DB::connection($this->conn())->table('taikhoan')->where('MaTK', $id)->update(['TenDangNhap' => $username]);
        if ($affected > 0) {
            $this->bumpAccountLookupVersion();
        }
        return response()->json(['ok' => true, 'affected' => $affected]);
    }

    public function updatePassword(Request $request, int $id): JsonResponse
    {
        $payload = (array) $request->json()->all();
        $affected = DB::connection($this->conn())->table('taikhoan')->where('MaTK', $id)->update([
            'MatKhau'            => $payload['MatKhau'],
            'BuocDoiMatKhau'     => (bool) ($payload['BuocDoiMatKhau'] ?? false) ? 1 : 0,
            'NgayCapMatKhauTam'  => !empty($payload['BuocDoiMatKhau']) ? now() : null,
        ]);
        if ($affected > 0) {
            $this->bumpAccountLookupVersion();
        }
        return response()->json(['ok' => true, 'affected' => $affected]);
    }

    public function findEmployeeForAccount(Request $request): JsonResponse //Tìm bản ghi nhân viên liên kết với tài khoản (theo MaNV hoặc Email)
    {
        $maTK = (int) $request->query('ma_tk', 0);
        $ttlSeconds = max(5, (int) env('API_ACCOUNT_LOOKUP_CACHE_TTL', 60));

        $payload = $this->rememberCache(
            'api:account:employee-by-account:v' . $this->accountLookupVersion() . ':ma_tk:' . $maTK,
            function () use ($maTK): array {
                $conn = DB::connection($this->conn());

                $account = $conn->table('taikhoan')->where('MaTK', $maTK)->first();
                if (!$account) {
                    return ['found' => false, 'message' => 'Tài khoản không tồn tại.'];
                }

                $employee = $conn->table('nhanvien')
                    ->where(fn ($q) => $q->where('MaNV', $account->MaNV ?? 0)->orWhere('Email', $account->TenDangNhap))
                    ->first();

                if (!$employee) {
                    return ['found' => false, 'message' => 'Không tìm thấy nhân viên.'];
                }

                return ['found' => true, 'data' => (array) $employee];
            },
            $ttlSeconds
        );

        if (!(bool) ($payload['found'] ?? false)) {
            return response()->json(['ok' => false, 'message' => $payload['message'] ?? 'Không tìm thấy nhân viên.'], 404);
        }

        return response()->json(['ok' => true, 'data' => $payload['data']]);
    }

    // ─── Session audit ───────────────────────────────────────────────────────

    public function registerSession(Request $request): JsonResponse  //Đăng ký hoặc cập nhật phiên đăng nhập (updateOrInsert theo marker)
    {
        $payload = (array) $request->json()->all();
        $maTK = (int) $payload['MaTK'];
        DB::connection($this->conn())->table('session_audit')->updateOrInsert(
            ['MaTK' => $maTK, 'session_marker' => substr((string) $payload['session_marker'], 0, 64)],
            [
                'user_agent'    => substr((string) ($payload['user_agent'] ?? ''), 0, 255),
                'ip_address'    => substr((string) ($payload['ip_address'] ?? ''), 0, 45),
                'last_activity' => now(),
                'revoked_at'    => null,
            ]
        );
        $this->bumpAccountSessionVersion($maTK);
        return response()->json(['ok' => true]);
    }

    public function touchSession(Request $request): JsonResponse //Cập nhật last_activity của phiên đăng nhập (theo marker)
    {
        $payload = (array) $request->json()->all();
        $maTK = (int) $payload['MaTK'];
        DB::connection($this->conn())->table('session_audit')
            ->where('MaTK', $maTK)
            ->where('session_marker', substr((string) $payload['session_marker'], 0, 64))
            ->update(['last_activity' => now()]);
        $this->bumpAccountSessionVersion($maTK);
        return response()->json(['ok' => true]);
    }

    public function revokeOtherSessions(Request $request): JsonResponse //Đánh dấu revoked_at cho tất cả phiên khác (trừ marker hiện tại)
    {
        $payload = (array) $request->json()->all();
        $maTK = (int) $payload['MaTK'];
        DB::connection($this->conn())->table('session_audit')
            ->where('MaTK', $maTK)
            ->where('session_marker', '<>', substr((string) $payload['current_marker'], 0, 64))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
        $this->bumpAccountSessionVersion($maTK);
        return response()->json(['ok' => true]);
    }

    public function revokeCurrentSession(Request $request): JsonResponse //Đánh dấu revoked_at cho phiên hiện tại
    {
        $payload = (array) $request->json()->all();
        $maTK = (int) $payload['MaTK'];
        DB::connection($this->conn())->table('session_audit')
            ->where('MaTK', $maTK)
            ->where('session_marker', substr((string) $payload['session_marker'], 0, 64))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
        $this->bumpAccountSessionVersion($maTK);
        return response()->json(['ok' => true]);
    }

    public function isSessionRevoked(Request $request): JsonResponse //Kiểm tra một marker có bị thu hồi chưa → dùng trong middleware auth
    {
        $maTK   = (int) $request->query('ma_tk', 0);
        $marker = substr((string) $request->query('session_marker', ''), 0, 64);

        $revoked = DB::connection($this->conn())->table('session_audit')
            ->where('MaTK', $maTK)->where('session_marker', $marker)->whereNotNull('revoked_at')->exists();

        return response()->json(['ok' => true, 'revoked' => $revoked]);
    }

    // ─── Password reset tokens ───────────────────────────────────────────────

    public function createResetToken(Request $request): JsonResponse //Tạo token đặt lại mật khẩu
    {
        $maTK     = (int) $request->input('MaTK', 0);
        $rawToken = Str::random(64);
        $conn     = DB::connection($this->conn());

        $conn->table('password_reset_tokens')
            ->where('MaTK', $maTK)->whereNull('used_at')->update(['used_at' => now()]);

        $conn->table('password_reset_tokens')->insert([
            'MaTK'       => $maTK,
            'token_hash' => password_hash($rawToken, PASSWORD_DEFAULT),
            'expires_at' => now()->addMinutes(30),
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true, 'token' => $rawToken]);
    }

    public function findValidResetToken(Request $request): JsonResponse //Tìm token hợp lệ: lấy toàn bộ token chưa dùng/chưa hết hạn rồi password_verify() từng cái
    {
        $rawToken = (string) $request->query('token', '');
        $rows = DB::connection($this->conn())->table('password_reset_tokens')
            ->whereNull('used_at')->where('expires_at', '>', now())->get();

        foreach ($rows as $row) {
            if (password_verify($rawToken, (string) $row->token_hash)) {
                return response()->json(['ok' => true, 'data' => (array) $row]);
            }
        }

            return response()->json(['ok' => false, 'message' => 'Token không hợp lệ.'], 404);
    }

    public function markResetTokenUsed(int $id): JsonResponse //Đánh dấu token đặt lại mật khẩu đã sử dụng
    {
        DB::connection($this->conn())->table('password_reset_tokens')->where('id', $id)->update(['used_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function listSessions(int $id): JsonResponse //Liệt kê các phiên đăng nhập gần đây của tài khoản (để hiển thị trong phần quản lý phiên đăng nhập)
    {
        $ttlSeconds = max(5, (int) env('API_ACCOUNT_SESSION_CACHE_TTL', 15));

        $payload = $this->rememberCache(
            'api:account:list-sessions:v' . $this->accountSessionVersion($id) . ':ma_tk:' . $id,
            function () use ($id): array {
                $rows = DB::connection($this->conn())->table('session_audit')
                    ->where('MaTK', $id)
                    ->orderByDesc('last_activity')
                    ->limit(20)
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->all();

                return ['data' => $rows];
            },
            $ttlSeconds
        );

        return response()->json(['ok' => true, 'data' => (array) ($payload['data'] ?? [])]);
    }

    private function accountLookupVersion(): int
    {
        try {
            return (int) Cache::get('api:account:lookup:version', 1);
        } catch (Throwable $exception) {
            Log::warning('Account cache version read failed', ['error' => $exception->getMessage()]);
            return (int) Cache::store((string) env('API_LIST_CACHE_FALLBACK_STORE', 'file'))->get('api:account:lookup:version', 1);
        }
    }

    private function accountSessionVersion(int $maTK): int
    {
        $key = 'api:account:sessions:version:' . $maTK;

        try {
            return (int) Cache::get($key, 1);
        } catch (Throwable $exception) {
            Log::warning('Account session cache version read failed', ['key' => $key, 'error' => $exception->getMessage()]);
            return (int) Cache::store((string) env('API_LIST_CACHE_FALLBACK_STORE', 'file'))->get($key, 1);
        }
    }

    private function bumpAccountLookupVersion(): void
    {
        $this->bumpCounter('api:account:lookup:version');
    }

    private function bumpAccountSessionVersion(int $maTK): void
    {
        $this->bumpCounter('api:account:sessions:version:' . $maTK);
    }

    private function bumpCounter(string $key): void
    {
        try {
            if (!Cache::has($key)) {
                Cache::forever($key, 1);
            }

            Cache::increment($key);
            return;
        } catch (Throwable $exception) {
            Log::warning('Account cache bump failed', ['key' => $key, 'error' => $exception->getMessage()]);
        }

        $fallback = Cache::store((string) env('API_LIST_CACHE_FALLBACK_STORE', 'file'));
        if (!$fallback->has($key)) {
            $fallback->forever($key, 1);
        }

        $fallback->increment($key);
    }

    private function rememberCache(string $key, callable $resolver, int $ttlSeconds): array
    {
        try {
            return Cache::remember($key, $ttlSeconds, $resolver);
        } catch (Throwable $exception) {
            Log::warning('Account cache remember failed', ['key' => $key, 'error' => $exception->getMessage()]);
        }

        try {
            return Cache::store((string) env('API_LIST_CACHE_FALLBACK_STORE', 'file'))->remember($key, $ttlSeconds, $resolver);
        } catch (Throwable $exception) {
            Log::warning('Account fallback cache remember failed', ['key' => $key, 'error' => $exception->getMessage()]);
            return $resolver();
        }
    }
}
