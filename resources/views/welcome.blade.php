@extends('layouts.outside-panel')

@section('content')
    <x-filament-panels::header.simple subheading="Đăng nhập bằng tài khoản Authentik của bạn để vào kho." />

    <x-filament::button tag="a" :href="filament()->getUrl()">
        Vào kho
    </x-filament::button>
@endsection
