{{--
    Khung chung của các trang ngoài panel: layout simple của Filament, giống trang đăng nhập của
    panel, nên tự mang CSS của Theme, logo lockup, font, Màu nhấn và dark mode. Route phải chạy
    trong panel (route của panel, hoặc middleware `panel:admin`). Trang không tự chuyển hướng.
--}}
<x-filament-panels::layout.simple>
    <div class="fi-simple-page">
        <div class="fi-simple-page-content">
            @yield('content')
        </div>
    </div>
</x-filament-panels::layout.simple>
