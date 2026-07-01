<?php

namespace App\Services;

use App\Support\TextEncoding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as PaginationLengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HrEmployeeService
{
    public function __construct(private InternalApiClient $client) {}

    private function conn(): string
    {
        return (string) config('service_registry.services.hr.connection', config('database.default'));
    }

    private function baseQuery()
    {
        return DB::connection($this->conn())
            ->table('nhanvien as nv')
            ->leftJoin('bacluong as bl', 'nv.MaBac', '=', 'bl.MaBac')
            ->leftJoin('ngachluong as nl', 'bl.MaNgach', '=', 'nl.MaNgach')
            ->leftJoin('hosonhanvien as hs', 'nv.MaNV', '=', 'hs.MaNV')
            ->leftJoinSub(
                DB::connection($this->conn())
                    ->table('phancong as pc1')
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

    public function paginate(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        try {
            $page = max(1, (int) request()->input('page', 1));
            $query = $this->baseQuery()
                ->when(!empty($filters['ma_nv']), fn ($q) => $q->where('nv.MaNV', (int) $filters['ma_nv']))
                ->when(!empty($filters['q']), function ($q) use ($filters) {
                    $kw = trim((string) $filters['q']);
                    $q->where(function ($inner) use ($kw) {
                        $inner->where('nv.HoTen', 'like', "%{$kw}%")
                            ->orWhere('nv.Email', 'like', "%{$kw}%")
                            ->orWhere('nv.DienThoai', 'like', "%{$kw}%")
                            ->orWhere('nv.MaNV', 'like', "%{$kw}%");
                    });
                })
                ->when(!empty($filters['status']), fn ($q) => $q->where('nv.TrangThai', (string) $filters['status']))
                ->when(!empty($filters['department']), fn ($q) => $q->where('pb.MaPB', (int) $filters['department']))
                ->orderBy('nv.MaNV');

            $total = (clone $query)->count();
            $items = $query->forPage($page, $perPage)->get()
                ->map(fn ($row) => (object) TextEncoding::normalizeValue((array) $row));

            return new PaginationLengthAwarePaginator($items, $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        } catch (\Throwable) {
            return $this->emptyPaginator($perPage);
        }
    }

    public function find(int $employeeId): ?array
    {
        try {
            $item = $this->baseQuery()->where('nv.MaNV', $employeeId)->first();
            return $item ? TextEncoding::normalizeValue((array) $item) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function options(): array
    {
        try {
            $conn = DB::connection($this->conn());
            return [
                'departments' => $conn->table('phongban')->orderBy('TenPB')->get()
                    ->map(fn ($row) => (object) TextEncoding::normalizeValue((array) $row))
                    ->all(),
                'positions' => $conn->table('chucvu')->orderBy('TenCV')->get()
                    ->map(fn ($row) => (object) TextEncoding::normalizeValue((array) $row))
                    ->all(),
                'salaryGrades' => $conn->table('bacluong as b')
                    ->leftJoin('ngachluong as n', 'b.MaNgach', '=', 'n.MaNgach')
                    ->select('b.MaBac', 'b.TenBac', 'b.HeSoLuong', 'b.LuongCoSo', 'n.TenNgach')
                    ->orderBy('n.TenNgach')
                    ->orderBy('b.HeSoLuong')
                    ->get()
                    ->map(fn ($row) => (object) TextEncoding::normalizeValue((array) $row))
                    ->all(),
            ];
        } catch (\Throwable) {
            return ['departments' => [], 'positions' => [], 'salaryGrades' => []];
        }
    }

    public function salaryGradesByBand(int|string $bandId): array
    {
        try {
            return DB::connection($this->conn())
                ->table('bacluong')
                ->where('MaNgach', $bandId)
                ->orderBy('HeSoLuong')
                ->get(['MaBac', 'TenBac', 'HeSoLuong'])
                ->map(fn ($row) => TextEncoding::normalizeValue((array) $row))
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function create(array $payload): int
    {
        return (int) ($this->client->post('biz/employees', $payload)['id'] ?? 0);
    }

    public function update(int $employeeId, array $payload): void
    {
        $this->client->put("biz/employees/{$employeeId}", $payload);
    }

    public function delete(int $employeeId): void
    {
        $this->client->delete("biz/employees/{$employeeId}");
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new PaginationLengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
