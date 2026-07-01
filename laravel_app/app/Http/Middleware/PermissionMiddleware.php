<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;

class PermissionMiddleware
{
    public function __construct(private PermissionService $permissionService)
    {
    }

    public function handle(Request $request, Closure $next, string $permission)
    {
        $maTK = (int) $request->session()->get('MaTK', 0);

        if ($maTK <= 0) {
            return redirect()->route('login.form')
                ->withErrors(['auth' => 'Ban can dang nhap truoc.']);
        }

        $requiredPermissions = array_values(array_filter(array_map('trim', explode('|', $permission))));
        if ($requiredPermissions === []) {
            $requiredPermissions = [$permission];
        }

        // Dùng quyền đã load trong session — không query DB
        $sessionPermissions = (array) $request->session()->get('quyen', []);
        foreach ($requiredPermissions as $requiredPermission) {
            if (in_array($requiredPermission, $sessionPermissions, true)) {
                return $next($request);
            }
        }

        // Fallback: query DB (cache 5 phút) nếu session chưa có quyền
        foreach ($requiredPermissions as $requiredPermission) {
            if ($this->permissionService->hasPermissionFromCache($maTK, $requiredPermission)) {
                return $next($request);
            }
        }

        abort(403, 'Ban khong co quyen truy cap chuc nang nay.');
    }
}
