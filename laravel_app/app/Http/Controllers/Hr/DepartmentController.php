<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

use App\Services\DepartmentDirectoryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function __construct(private DepartmentDirectoryService $departmentService)
    {
    }

    public function index(Request $request): View
    {
        $departments = $this->departmentService->paginate($request->only(['q']));
        $departments->appends($request->query());

        return view('phongban.index', [
            'departments' => $departments,
            'filters' => $request->only(['q']),
        ]);
    }

    public function create(): View
    {
        return view('phongban.form', [
            'mode' => 'create',
            'department' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatePayload($request);

        try {
            $departmentId = $this->departmentService->create($payload);

            return redirect()->route('phongban.edit', ['department' => $departmentId])
                ->with('success', 'Đã tạo phòng ban thành công.');
        } catch (QueryException $exception) {
            return back()->withInput()->withErrors(['form' => 'Không thể tạo phòng ban: ' . $exception->getMessage()]);
        }
    }

    public function edit(int $department): View
    {
        $item = $this->departmentService->find($department);
        abort_if($item === null, 404);

        return view('phongban.form', [
            'mode' => 'edit',
            'department' => $item,
        ]);
    }

    public function update(Request $request, int $department): RedirectResponse
    {
        $payload = $this->validatePayload($request);

        try {
            $this->departmentService->update($department, $payload);

            return redirect()->route('phongban.edit', ['department' => $department])
                ->with('success', 'Đã cập nhật phòng ban thành công.');
        } catch (QueryException $exception) {
            return back()->withInput()->withErrors(['form' => 'Không thể cập nhật phòng ban: ' . $exception->getMessage()]);
        }
    }

    public function destroy(int $department): RedirectResponse
    {
        try {
            $this->departmentService->delete($department);

            return redirect()->route('phongban.index')
                ->with('success', 'Đã xóa phòng ban thành công.');
        } catch (QueryException $exception) {
            return back()->withErrors(['form' => 'Không thể xóa phòng ban do còn dữ liệu liên quan.']);
        }
    }

    public function destroyLegacy(int $department): RedirectResponse
    {
        return $this->destroy($department);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $rows = $this->departmentService->exportRows($request->only(['q']));
        $filename = 'Danh_sach_phong_ban_' . now()->format('Ymd') . '.xls';

        return response()->streamDownload(function () use ($rows) {
            echo "\xEF\xBB\xBF";
            echo "<table border='1'><tr><th>Mã PB</th><th>Tên phòng ban</th><th>Mô tả</th></tr>";
            foreach ($rows as $row) {
                echo '<tr>';
                echo '<td>' . e((string) $row->MaPB) . '</td>';
                echo '<td>' . e((string) $row->TenPB) . '</td>';
                echo '<td>' . e((string) ($row->MoTa ?? '')) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'filecsv' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $handle = fopen($request->file('filecsv')->getRealPath(), 'r');
        if ($handle === false) {
            return back()->withErrors(['form' => 'Không thể đọc file CSV.']);
        }

        $rows = [];
        fgetcsv($handle);
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $rows[] = [
                'TenPB' => trim((string) ($data[0] ?? '')),
                'MoTa' => trim((string) ($data[1] ?? '')),
            ];
        }
        fclose($handle);

        $count = $this->departmentService->importRows($rows);

        return redirect()->route('phongban.index')->with('success', "Đã nhập {$count} phòng ban.");
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'TenPB' => ['required', 'string', 'max:100'],
            'MoTa' => ['nullable', 'string'],
        ]);
    }
}