@extends('layouts.outside-panel')

@section('content')
    <x-filament-panels::header.simple subheading="Đăng nhập qua platform Muakey để vào kho." />

    <x-filament::button tag="a" :href="filament()->getUrl()">
        Vào kho
    </x-filament::button>
@endsection
