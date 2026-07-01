@php $title = $mode === 'create' ? 'Thêm phòng ban' : 'Sửa phòng ban' @endphp
@php $subtitle = 'Quản trị phòng ban' @endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <form method="post" action="{{ $mode === 'create' ? route('phongban.store') : route('phongban.update', ['department' => $department['MaPB']]) }}">
            @csrf
            @if ($mode === 'edit')
                @method('PUT')
            @endif

            <div class="field-grid">
                <div>
                    <label for="TenPB">Tên phòng ban</label>
                    <input id="TenPB" type="text" name="TenPB" value="{{ old('TenPB', $department['TenPB'] ?? '') }}" placeholder="VD: Phòng Nhân sự" maxlength="100" required>
                </div>

                <div class="full-span">
                    <label for="MoTa">Mô tả</label>
                    <textarea id="MoTa" name="MoTa" rows="4" placeholder="Mô tả chức năng của phòng ban...">{{ old('MoTa', $department['MoTa'] ?? '') }}</textarea>
                </div>
            </div>

            <div class="form-actions-bar">
                <button type="submit" class="btn">Lưu phòng ban</button>
                <a href="{{ route('phongban.index') }}" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </section>
@endsection