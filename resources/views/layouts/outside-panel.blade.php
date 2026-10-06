{{--
    Khung chung của các trang ngoài panel: layout simple của Filament, giống trang đăng nhập của
    panel, nên tự mang CSS của Theme, logo lockup, font, Màu nhấn và dark mode. Route phải chạy
    trong panel (route của panel, hoặc middleware `panel:admin`).

    Trang không tự chuyển hướng. Layout có nạp JS của Livewire và Filament, nên bật thứ gì tự gửi
    request trên mọi trang của panel (`->spa()`, `->databaseNotifications()` có poll) thì soát lại
    trang bị từ chối: nó không được dẫn tới một vòng lặp kho ↔ Authentik.
--}}
{{-- `livewire` đi qua data của @component như Livewire vẫn làm: truyền bằng attribute thì @props của layout xoá mất. --}}
@component('filament-panels::components.layout.simple', ['livewire' => new \App\Filament\Support\OutsidePanelPage(trim($__env->yieldContent('title')))])
    <div class="fi-simple-page">
        <div class="fi-simple-page-content">
            @yield('content')
        </div>
    </div>
@endcomponent
