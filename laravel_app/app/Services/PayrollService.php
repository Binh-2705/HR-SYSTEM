<?php

namespace App\Services;

use App\Models\PayrollRecord;
use App\Support\TextEncoding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as PaginationLengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollService
{
    public function __construct(private InternalApiClient $client) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            $page = max(1, (int) request()->input('page', 1));
            $query = PayrollRecord::query()
                ->withEmployeeContext()
                ->applyFilters($filters)
                ->sortDefault();

            $total = (clone $query)->count();
            $items = $query->forPage($page, $perPage)->get()
                ->map(fn ($row) => (object) TextEncoding::normalizeValue($row->toArray()));

            return new PaginationLengthAwarePaginator($items, $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        } catch (\Throwable) {
            return $this->emptyPaginator($perPage);
        }
    }

    public function find(int $payrollId): ?array
    {
        try {
            $item = PayrollRecord::query()
                ->withEmployeeContext()
                ->where('bl.MaBL', $payrollId)
                ->first();

            return $item ? TextEncoding::normalizeValue($item->toArray()) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function employeeOptions(): array
    {
        try {
            return DB::connection((string) config('service_registry.services.payroll.connection', config('database.default')))
                ->table('nhanvien')
                ->orderBy('HoTen')
                ->get(['MaNV', 'HoTen'])
                ->map(fn ($row) => (object) TextEncoding::normalizeValue((array) $row))
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function create(array $payload): int
    {
        return (int) ($this->client->post('biz/payroll', $payload)['id'] ?? 0);
    }

    public function update(int $payrollId, array $payload): void
    {
        $this->client->put("biz/payroll/{$payrollId}", $payload);
    }

    public function runMonthly(int $month, int $year): array
    {
        return $this->client->post('biz/payroll/run-monthly', ['month' => $month, 'year' => $year], 120);
    }

    public function processMonthlyPayroll(int $month, int $year): int
    {
        $result = $this->runMonthly($month, $year);
        return (int) ($result['processed'] ?? $result['count'] ?? 0);
    }

    public function salaryComponents(int $maNV, int $month, int $year): array
    {
        try {
            return $this->client->get('biz/payroll/salary-components', [
                'ma_nv' => $maNV,
                'month' => $month,
                'year' => $year,
            ]);
        } catch (RuntimeException) {
            return [];
        }
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new PaginationLengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
