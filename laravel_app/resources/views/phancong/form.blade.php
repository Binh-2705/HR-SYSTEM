@php
    $title = $mode === 'create' ? 'Thêm ' . $moduleConfig['title'] : 'Sửa ' . $moduleConfig['title'];
    $subtitle = $moduleConfig['subtitle'];
@endphp
@extends('layouts.app')

@section('content')
    @include('resource_modules.partials.form_content')
@endsection
