<?php

namespace App\Filament\Support;

/**
 * Đứng vào chỗ `$livewire` của layout simple cho trang ngoài panel (view Blade thường, không phải
 * Livewire page), để layout dựng `<title>` riêng của trang. Layout của Filament chỉ gọi ba method
 * dưới đây; nâng Filament mà layout gọi thêm thì test của các trang này nổ.
 */
final readonly class OutsidePanelPage
{
    public function __construct(private string $title) {}

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return array<string>
     */
    public function getRenderHookScopes(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtraBodyAttributes(): array
    {
        return [];
    }
}
