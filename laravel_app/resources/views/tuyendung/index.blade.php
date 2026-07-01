@php
    $title = 'Tuyển dụng';
    $subtitle = 'Quản trị đợt tuyển dụng trên hệ thống';
@endphp
@extends('layouts.app')

@section('content')
    <div class="context-help-wrap">
        <details class="context-help">
            <summary>
                <span class="context-help-icon" aria-hidden="true">i</span>
                <span>Hướng dẫn nhanh</span>
            </summary>
            <div class="context-help-panel">
                <p class="context-help-title">Cách dùng chức năng Tuyển dụng</p>
                <ol class="context-help-steps">
                    <li>Tạo đợt tuyển mới với vị trí và số lượng rõ ràng.</li>
                    <li>Vào <strong>Quản lý ứng viên</strong> để thêm ứng viên, sau đó gắn hồ sơ vào đợt tuyển phù hợp.</li>
                    <li>Mở nút <strong>Hồ sơ</strong> của từng đợt để theo dõi trạng thái phỏng vấn và đánh giá.</li>
                </ol>
                <p class="context-help-note">Nếu bạn mới thao tác lần đầu, hãy đi theo đúng thứ tự 1 -> 2 -> 3 để tránh thiếu bước.</p>
            </div>
        </details>
    </div>

    <section class="panel">
        <div class="toolbar toolbar-start" style="margin-bottom:12px;">
            @if (in_array('them_dot_tuyen', session('quyen', []), true))
                <a class="btn" href="{{ route('tuyendung.create') }}">+ Thêm đợt tuyển</a>
            @endif
            @if (in_array('xem_ung_vien', session('quyen', []), true))
                <a class="btn btn-secondary" href="{{ route('tuyendung.ungvien.index') }}">👤 Quản lý ứng viên</a>
            @endif
        </div>
        <form method="get" class="filter-grid">
            <div>
                <label for="q" class="wide-search-label">Tìm kiếm</label>
                <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tên đợt hoặc vị trí">
            </div>
            <div>
                <label for="status" class="wide-search-label">Trạng thái</label>
                <select id="status" name="status">
                    <option value="">Tất cả</option>
                    <option value="Đang tuyển" @selected(($filters['status'] ?? '') === 'Đang tuyển')>Đang tuyển</option>
                    <option value="Đã kết thúc" @selected(($filters['status'] ?? '') === 'Đã kết thúc')>Đã kết thúc</option>
                </select>
            </div>
            <div class="button-row">
                <button class="btn" type="submit">Lọc</button>
                @if (in_array('them_dot_tuyen', session('quyen', []), true))
                    <a class="btn btn-secondary" href="{{ route('tuyendung.create') }}">Thêm mới</a>
                @endif
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="table-shell">
            <table class="data-table table-compact">
                <thead><tr>
                    <th>MãDTD</th>
                    <th>Tên đợt</th>
                    <th>Vị trí</th>
                    <th>Số lượng</th>
                    <th>Hồ sơ</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr></thead>
                <tbody>
                @forelse ($campaigns as $campaign)
                    @php
                        $soLuongRaw = $campaign->SoLuong ?? null;
                        $soHoSoRaw = $campaign->SoHoSo ?? null;
                        $hasValidSoLuong = $soLuongRaw !== null && $soLuongRaw !== '' && is_numeric($soLuongRaw);
                        $hasValidSoHoSo = $soHoSoRaw !== null && $soHoSoRaw !== '' && is_numeric($soHoSoRaw);
                    @endphp
                    <tr>
                        <td>{{ $campaign->MaDTD }}</td>
                        <td>
                            @if (!empty($campaign->TenDotTuyenDung))
                                <strong>{{ $campaign->TenDotTuyenDung }}</strong>
                            @else
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn tên đợt tuyển</span>
                            @endif
                        </td>
                        <td>
                            @if (!empty($campaign->ViTriTuyenDung))
                                {{ $campaign->ViTriTuyenDung }}
                            @else
                                <span class="field-status field-status-unassigned">Chưa được gán vị trí tuyển dụng</span>
                            @endif
                        </td>
                        <td>
                            @if ($hasValidSoLuong)
                                {{ (int) $soLuongRaw }}
                            @elseif ($soLuongRaw === null || $soLuongRaw === '')
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn số lượng</span>
                            @else
                                <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: số lượng</span>
                            @endif
                        </td>
                        <td>
                            @if ($hasValidSoHoSo)
                                {{ (int) $soHoSoRaw }}
                            @elseif ($soHoSoRaw === null || $soHoSoRaw === '')
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn hồ sơ</span>
                            @else
                                <span class="field-status field-status-invalid">Dữ liệu không hợp lệ: hồ sơ</span>
                            @endif
                        </td>
                        <td>
                            @if (!empty($campaign->TrangThai))
                                {{ $campaign->TrangThai }}
                            @else
                                <span class="field-status field-status-source">Thiếu dữ liệu nguồn trạng thái</span>
                            @endif
                        </td>
                        <td>
                            <div class="button-row">
                            @if (in_array('xem_ho_so', session('quyen', []), true))
                                <a class="btn" href="{{ route('tuyendung.hoso.index', ['recruitment' => $campaign->MaDTD]) }}">Hồ sơ</a>
                            @endif
                            @if (in_array('them_dot_tuyen', session('quyen', []), true))
                                <a class="btn btn-secondary" href="{{ route('tuyendung.edit', ['recruitment' => $campaign->MaDTD]) }}">Sửa</a>
                            @endif
                            @if (in_array('xoa_dot_tuyen', session('quyen', []), true))
                                <form method="post" action="{{ route('tuyendung.destroy', ['recruitment' => $campaign->MaDTD]) }}" class="inline-form" onsubmit="return confirm('Xóa đợt tuyển dụng này?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger" type="submit">Xóa</button>
                                </form>
                            @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">
                            <div class="empty-state-note">
                                <span>Không có đợt tuyển phù hợp với bộ lọc.</span>
                                @if (in_array('them_dot_tuyen', session('quyen', []), true))
                                    <a class="btn btn-secondary" href="{{ route('tuyendung.create') }}">Tạo đợt tuyển đầu tiên</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="top-gap-lg">{{ $campaigns->links() }}</div>
    </section>
@endsection