@php $title = $mode === 'create' ? 'Thêm chấm công' : 'Sửa chấm công' @endphp
@php $subtitle = 'Cập nhật dữ liệu công và trạng thái đi làm' @endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <form method="post" action="{{ $mode === 'create' ? route('chamcong.store') : route('chamcong.update', ['attendance' => $record['MaCC']]) }}">
            @csrf
            @if ($mode === 'edit')
                @method('PUT')
            @endif

            <div class="field-grid">
                <div>
                    <label for="MaNV">Nhân viên</label>
                    <select id="MaNV" name="MaNV" required>
                        <option value="">-- Chọn nhân viên --</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->MaNV }}" @selected((string) old('MaNV', $record['MaNV'] ?? '') === (string) $employee->MaNV)>
                                {{ $employee->MaNV }} - {{ $employee->HoTen }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="Ngay">Ngày làm việc</label>
                    <input id="Ngay" type="date" name="Ngay" value="{{ old('Ngay', $record['Ngay'] ?? '') }}" required>
                </div>
                <div>
                    <label for="GioVao">Giờ vào</label>
                    <input id="GioVao" type="time" name="GioVao" value="{{ old('GioVao', $record['GioVao'] ?? '') }}">
                </div>
                <div>
                    <label for="GioRa">Giờ ra</label>
                    <input id="GioRa" type="time" name="GioRa" value="{{ old('GioRa', $record['GioRa'] ?? '') }}">
                </div>
                <div>
                    <label for="TrangThai">Trạng thái</label>
                    <select id="TrangThai" name="TrangThai" required>
                        <option value="Di lam" @selected(old('TrangThai', $record['TrangThai'] ?? 'Di lam') === 'Di lam')>Đi làm</option>
                        <option value="Nghi phep" @selected(old('TrangThai', $record['TrangThai'] ?? '') === 'Nghi phep')>Nghỉ phép</option>
                        <option value="Nghi khong luong" @selected(old('TrangThai', $record['TrangThai'] ?? '') === 'Nghi khong luong')>Nghỉ không lương</option>
                        <option value="Cong tac" @selected(old('TrangThai', $record['TrangThai'] ?? '') === 'Cong tac')>Công tác</option>
                        <option value="Le" @selected(old('TrangThai', $record['TrangThai'] ?? '') === 'Le')>Lễ</option>
                    </select>
                </div>
                <div class="full-span">
                    <label for="GhiChu">Ghi chú</label>
                    <textarea id="GhiChu" name="GhiChu" placeholder="Thông tin bổ sung nếu có...">{{ old('GhiChu', $record['GhiChu'] ?? '') }}</textarea>
                </div>
            </div>

            <div class="form-actions-bar">
                <button type="submit" class="btn">Lưu chấm công</button>
                <a href="{{ route('chamcong.index') }}" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </section>
@endsection