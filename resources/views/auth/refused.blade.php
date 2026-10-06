@extends('layouts.outside-panel')

@section('title', 'Không đăng nhập được')

@section('content')
    <x-filament-panels::header.simple heading="Không đăng nhập được vào kho" :subheading="$refusal->message()" />

    <x-filament::button tag="a" :href="filament()->getLoginUrl()">
        Thử lại
    </x-filament::button>
@endsection
