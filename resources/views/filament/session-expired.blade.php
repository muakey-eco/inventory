{{--
    Báo tiếng Việt khi Phiên App đã chết giữa chừng (ADR 0011). Nạp cuối mọi trang panel qua
    PanelsRenderHook::SCRIPTS_AFTER.

    Phiên App kết thúc sau 2 giờ không thao tác, hoặc sớm hơn khi nhân viên đăng xuất ở tab hay
    App khác, bị đình chỉ trên platform, hay mất `access` (back-channel logout). Livewire gặp 401
    (Phiên App mất) hay 419 (session CSRF mất) thì hiện hộp thoại tiếng Việt thay cho confirm()
    tiếng Anh, và không tự tải lại để nhân viên còn kịp chép phần đang nhập dở. Tải lại thì plugin
    của SDK đẩy qua platform rồi quay về đúng trang.
--}}
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.interceptRequest(({ onError }) => {
            onError(({ response, preventDefault }) => {
                if (![401, 419].includes(response.status)) {
                    return;
                }

                preventDefault();
                window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'session-expired' } }));
            });
        });
    });
</script>

<x-filament::modal
    id="session-expired"
    icon="heroicon-o-clock"
    icon-color="danger"
    alignment="center"
    heading="Phiên đăng nhập đã kết thúc"
    description="Bạn đã lâu không thao tác, đã đăng xuất ở tab hay ứng dụng khác, hoặc quyền vào kho đã đổi. Nếu đang nhập dở, hãy chép nội dung ra chỗ khác trước, rồi tải lại trang để đăng nhập lại."
>
    <x-slot name="footerActions">
        <x-filament::button color="gray" x-on:click="close()">
            Để tôi chép nội dung
        </x-filament::button>

        <x-filament::button x-on:click="window.location.reload()">
            Tải lại trang
        </x-filament::button>
    </x-slot>
</x-filament::modal>
