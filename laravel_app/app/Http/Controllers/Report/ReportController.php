<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;

use App\Services\ReportService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService)
    {
    }

    public function index(Request $request): View
    {
        $reports = $this->reportService->paginate($request->only(['q', 'type']));
        $reports->appends($request->query());

        return view('baocao.index', [
            'reports' => $reports,
            'filters' => $request->only(['q', 'type']),
        ]);
    }

    public function create(): View
    {
        return view('baocao.form', ['mode' => 'create', 'report' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $payload['NguoiTao'] = session('taikhoan.TenDangNhap', 'system');

        try {
            $reportId = $this->reportService->create($payload);

            return redirect()->route('baocao.edit', ['report' => $reportId])
                ->with('success', 'Đã tạo báo cáo thành công.');
        } catch (QueryException $exception) {
            return back()->withInput()->withErrors(['form' => 'Không thể tạo báo cáo: ' . $exception->getMessage()]);
        }
    }

    public function edit(int $report): View
    {
        $item = $this->reportService->find($report);
        abort_if($item === null, 404);

        return view('baocao.form', ['mode' => 'edit', 'report' => $item]);
    }

    public function update(Request $request, int $report): RedirectResponse
    {
        $payload = $this->validatePayload($request);

        try {
            $this->reportService->update($report, $payload);

            return redirect()->route('baocao.edit', ['report' => $report])
                ->with('success', 'Đã cập nhật báo cáo thành công.');
        } catch (QueryException $exception) {
            return back()->withInput()->withErrors(['form' => 'Không thể cập nhật báo cáo: ' . $exception->getMessage()]);
        }
    }

    public function destroy(int $report): RedirectResponse
    {
        try {
            $this->reportService->delete($report);

            return redirect()->route('baocao.index')
                ->with('success', 'Đã xóa báo cáo thành công.');
        } catch (QueryException $exception) {
            return back()->withErrors(['form' => 'Không thể xóa báo cáo.']);
        }
    }

    public function destroyLegacy(int $report): RedirectResponse
    {
        return $this->destroy($report);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $rows = $this->reportService->exportRows($request->only(['q', 'type']));

        return response()->streamDownload(function () use ($rows) {
            echo "ID\tTên báo cáo\tLoại\tNgười tạo\n";
            foreach ($rows as $row) {
                echo $row->MaBC . "\t" . $row->TenBaoCao . "\t" . $row->LoaiBaoCao . "\t" . ($row->NguoiTao ?: 'system') . "\n";
            }
        }, 'baocao_nhan_su.xls', [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    public function exportJson(Request $request)
    {
        $rows = array_values($this->reportService->exportRows($request->only(['q', 'type'])));

        return response()->json([
            'exportedAt' => now()->toIso8601String(),
            'count' => count($rows),
            'reports' => $rows,
        ], 200, [
            'Content-Disposition' => 'attachment; filename="baocao_nhan_su_' . now()->format('Ymd-His') . '.json"',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'TenBaoCao' => ['required', 'string', 'max:200'],
            'LoaiBaoCao' => ['required', 'in:Nhân sự,Chấm công,Nghỉ phép,Hợp đồng,Lương'],
            'TuNgay' => ['nullable', 'date'],
            'DenNgay' => ['nullable', 'date'],
            'GhiChu' => ['nullable', 'string'],
        ]);
    }
}