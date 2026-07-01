<?php

namespace App\Http\Middleware;

use App\Services\AccountSecurityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SessionAuthMiddleware
{
    public function __construct(private AccountSecurityService $securityService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->has('MaTK')) {
            return redirect()->route('login.form')
                ->withErrors(['auth' => 'Ban can dang nhap truoc.']);
        }

        $maTK = (int) $request->session()->get('MaTK', 0);
        $marker = (string) $request->session()->get('session_marker', '');

        // Kiểm tra session bị thu hồi — cache 2 phút, tránh query DB mỗi request
        if ($maTK > 0 && $marker !== '') {
            $revokeCacheKey = "session_revoked_{$maTK}_" . substr(md5($marker), 0, 16);
            $isRevoked = Cache::remember($revokeCacheKey, 120, function () use ($maTK, $marker) {
                return $this->securityService->isSessionRevoked($maTK, $marker);
            });

            if ($isRevoked) {
                Cache::forget($revokeCacheKey);
                $request->session()->flush();
                return redirect()->route('login.form')
                    ->withErrors(['auth' => 'Phien dang nhap nay da bi thu hoi.']);
            }
        }

        // Cập nhật last_activity tối đa 1 lần/60 giây — tránh UPDATE DB mỗi request
        if ($maTK > 0 && $marker !== '') {
            $touchKey = "session_touched_{$maTK}_" . substr(md5($marker), 0, 16);
            if (!Cache::has($touchKey)) {
                $this->securityService->touchSessionAudit($maTK, $marker);
                Cache::put($touchKey, true, 60);
            }
        }

        // Kiểm tra đổi mật khẩu — dùng session, không query DB mỗi request
        $mustChangePassword = (bool) $request->session()->get('must_change_password', false)
            || !empty(data_get($request->session()->get('taikhoan', []), 'BuocDoiMatKhau'));

        if ($maTK > 0 && $request->route()?->getName() !== 'password.force' && $request->route()?->getName() !== 'password.force.submit' && $mustChangePassword) {
            return redirect()->route('password.force');
        }

        return $next($request);
    }
}
