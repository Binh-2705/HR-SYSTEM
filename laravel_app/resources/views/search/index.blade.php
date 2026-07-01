@php $title = 'Tra cứu hệ thống' @endphp
@php $subtitle = 'Tìm nhanh trên các phân hệ đã đưa về hệ thống hiện tại' @endphp
@extends('layouts.app')

@section('content')
    <section class="panel">
        <form method="get" class="filter-grid single-wide">
            <div>
                <label for="q" class="wide-search-label">Từ khóa</label>
                <input id="q" name="q" value="{{ $keyword }}" placeholder="Họ tên, phòng ban, đợt tuyển, báo cáo...">
            </div>
            <div><button class="btn" type="submit">Tìm</button></div>
        </form>
    </section>

    @foreach ($results as $section => $items)
        <section class="panel">
            <h2 class="no-top-margin">{{ str_replace('-', ' ', $section) }}</h2>
            @if (empty($items))
                <div class="muted">Không có kết quả.</div>
            @else
                <div class="table-shell">
                    <table class="data-table table-compact">
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    @foreach ((array) $item as $value)
                                        <td>{{ $value }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endforeach
@endsection