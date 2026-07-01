@php $title = 'Chi tiết phân quyền tài khoản' @endphp
@php $subtitle = 'Tổng hợp vai trò và chức năng đang được gán cho tài khoản' @endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="muted">Tài khoản</div>
        <div class="metric-strong top-gap-sm">#{{ $accountId }}</div>
    </section>

    <section class="detail-grid">
        <article class="panel">
            <h3 class="no-top-margin">Vai trò</h3>
            <div class="chip-cloud top-gap-md">
                @forelse ($roles as $role)
                    <span class="role-chip">{{ $role }}</span>
                @empty
                    <div class="muted">Tài khoản này chưa được gán vai trò.</div>
                @endforelse
            </div>
        </article>

        <article class="panel">
            <h3 class="no-top-margin">Chức năng</h3>
            <div class="chip-cloud top-gap-md">
                @forelse ($permissions as $permission)
                    <span class="permission-chip">{{ $permission }}</span>
                @empty
                    <div class="muted">Không có quyền nào được tìm thấy.</div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="panel">
        <a class="btn btn-secondary" href="{{ route('phanquyen.index') }}">Quay lại phân quyền</a>
    </section>
@endsection