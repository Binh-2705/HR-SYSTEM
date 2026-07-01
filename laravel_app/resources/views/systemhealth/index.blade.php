@php $title = 'Sức khỏe hệ thống' @endphp
@php $subtitle = 'Kiểm tra nhanh tình trạng service và kết nối hệ thống' @endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <h2 class="no-top-margin">Dịch vụ cơ sở dữ liệu</h2>
        <div class="health-grid">
            @foreach ($statuses as $name => $status)
                @php $statusClass = $status['status'] === 'ok' ? 'status-text-ok' : 'status-text-error' @endphp
                <div class="health-card">
                    <div class="health-service-name">{{ $name }}</div>
                    <div class="{{ $statusClass }} top-gap-sm">{{ strtoupper($status['status']) }}</div>
                    <div class="muted top-gap-sm">{{ $status['detail'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="panel">
        @php $botClass = $botStatus['status'] === 'ok' ? 'status-text-ok' : 'status-text-error' @endphp
        <h2 class="no-top-margin">Dịch vụ bot</h2>
        <div><strong>URL:</strong> {{ $botUrl }}</div>
        <div class="{{ $botClass }} top-gap-sm">{{ strtoupper($botStatus['status']) }}</div>
        <div class="muted top-gap-sm">{{ $botStatus['detail'] }}</div>
    </section>
@endsection