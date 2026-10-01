@extends('auth.layout')

@section('title', 'Không đăng nhập được')

@section('content')
    <h1>Không đăng nhập được vào kho</h1>
    <p>{{ $refusal->message() }}</p>
    <a href="{{ filament()->getLoginUrl() }}">Thử lại</a>
@endsection
