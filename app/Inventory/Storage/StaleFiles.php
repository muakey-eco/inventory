<?php

namespace App\Inventory\Storage;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\StorageAttributes;

/**
 * File cũ để các lệnh purge xoá. Thời điểm ghi lấy từ một lần liệt kê thư mục, không hỏi từng
 * file: trên S3 mỗi `lastModified()` là một request HEAD.
 */
final class StaleFiles
{
    /**
     * File nằm thẳng trong `directory` (không đệ quy), ghi trước mốc `writtenBefore`.
     *
     * @return list<string>
     */
    public static function in(Filesystem $disk, string $directory, CarbonInterface $writtenBefore): array
    {
        assert($disk instanceof FilesystemAdapter);

        return $disk->getDriver()->listContents($directory)
            ->filter(fn (StorageAttributes $item): bool => $item->isFile() && ($item->lastModified() ?? PHP_INT_MAX) < $writtenBefore->getTimestamp())
            ->map(fn (StorageAttributes $item): string => $item->path())
            ->toArray();
    }
}
