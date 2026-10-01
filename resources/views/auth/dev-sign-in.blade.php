@extends('auth.layout')

@section('title', 'Đăng nhập dev')

@section('content')
    <h1>Đăng nhập dev</h1>
    <p>Chỉ có ở APP_ENV=local. Chọn một nhân viên để vào thẳng, không qua Authentik.</p>
    <ul>
        @forelse ($staff as $member)
            <li>
                <span>
                    {{ $member->name }}<br>
                    <small>{{ $member->roles->map(fn ($role) => \App\Inventory\Access\Role::from($role->name)->label())->join(', ') ?: 'Chưa có Vai trò' }}</small>
                </span>
                <form method="post" action="{{ route('filament.admin.auth.dev.store', $member) }}">
                    @csrf
                    <button type="submit">Vào</button>
                </form>
            </li>
        @empty
            <li>Chưa có nhân viên. Chạy <code>php artisan db:seed</code>.</li>
        @endforelse
    </ul>
@endsection
