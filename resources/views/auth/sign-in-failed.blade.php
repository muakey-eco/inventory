@extends('layouts.outside-panel')

@section('title', 'Không vào được kho')

@section('content')
    <x-filament-panels::header.simple heading="Không vào được kho" :subheading="$message" />

    <x-filament::button tag="a" :href="$retryUrl">
        Thử lại
    </x-filament::button>
@endsection
