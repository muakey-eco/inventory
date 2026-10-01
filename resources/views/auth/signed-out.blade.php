@extends('auth.layout')

@section('title', 'Đã đăng xuất')

@section('content')
    <h1>Bạn đã đăng xuất khỏi kho</h1>
    <p>Phiên đăng nhập Authentik của bạn vẫn còn; đăng xuất hẳn thì làm ở Authentik.</p>
    <a href="{{ filament()->getLoginUrl() }}">Đăng nhập lại</a>
@endsection
