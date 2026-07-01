<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Services\ContractAdminService;
use App\Services\GenericResourceModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LogicException;

class ContractAdminController extends Controller
{
    public function __construct(
        private ContractAdminService $contracts,
        private GenericResourceModuleService $modules,
    )
    {
    }

    public function renewForm(int $contract): View
    {
        $contractData = $this->contracts->contractDetail($contract);
        abort_if($contractData === null, 404);

        return view('hopdong.renew', ['contract' => $contractData]);
    }

    public function renewStore(Request $request, int $contract): RedirectResponse // gia hạn hợp đồng
    {
        $payload = $request->validate([
            'SoHopDong' => ['required', 'string', 'max:50'],
            'LoaiHopDong' => ['required', 'in:Thử việc,Xác định thời hạn,Không xác định thời hạn'],
            'NgayBatDau' => ['required', 'date'],
            'NgayKetThuc' => ['nullable', 'date', 'after_or_equal:NgayBatDau'],
            'GhiChu' => ['nullable', 'string'],
        ], [
            'SoHopDong.required' => 'Vui lòng nhập số hợp đồng.',
            'SoHopDong.max' => 'Số hợp đồng không được vượt quá 50 ký tự.',
            'LoaiHopDong.required' => 'Vui lòng chọn loại hợp đồng.',
            'LoaiHopDong.in' => 'Loại hợp đồng không hợp lệ.',
            'NgayBatDau.required' => 'Vui lòng nhập ngày bắt đầu.',
            'NgayBatDau.date' => 'Ngày bắt đầu không hợp lệ.',
            'NgayKetThuc.date' => 'Ngày kết thúc không hợp lệ.',
            'NgayKetThuc.after_or_equal' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.',
        ]);

        try {
            $this->contracts->renewContract($contract, $payload);
        } catch (LogicException $exception) {
            return back()->withInput()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('hopdong.index')->with('success', 'Đã gia hạn hợp đồng thành công.');
    }

    public function terminate(int $contract): RedirectResponse // chấm dứt hợp đồng
    {
        $this->contracts->terminateContract($contract);

        return redirect()->route('hopdong.index')->with('success', 'Đã chấm dứt hợp đồng.');
    }

    public function salaryHistory(int $contract): View // lịch sử lương của hợp đồng
    {
        $contractData = $this->contracts->contractDetail($contract);
        abort_if($contractData === null, 404);

        return view('hopdong.salary_history', [
            'contract' => $contractData,
            'history' => $this->contracts->salaryHistory($contract),
        ]);
    }

    public function destroyLegacy(int $contract): RedirectResponse // xóa hợp đồng
    {
        $this->modules->delete('contracts', (string) $contract);

        return redirect()->route('hopdong.index')->with('success', 'Đã xóa hợp đồng thành công.');
    }
}