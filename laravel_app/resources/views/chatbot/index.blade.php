@php $title = 'Giám sát chatbot' @endphp
@php $subtitle = 'Theo dõi phiên sử dụng chatbot, tin nhắn và bản nháp hành động' @endphp
@extends('layouts.app')

@section('content')
<section class="panel">
    <div class="table-shell">
        <table class="data-table table-compact">
            <thead>
                <tr>
                    <th>Session</th>
                    <th>Người dùng</th>
                    <th>Vai trò</th>
                    <th>Tin nhắn</th>
                    <th>Bản nháp</th>
                    <th>Hoạt động cuối</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sessions as $session)
                    <tr>
                        <td>{{ $session->id }}</td>
                        <td>
                            <div><strong>{{ $session->username }}</strong></div>
                            <div class="muted">{{ $session->session_key }}</div>
                        </td>
                        <td>{{ $session->role_name }}</td>
                        <td>{{ $session->MessageCount }}</td>
                        <td>{{ $session->DraftCount }}</td>
                        <td>{{ $session->last_interaction_at }}</td>
                        <td><a class="btn btn-secondary" href="{{ route('chatbot.show', ['session' => $session->id]) }}">Xem chi tiết</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Không có dữ liệu chatbot.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="top-gap-lg">{{ $sessions->links() }}</div>
</section>
@endsection