<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportBizController extends Controller
{
    private function conn(): string
    {
        return (string) config('service_registry.services.reporting.connection', config('database.default'));
    }

    public function paginate(Request $request): JsonResponse
    {
        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('perPage', 12), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $query = DB::connection($this->conn())->table('baocao')
            ->when(!empty($filters['q']), function (Builder $q) use ($filters) {
                $kw = trim((string) $filters['q']);
                $q->where(fn (Builder $i) => $i->where('TenBaoCao', 'like', "%{$kw}%")->orWhere('NguoiTao', 'like', "%{$kw}%"));
            })
            ->when(!empty($filters['type']), fn (Builder $q) => $q->where('LoaiBaoCao', $filters['type']))
            ->orderByDesc('MaBC');

        $total = (clone $query)->count();
        $data  = $query->forPage($page, $perPage)->get()->map(fn ($r) => (array) $r)->all();

        return response()->json(['ok' => true, 'data' => $data, 'total' => $total, 'per_page' => $perPage, 'current_page' => $page]);
    }

    public function show(int $id): JsonResponse
    {
        $item = DB::connection($this->conn())->table('baocao')->where('MaBC', $id)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Báo cáo không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (array) $item]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = (array) $request->json()->all();
        $id = (int) DB::connection($this->conn())->table('baocao')->insertGetId($payload, 'MaBC');
        return response()->json(['ok' => true, 'id' => $id], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        DB::connection($this->conn())->table('baocao')->where('MaBC', $id)->update((array) $request->json()->all());
        return response()->json(['ok' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        DB::connection($this->conn())->table('baocao')->where('MaBC', $id)->delete();
        return response()->json(['ok' => true]);
    }

    public function export(Request $request): JsonResponse
    {
        $filters = (array) $request->input('filters', []);
        $rows = DB::connection($this->conn())->table('baocao')
            ->when(!empty($filters['q']), function (Builder $q) use ($filters) {
                $kw = trim((string) $filters['q']);
                $q->where(fn (Builder $i) => $i->where('TenBaoCao', 'like', "%{$kw}%")->orWhere('NguoiTao', 'like', "%{$kw}%"));
            })
            ->when(!empty($filters['type']), fn (Builder $q) => $q->where('LoaiBaoCao', $filters['type']))
            ->orderByDesc('MaBC')
            ->get()->map(fn ($r) => (array) $r)->all();

        return response()->json(['ok' => true, 'data' => $rows]);
    }
}
