<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * LegacyRedirectMiddleware
 * ────────────────────────
 * Bắt các URL legacy kiểu ?controller=X&action=Y và redirect 301
 * sang Laravel route tương ứng dựa trên config/legacy_redirect_map.php.
 *
 * Ví dụ:
 *   /?controller=nhanvien&action=index  →  301 → /nhanvien
 *   /?controller=nhanvien&action=sua&MaNV=5  →  301 → /nhanvien/5/edit
 */
class LegacyRedirectMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $controller = $request->query('controller');
        $action     = $request->query('action', 'index');

        // Nếu không có query string legacy, bỏ qua
        if (! $controller) {
            return $next($request);
        }

        $map = config('legacy_redirect_map', []);
        $key = strtolower(trim($controller)) . ':' . strtolower(trim($action));

        if (! isset($map[$key])) {
            // Thử fallback sang action=index
            $fallbackKey = strtolower(trim($controller)) . ':index';
            if (isset($map[$fallbackKey])) {
                $key = $fallbackKey;
            } else {
                // Không tìm thấy mapping cho URL legacy.
                // Chuyển hướng về trang hợp lệ để tránh người dùng rơi vào 404.
                Log::warning('LegacyRedirect: không tìm thấy mapping', [
                    'controller' => $controller,
                    'action'     => $action,
                    'url'        => $request->fullUrl(),
                ]);

                $landingRoute = $request->session()->has('MaTK') ? 'dashboard' : 'login.form';
                return redirect()->route($landingRoute);
            }
        }

        $entry = $map[$key];

        // Entry dạng đơn giản: 'route.name'
        if (is_string($entry)) {
            try {
                return redirect()->route($entry, [], 301);
            } catch (\Exception $e) {
                Log::warning('LegacyRedirect: route không tồn tại', ['route' => $entry]);
                return $next($request);
            }
        }

        // Entry dạng array với idParam / routeKey
        $routeName  = $entry['route']    ?? null;
        $idParam    = $entry['idParam']  ?? 'id';
        $routeKey   = $entry['routeKey'] ?? 'id';
        $id         = $request->query($idParam);

        if (! $routeName) {
            return $next($request);
        }

        try {
            $params = $id ? [$routeKey => $id] : [];
            return redirect()->route($routeName, $params, 301);
        } catch (\Exception $e) {
            Log::warning('LegacyRedirect: route không tồn tại', [
                'route'  => $routeName,
                'params' => $params ?? [],
            ]);
            return $next($request);
        }
    }
}
