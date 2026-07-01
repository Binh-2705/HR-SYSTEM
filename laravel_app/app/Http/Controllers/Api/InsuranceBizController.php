<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InsuranceBizController extends Controller
{
    private function conn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    public function deactivate(int $id): JsonResponse
    {
        $affected = DB::connection($this->conn())
            ->table('baohiem')
            ->where('MaBH', $id)
            ->update(['TrangThai' => 'Ngừng']);

        if ($affected === 0) {
            return response()->json(['ok' => false, 'message' => 'Không tìm thấy bảo hiểm.'], 404);
        }

        return response()->json(['ok' => true]);
    }
}
