@php $title = 'Bảng dịch vụ' @endphp
@php $subtitle = 'Theo dõi và quản trị các dịch vụ cơ sở dữ liệu đã được kết nối' @endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="toolbar">
            <div>
                <h2 class="no-top-margin">Danh mục dịch vụ</h2>
                <p class="page-note">Mỗi dịch vụ được map đến đúng kết nối và tài nguyên, phục vụ giao diện và API tương thích.</p>
            </div>
            <div class="inline-actions">
                <a class="btn btn-secondary" href="{{ route('dashboard') }}">Về bảng điều khiển</a>
            </div>
        </div>
    </section>

    <section class="console-grid">
        @foreach ($services as $serviceName => $service)
            <article class="panel console-card">
                <span class="eyebrow">{{ $serviceName }}</span>
                <h3 class="no-top-margin">{{ $serviceName }}</h3>
                <div class="page-note">Kết nối: <strong>{{ $service['connection'] }}</strong></div>
                <div class="chip-list top-gap-lg">
                    @foreach ($service['resources'] as $resource)
                        <a class="chip-link" href="{{ route('services.show', ['service' => $serviceName, 'resource' => $resource]) }}">{{ $resource }}</a>
                    @endforeach
                </div>
            </article>
        @endforeach
    </section>
@endsection