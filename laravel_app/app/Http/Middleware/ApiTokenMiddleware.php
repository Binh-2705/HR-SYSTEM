<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $configuredToken = (string) config('services.service_gateway.token', '');

        if ($configuredToken === '') {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Service gateway token is not configured.',
            ], 503);
        }

        $providedToken = (string) ($request->bearerToken() ?? $request->header('X-Service-Token', ''));

        if ($providedToken === '' || !hash_equals($configuredToken, $providedToken)) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Unauthorized service request.',
            ], 401);
        }

        return $next($request);
    }
}
/*
File này là "bảo vệ cổng vào" cho toàn bộ API nội bộ /api/biz/*: ApiTokenMiddleware.php

Luồng xử lý
Chi tiết từng phần
config('services.service_gateway.token')
Token bí mật lưu trong file .env, ví dụ:

hash_equals($configuredToken, $providedToken)
Dùng thay vì === để chống timing attack — hacker không thể đoán token bằng cách đo thời gian so sánh.

2 cách gửi token:

Vai trò trong project
Middleware này được gán cho toàn bộ group /api/biz/* trong api.php:

Điều này có nghĩa là các BizController (Attendance, Payroll, Contract...) không thể bị gọi trực tiếp từ trình duyệt — chỉ có InternalApiClient (với token đúng) mới được phép gọi vào.
*/