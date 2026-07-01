<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeBizController extends Controller
{
    private function conn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    private function baseQuery(): Builder
    {
        return DB::connection($this->conn())
            ->table('nhanvien as nv')
            ->leftJoin('bacluong as bl', 'nv.MaBac', '=', 'bl.MaBac')
            ->leftJoin('ngachluong as nl', 'bl.MaNgach', '=', 'nl.MaNgach')
            ->leftJoin('hosonhanvien as hs', 'nv.MaNV', '=', 'hs.MaNV')
            ->leftJoinSub(
                DB::connection($this->conn())->table('phancong as pc1')
                    ->select('pc1.MaNV', DB::raw('MAX(pc1.MaQT) as LatestAssignmentId'))
                    ->groupBy('pc1.MaNV'),
                'latest_pc',
                fn ($join) => $join->on('latest_pc.MaNV', '=', 'nv.MaNV')
            )
            ->leftJoin('phancong as pc', 'pc.MaQT', '=', 'latest_pc.LatestAssignmentId')
            ->leftJoin('phongban as pb', 'pb.MaPB', '=', 'pc.MaPB')
            ->leftJoin('chucvu as cv', 'cv.MaCV', '=', 'pc.MaCV')
            ->select([
                'nv.MaNV', 'nv.HoTen', 'nv.GioiTinh', 'nv.NgaySinh',
                'nv.Email', 'nv.DienThoai', 'nv.TrangThai', 'nv.MaBac',
                'bl.TenBac', 'nl.TenNgach', 'hs.DiaChi', 'hs.NgayVaoLam',
                'pb.MaPB as CurrentMaPB', 'pb.TenPB', 'cv.MaCV as CurrentMaCV', 'cv.TenCV',
            ]);
    }

    public function paginate(Request $request): JsonResponse
    {
        $filters = (array) $request->input('filters', []);
        $perPage = max(1, min((int) $request->input('perPage', 12), 100));
        $page    = max(1, (int) $request->input('page', 1));

        $query = $this->baseQuery()
            ->when(!empty($filters['ma_nv']), fn (Builder $q) => $q->where('nv.MaNV', (int) $filters['ma_nv']))
            ->when(!empty($filters['q']), function (Builder $q) use ($filters) {
                $kw = trim((string) $filters['q']);
                $q->where(fn (Builder $i) => $i
                    ->where('nv.HoTen', 'like', "%{$kw}%")
                    ->orWhere('nv.Email', 'like', "%{$kw}%")
                    ->orWhere('nv.DienThoai', 'like', "%{$kw}%")
                    ->orWhere('nv.MaNV', 'like', "%{$kw}%")
                );
            })
            ->when(!empty($filters['status']), fn (Builder $q) => $q->where('nv.TrangThai', $filters['status']))
            ->when(!empty($filters['department']), fn (Builder $q) => $q->where('pb.MaPB', (int) $filters['department']))
            ->orderBy('nv.MaNV');

        $total = (clone $query)->count();
        $data  = $query->forPage($page, $perPage)->get()->map(fn ($r) => (array) $r)->all();

        return response()->json(['ok' => true, 'data' => $data, 'total' => $total, 'per_page' => $perPage, 'current_page' => $page]);
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->baseQuery()->where('nv.MaNV', $id)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Nhân viên không tồn tại.'], 404);
        }
        return response()->json(['ok' => true, 'data' => (array) $item]);
    }

    public function options(): JsonResponse
    {
        $conn = DB::connection($this->conn());
        return response()->json([
            'ok' => true,
            'departments'  => $conn->table('phongban')->orderBy('TenPB')->get(),
            'positions'    => $conn->table('chucvu')->orderBy('TenCV')->get(),
            'salaryGrades' => $conn->table('bacluong as b')
                ->leftJoin('ngachluong as n', 'b.MaNgach', '=', 'n.MaNgach')
                ->select('b.MaBac', 'b.TenBac', 'b.HeSoLuong', 'b.LuongCoSo', 'n.TenNgach')
                ->orderBy('n.TenNgach')
                ->orderBy('b.HeSoLuong')
                ->get(),
        ]);
    }

    public function salaryGrades(Request $request): JsonResponse
    {
        $bandId = $request->query('ma_ngach');
        $grades = DB::connection($this->conn())
            ->table('bacluong')
            ->when($bandId !== null && $bandId !== '', fn (Builder $q) => $q->where('MaNgach', $bandId))
            ->orderBy('HeSoLuong')
            ->get(['MaBac', 'TenBac', 'HeSoLuong']);

        return response()->json(['ok' => true, 'data' => $grades]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = (array) $request->json()->all();

        $employeeId = DB::connection($this->conn())->transaction(function () use ($payload) {
            $conn = DB::connection($this->conn());
            $id = (int) $conn->table('nhanvien')->insertGetId([
                'HoTen'      => $payload['HoTen'],
                'GioiTinh'   => $payload['GioiTinh'] ?? null,
                'NgaySinh'   => $payload['NgaySinh'] ?? null,
                'Email'      => $payload['Email'] ?? null,
                'DienThoai'  => $payload['DienThoai'] ?? null,
                'TrangThai'  => $payload['TrangThai'],
                'MaBac'      => $payload['MaBac'] ?? null,
                'MaHS'       => null,
            ], 'MaNV');

            $this->upsertProfile($id, $payload, $conn);
            $this->upsertAssignment($id, $payload, true, $conn);

            return $id;
        });

        return response()->json(['ok' => true, 'id' => $employeeId], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $payload = (array) $request->json()->all();

        DB::connection($this->conn())->transaction(function () use ($id, $payload) {
            $conn = DB::connection($this->conn());
            $conn->table('nhanvien')->where('MaNV', $id)->update([
                'HoTen'     => $payload['HoTen'],
                'GioiTinh'  => $payload['GioiTinh'] ?? null,
                'NgaySinh'  => $payload['NgaySinh'] ?? null,
                'Email'     => $payload['Email'] ?? null,
                'DienThoai' => $payload['DienThoai'] ?? null,
                'TrangThai' => $payload['TrangThai'],
                'MaBac'     => $payload['MaBac'] ?? null,
            ]);
            $this->upsertProfile($id, $payload, $conn);
            $this->upsertAssignment($id, $payload, false, $conn);
        });

        return response()->json(['ok' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        $conn = DB::connection($this->conn());

        if ($conn->table('phancong')->where('MaNV', $id)->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'Nhân viên còn dữ liệu phân công nên không thể xóa.',
            ], 409);
        }

        try {
            $deleted = $conn->table('nhanvien')->where('MaNV', $id)->delete();
        } catch (QueryException $exception) {
            return response()->json([
                'ok' => false,
                'message' => 'Không thể xóa nhân viên do còn dữ liệu liên quan.',
            ], 409);
        }

        if ($deleted === 0) {
            return response()->json(['ok' => false, 'message' => 'Nhân viên không tồn tại.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function upsertProfile(int $id, array $payload, \Illuminate\Database\ConnectionInterface $conn): void
    {
        $profilePayload = [
            'MaNV'       => $id,
            'DiaChi'     => $payload['DiaChi'] ?? null,
            'NgayVaoLam' => $payload['NgayVaoLam'] ?? null,
            'MaPB'       => $payload['MaPB'] ?? null,
            'MaCV'       => $payload['MaCV'] ?? null,
        ];

        $hasData = collect($profilePayload)->except('MaNV')
            ->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();

        if ($hasData) {
            $conn->table('hosonhanvien')->updateOrInsert(['MaNV' => $id], $profilePayload);
        }
    }

    private function upsertAssignment(int $id, array $payload, bool $isCreate, \Illuminate\Database\ConnectionInterface $conn): void
    {
        if (empty($payload['MaPB']) || empty($payload['MaCV'])) {
            return;
        }

        $latestId = $conn->table('phancong')->where('MaNV', $id)->max('MaQT');

        $data = [
            'MaNV'         => $id,
            'MaPB'         => (int) $payload['MaPB'],
            'MaCV'         => (int) $payload['MaCV'],
            'NgayBatDau'   => $payload['NgayVaoLam'] ?? now()->toDateString(),
            'NgayKetThuc'  => null,
            'LyDoThayDoi'  => $isCreate ? 'Khoi tao tu Laravel' : 'Cap nhat tu Laravel',
        ];

        if ($latestId) {
            $latest = $conn->table('phancong')->where('MaQT', $latestId)->first();
            if ($latest && (int) $latest->MaPB === $data['MaPB'] && (int) $latest->MaCV === $data['MaCV']) {
                return;
            }
        }

        $conn->table('phancong')->insert($data);
    }
}
