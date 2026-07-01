<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class AccountSecurityService
{
    public function __construct(private InternalApiClient $client) {}

    private function connection(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    private function sessionVersionKey(int $maTK): string
    {
        return 'api:account:sessions:version:' . $maTK;
    }

    private function bumpSessionVersion(int $maTK): void
    {
        $key = $this->sessionVersionKey($maTK);

        try {
            if (!Cache::has($key)) {
                Cache::forever($key, 1);
            }

            Cache::increment($key);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function getById(int $maTK): ?array
    {
        try {
            return $this->client->get("biz/accounts/{$maTK}")['data'] ?? null;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return null;
        } catch (Throwable) {
            return null;
        }
    }

    public function getByUsername(string $username): ?array
    {
        $normalizedUsername = trim($username);

        $row = DB::connection($this->connection())
            ->table('taikhoan as tk')
            ->leftJoin('taikhoanvaitro as tkvt', 'tk.MaTK', '=', 'tkvt.MaTK')
            ->leftJoin('vaitro as vt', 'tkvt.MaVaiTro', '=', 'vt.MaVaiTro')
            ->select('tk.*', DB::raw("COALESCE(vt.TenVaiTro, 'NhanVien') as VaiTro"))
            ->where('tk.TenDangNhap', $normalizedUsername)
            ->first();

        if ($row) {
            return (array) $row;
        }

        return null;
    }

    public function isUsernameAvailable(string $username, int $excludeMaTK = 0): bool
    {
        return (bool) $this->client->get('biz/accounts/check-username', ['username' => $username, 'exclude_ma_tk' => $excludeMaTK])['available'];
    }

    public function updateUsername(int $maTK, string $newUsername): bool
    {
        return (bool) ($this->client->patch("biz/accounts/{$maTK}/username", ['TenDangNhap' => $newUsername])['affected'] ?? 0);
    }

    public function updatePassword(int $maTK, string $newHash, bool $forceChange = false): bool
    {
        return (bool) ($this->client->patch("biz/accounts/{$maTK}/password", ['MatKhau' => $newHash, 'BuocDoiMatKhau' => $forceChange])['affected'] ?? 0);
    }

    public function isPasswordChangeRequired(int $maTK): bool
    {
        $account = $this->getById($maTK);
        return (bool) ($account['BuocDoiMatKhau'] ?? false);
    }

    public function findAccountForInternalRecovery(string $username, string $employeeCode, string $birthDate, string $phoneSuffix): ?array
    {
        $account = $this->getByUsername($username);
        if (!$account) {
            return null;
        }

        try {
            $employeeData = $this->client->get('biz/accounts/employee-for-account', ['ma_tk' => $account['MaTK']]);
            $employee = $employeeData['data'] ?? null;
        } catch (Throwable) {
            return null;
        }

        if (!$employee) {
            return null;
        }

        $allowedCodes = array_unique(array_filter([
            strtoupper(trim((string) ($account['MaNV'] ?? ''))),
            strtoupper(trim((string) ($employee['MaNV'] ?? ''))),
        ]));

        if (!in_array(strtoupper(trim($employeeCode)), $allowedCodes, true)) {
            return null;
        }

        if (substr((string) ($employee['NgaySinh'] ?? ''), 0, 10) !== trim($birthDate)) {
            return null;
        }

        $storedPhone = preg_replace('/\D+/', '', (string) ($employee['DienThoai'] ?? ''));
        $providedPhone = substr(preg_replace('/\D+/', '', $phoneSuffix), -4);
        if ($storedPhone === '' || substr($storedPhone, -4) !== $providedPhone) {
            return null;
        }

        return ['account' => $account, 'employee' => $employee];
    }

    public function createPasswordResetToken(int $maTK): string
    {
        return (string) ($this->client->post('biz/accounts/reset-token', ['MaTK' => $maTK])['token'] ?? '');
    }

    public function findValidResetToken(string $rawToken): ?array
    {
        try {
            return $this->client->get('biz/accounts/reset-token/find', ['token' => $rawToken])['data'] ?? null;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return null;
        } catch (Throwable) {
            return null;
        }
    }

    public function markResetTokenUsed(int $id): void
    {
        try {
            $this->client->post("biz/accounts/reset-token/{$id}/used");
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function registerSessionAudit(int $maTK, string $marker, string $userAgent = '', string $ipAddress = ''): void
    {
        try {
            DB::connection($this->connection())->table('session_audit')->updateOrInsert(
                ['MaTK' => $maTK, 'session_marker' => substr($marker, 0, 64)],
                [
                    'user_agent' => substr($userAgent, 0, 255),
                    'ip_address' => substr($ipAddress, 0, 45),
                    'last_activity' => now(),
                    'revoked_at' => null,
                ]
            );
            $this->bumpSessionVersion($maTK);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function touchSessionAudit(int $maTK, string $marker): void
    {
        try {
            DB::connection($this->connection())->table('session_audit')
                ->where('MaTK', $maTK)
                ->where('session_marker', substr($marker, 0, 64))
                ->update(['last_activity' => now()]);
            $this->bumpSessionVersion($maTK);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function revokeOtherSessions(int $maTK, string $currentMarker): void
    {
        try {
            DB::connection($this->connection())->table('session_audit')
                ->where('MaTK', $maTK)
                ->where('session_marker', '<>', substr($currentMarker, 0, 64))
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
            $this->bumpSessionVersion($maTK);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function revokeCurrentSession(int $maTK, string $marker): void
    {
        try {
            DB::connection($this->connection())->table('session_audit')
                ->where('MaTK', $maTK)
                ->where('session_marker', substr($marker, 0, 64))
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
            $this->bumpSessionVersion($maTK);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function isSessionRevoked(int $maTK, string $marker): bool
    {
        try {
            return DB::connection($this->connection())->table('session_audit')
                ->where('MaTK', $maTK)
                ->where('session_marker', substr($marker, 0, 64))
                ->whereNotNull('revoked_at')
                ->exists();
        } catch (Throwable $e) {
            report($e);
            return false;
        }
    }

    public function getRecentSessions(int $maTK): array
    {
        try {
            return DB::connection($this->connection())->table('session_audit')
                ->where('MaTK', $maTK)
                ->orderByDesc('last_activity')
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (Throwable $e) {
            report($e);
            return [];
        }
    }
}
