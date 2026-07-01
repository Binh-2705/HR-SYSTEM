<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;

use App\Services\TrainingService;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TrainingController extends Controller
{
    public function __construct(private TrainingService $trainingService)
    {
    }

    public function index(Request $request): View
    {
        $courses = $this->trainingService->paginate($request->only(['q', 'status']));
        /** @var LengthAwarePaginator $courses */
        $courses->setCollection(
            $courses->getCollection()->map(fn ($course) => (object) $this->normalizeCourseListRow((array) $course))
        );
        $courses->appends($request->query());

        return view('daotao.index', [
            'courses' => $courses,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    private function normalizeCourseListRow(array $row): array
    {
        return [
            'MaKDT' => (int) ($row['MaKDT'] ?? 0),
            'TenKhoaDaoTao' => (string) ($row['TenKhoaDaoTao'] ?? ''),
            'DonViToChuc' => (string) ($row['DonViToChuc'] ?? ''),
            'SoHocVien' => (int) ($row['SoHocVien'] ?? 0),
            'TrangThai' => (string) ($row['TrangThai'] ?? ''),
        ];
    }

    public function create(): View
    {
        return view('daotao.form', ['mode' => 'create', 'course' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatePayload($request);

        try {
            $courseId = $this->trainingService->create($payload);

            return redirect()->route('daotao.edit', ['training' => $courseId])
                ->with('success', 'Đã tạo khóa đào tạo thành công.');
        } catch (QueryException $exception) {
            return back()->withInput()->withErrors(['form' => 'Không thể tạo khóa đào tạo: ' . $exception->getMessage()]);
        }
    }

    public function edit(int $training): View
    {
        $course = $this->trainingService->find($training);
        abort_if($course === null, 404);

        return view('daotao.form', ['mode' => 'edit', 'course' => $course]);
    }

    public function update(Request $request, int $training): RedirectResponse
    {
        $payload = $this->validatePayload($request);

        try {
            $this->trainingService->update($training, $payload);

            return redirect()->route('daotao.edit', ['training' => $training])
                ->with('success', 'Đã cập nhật khóa đào tạo thành công.');
        } catch (QueryException $exception) {
            return back()->withInput()->withErrors(['form' => 'Không thể cập nhật khóa đào tạo: ' . $exception->getMessage()]);
        }
    }

    public function destroy(int $training): RedirectResponse
    {
        try {
            $this->trainingService->delete($training);

            return redirect()->route('daotao.index')
                ->with('success', 'Đã xóa khóa đào tạo thành công.');
        } catch (QueryException $exception) {
            return back()->withErrors(['form' => 'Không thể xóa khóa đào tạo.']);
        } catch (RuntimeException $exception) {
            return redirect()->route('daotao.index')
                ->withErrors(['form' => 'Không thể kết nối dịch vụ đào tạo, đã chuyển sang dữ liệu dự phòng.']);
        }
    }

    public function participants(int $training): View
    {
        $data = $this->trainingService->participantsPageData($training);
        abort_if(data_get($data, 'course') === null, 404);

        return view('daotao.participants', $data);
    }

    public function storeParticipant(Request $request, int $training): RedirectResponse
    {
        $validated = $request->validate([
            'MaNV' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $created = $this->trainingService->addParticipant($training, (int) $validated['MaNV']);

            if (!$created) {
                return redirect()->route('daotao.hocvien', ['training' => $training])
                    ->withErrors(['form' => 'Nhân viên này đã có trong khóa đào tạo.']);
            }

            return redirect()->route('daotao.hocvien', ['training' => $training])
                ->with('success', 'Đã thêm nhân viên vào khóa đào tạo.');
        } catch (QueryException $exception) {
            return redirect()->route('daotao.hocvien', ['training' => $training])
                ->withErrors(['form' => 'Không thể thêm nhân viên vào khóa đào tạo.']);
        }
    }

    public function updateParticipantResult(Request $request, int $participant): RedirectResponse
    {
        $validated = $request->validate([
            'MaKDT' => ['required', 'integer', 'min:1'],
            'KetQua' => ['required', 'in:Đạt,Không đạt,Chưa đánh giá'],
            'DiemDanhGia' => ['nullable', 'numeric', 'between:0,10'],
            'GhiChu' => ['nullable', 'string'],
        ]);

        try {
            $this->trainingService->updateParticipantResult($participant, [
                'KetQua' => $validated['KetQua'],
                'DiemDanhGia' => $validated['DiemDanhGia'] ?? null,
                'GhiChu' => $validated['GhiChu'] ?? null,
            ]);

            return redirect()->route('daotao.hocvien', ['training' => (int) $validated['MaKDT']])
                ->with('success', 'Đã cập nhật kết quả đào tạo.');
        } catch (QueryException $exception) {
            return redirect()->route('daotao.hocvien', ['training' => (int) $validated['MaKDT']])
                ->withErrors(['form' => 'Không thể cập nhật kết quả đào tạo.']);
        }
    }

    private function validatePayload(Request $request): array
    {
        $payload = $request->validate([
            'TenKhoaDaoTao' => ['required', 'string', 'max:200'],
            'TuNgay' => ['required', 'date'],
            'DenNgay' => ['required', 'date'],
            'NoiDung' => ['nullable', 'string'],
            'DonViToChuc' => ['nullable', 'string', 'max:150'],
            'TrangThai' => ['required', 'in:Lên kế hoạch,Đang đào tạo,Hoàn thành'],
        ]);

        if (strtotime((string) $payload['DenNgay']) < strtotime((string) $payload['TuNgay'])) {
            throw ValidationException::withMessages([
                'DenNgay' => 'Đến ngày phải lớn hơn hoặc bằng từ ngày.',
            ]);
        }

        return $payload;
    }
}