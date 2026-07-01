<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AttendanceService
{
    public function __construct(private InternalApiClient $client) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            return $this->client->paginate('biz/attendance/paginate', [
                'filters' => $filters,
                'perPage' => $perPage,
                'page'    => request()->input('page', 1),
            ]);
        } catch (RuntimeException) {
            return $this->emptyPaginator($perPage);
        }
    }

    public function find(int $attendanceId): ?array
    {
        try {
            return $this->client->get("biz/attendance/{$attendanceId}")['data'] ?? null;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return null;
        } catch (RuntimeException) {
            return null;
        }
    }

    public function employeeOptions(): array
    {
        try {
            $data = $this->client->get('biz/attendance/employee-options')['data'] ?? [];
            return array_map(fn($e) => (object) $e, $data);
        } catch (RuntimeException) {
            return [];
        }
    }

    public function create(array $payload): int
    {
        return (int) ($this->client->post('biz/attendance', $payload)['id'] ?? 0);
    }

    public function update(int $attendanceId, array $payload): void
    {
        $this->client->put("biz/attendance/{$attendanceId}", $payload);
    }

    public function delete(int $attendanceId): void
    {
        $this->client->delete("biz/attendance/{$attendanceId}");
    }

    public function workedDaysSummary(int $maNV, int $month, int $year): array
    {
        return $this->client->get('biz/attendance/worked-days', ['ma_nv' => $maNV, 'month' => $month, 'year' => $year])['data'] ?? [];
    }

    public function exportRows(array $filters = []): array
    {
        return $this->client->get('biz/attendance/export-rows', $filters)['data'] ?? [];
    }

    public function workedDaysByMonth(int $maNV, int $month, ?int $year = null): array
    {
        return $this->client->get('biz/attendance/worked-days', [
            'ma_nv' => $maNV,
            'month' => $month,
            'year'  => $year ?? (int) now()->year,
        ])['data'] ?? [];
    }

    public function monthlyAttendanceMatrix(int $month, int $year, ?int $maNV = null): array
    {
        $params = ['month' => $month, 'year' => $year];
        if ($maNV !== null) {
            $params['ma_nv'] = $maNV;
        }

        try {
            return $this->client->get('biz/attendance/monthly-matrix', $params)['data'] ?? [];
        } catch (RuntimeException) {
            return $this->monthlyMatrixFromDatabase($month, $year, $maNV);
        }
    }

    private function monthlyMatrixFromDatabase(int $month, int $year, ?int $maNV = null): array
    {
        $conn = DB::connection($this->connectionName());

        $employees = $conn->table('nhanvien as nv')
            ->leftJoin('hosonhanvien as hs', 'hs.MaNV', '=', 'nv.MaNV')
            ->leftJoin('phongban as pb', 'pb.MaPB', '=', 'hs.MaPB')
            ->select(['nv.MaNV', 'nv.HoTen', 'pb.TenPB'])
            ->when($maNV !== null, fn ($q) => $q->where('nv.MaNV', $maNV))
            ->orderBy('pb.TenPB')
            ->orderBy('nv.HoTen')
            ->get();

        $records = $conn->table('chamcong')
            ->whereMonth('Ngay', $month)
            ->whereYear('Ngay', $year)
            ->get()
            ->groupBy('MaNV');

        $matrix = [];
        foreach ($employees as $emp) {
            $dept = (string) ($emp->TenPB ?? 'Chua phan cong');
            $empRecords = $records->get((string) $emp->MaNV, collect());
            $days = [];

            foreach ($empRecords as $rec) {
                $day = date('d', strtotime((string) $rec->Ngay));
                $days[$day] = ['s' => (string) $rec->TrangThai, 'id' => (int) $rec->MaCC];
            }

            $matrix[$dept][] = [
                'MaNV' => (int) $emp->MaNV,
                'HoTen' => (string) $emp->HoTen,
                'Ngay' => $days,
            ];
        }

        return $matrix;
    }

    private function connectionName(): string
    {
        return (string) config('service_registry.services.attendance.connection', config('database.default'));
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
