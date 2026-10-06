@extends('auth.layout')

@section('content')
    <x-filament-panels::header.simple
        heading="Bạn đã đăng xuất khỏi kho"
        subheading="Phiên đăng nhập Authentik của bạn vẫn còn; đăng xuất hẳn thì làm ở Authentik."
    />

    <x-filament::button tag="a" :href="filament()->getLoginUrl()">
        Đăng nhập lại
    </x-filament::button>
@endsection
