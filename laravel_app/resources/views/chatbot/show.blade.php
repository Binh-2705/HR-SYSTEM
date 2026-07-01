@php $title = 'Chi tiết phiên chatbot' @endphp
@php $subtitle = 'Giám sát tin nhắn và bản nháp hành động của chatbot' @endphp
@extends('layouts.app')

@section('content')
<section class="panel">
    <div><strong>Session:</strong> {{ $session['session_key'] }}</div>
    <div><strong>Người dùng:</strong> {{ $session['username'] }} (MãTK {{ $session['ma_tk'] }})</div>
    <div><strong>Vai trò:</strong> {{ $session['role_name'] }}</div>
</section>

<section class="panel">
    <h3 class="no-top-margin">Bản nháp hành động</h3>
    <div class="table-shell">
        <table class="data-table table-compact">
            <thead><tr><th>Token</th><th>Hành động</th><th>Trạng thái</th><th>Tạo lúc</th></tr></thead>
            <tbody>
                @forelse ($drafts as $draft)
                    <tr><td>{{ $draft['token'] }}</td><td>{{ $draft['title'] }}</td><td>{{ $draft['status_name'] }}</td><td>{{ $draft['created_at'] }}</td></tr>
                @empty
                    <tr><td colspan="4" class="muted">Không có bản nháp.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h3 class="no-top-margin">Tin nhắn</h3>
    @forelse ($messages as $message)
        <div class="message-card-reset">
            <div><strong>{{ $message['role_name'] }}</strong> <span class="muted">{{ $message['created_at'] }}</span></div>
            <div class="top-gap-sm prewrap">{{ $message['content'] }}</div>
        </div>
    @empty
        <div class="muted">Không có tin nhắn.</div>
    @endforelse
</section>
@endsection