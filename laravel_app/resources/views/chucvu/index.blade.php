@php $title = $moduleConfig['title'] @endphp
@php $subtitle = $moduleConfig['subtitle'] @endphp
@extends('layouts.app')

@section('content')
    @include('resource_modules.partials.index_content')
@endsection
