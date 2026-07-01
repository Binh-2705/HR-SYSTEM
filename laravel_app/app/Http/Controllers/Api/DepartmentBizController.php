<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentBizController extends Controller
{
    private function conn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    public function paginate(Request $request): JsonResponse
    {
        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('perPage', 12), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $query = DB::connection($this->conn())
            ->table('phongban as pb')
            ->leftJoin('hosonhanvien as hs', 'pb.MaPB', '=', 'hs.MaPB')
            ->select(['pb.MaPB', 'pb.TenPB', 'pb.MoTa', DB::raw('COUNT(DISTINCT hs.MaNV) as SoNhanVien')])
            ->when(!empty($filters['q']), function (Builder $q) use ($filters) {
                $kw = trim((string) $filters['q']);
                $q->where(fn (Builder $i) => $i->where('pb.TenPB', 'like', "%{$kw}%")->orWhere('pb.MoTa', 'like', "%{$kw}%"));
            })
            ->groupBy('pb.MaPB', 'pb.TenPB', 'pb.MoTa')
            ->orderBy('pb.MaPB');

        $total = (clone $query)->select(DB::raw('COUNT(DISTINCT pb.MaPB)'))->value(DB::raw('COUNT(DISTINCT pb.MaPB)')) ?? 0;
        $data  = $query->forPage($page, $perPage)->get()->map(fn ($r) => (array) $r)->all();

        return response()->json(['ok' => true, 'data' => $data, 'total' => (int) $total, 'per_page' => $perPage, 'current_page' => $page]);
    }

    public function show(int $id): JsonResponse
    {
        $item = DB::connection($this->conn())->table('phongban')->where('MaPB', $id)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Phòng ban không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (array) $item]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = (array) $request->json()->all();
        $id = (int) DB::connection($this->conn())->table('phongban')->insertGetId([
            'TenPB' => $payload['TenPB'],
            'MoTa'  => $payload['MoTa'] ?? null,
        ], 'MaPB');

        return response()->json(['ok' => true, 'id' => $id], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $payload = (array) $request->json()->all();
        DB::connection($this->conn())->table('phongban')->where('MaPB', $id)->update([
            'TenPB' => $payload['TenPB'],
            'MoTa'  => $payload['MoTa'] ?? null,
        ]);
        return response()->json(['ok' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        DB::connection($this->conn())->table('phongban')->where('MaPB', $id)->delete();
        return response()->json(['ok' => true]);
    }

    public function export(Request $request): JsonResponse
    {
        $filters = (array) $request->input('filters', []);
        $rows = DB::connection($this->conn())
            ->table('phongban as pb')
            ->when(!empty($filters['q']), function (Builder $q) use ($filters) {
                $kw = trim((string) $filters['q']);
                $q->where(fn (Builder $i) => $i->where('pb.TenPB', 'like', "%{$kw}%")->orWhere('pb.MoTa', 'like', "%{$kw}%"));
            })
            ->orderBy('pb.MaPB')
            ->get(['pb.MaPB', 'pb.TenPB', 'pb.MoTa'])
            ->map(fn ($r) => (array) $r)->all();

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function import(Request $request): JsonResponse
    {
        $rows = (array) $request->input('rows', []);
        $count = 0;

        DB::connection($this->conn())->transaction(function () use ($rows, &$count) {
            $conn = DB::connection($this->conn());
            foreach ($rows as $row) {
                $name = trim((string) ($row['TenPB'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $conn->table('phongban')->updateOrInsert(
                    ['TenPB' => $name],
                    ['TenPB' => $name, 'MoTa' => trim((string) ($row['MoTa'] ?? '')) ?: null]
                );
                $count++;
            }
        });

        return response()->json(['ok' => true, 'count' => $count]);
    }
}
