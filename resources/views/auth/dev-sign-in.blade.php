@extends('layouts.outside-panel')

@section('title', 'Đăng nhập dev')

@section('content')
    <x-filament-panels::header.simple
        heading="Đăng nhập dev"
        subheading="Chỉ có ở APP_ENV=local. Chọn một nhân viên để vào thẳng, không qua Authentik."
    />

    <ul>
        @forelse ($staff as $member)
            <li class="flex items-center justify-between gap-4 border-t border-(--muakey-divider) py-2">
                <span>
                    <span class="block text-(--muakey-text)">{{ $member->name }}</span>
                    <span class="block text-sm text-(--muakey-text-tertiary)">
                        {{ $member->roles->map(fn ($role) => \App\Inventory\Access\Role::from($role->name)->label())->join(', ') ?: 'Chưa có Vai trò' }}
                    </span>
                </span>
                <form method="post" action="{{ route('filament.admin.auth.dev.store', $member) }}">
                    @csrf
                    <x-filament::button type="submit" size="sm" color="gray">Vào</x-filament::button>
                </form>
            </li>
        @empty
            <li class="text-(--muakey-text-secondary)">
                Chưa có nhân viên. Chạy <code>php artisan db:seed</code>.
            </li>
        @endforelse
    </ul>
@endsection
