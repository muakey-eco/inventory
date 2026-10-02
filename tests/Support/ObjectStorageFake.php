<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Date;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use LogicException;

/**
 * Kho object trong bộ nhớ, đứng thay S3: không có đường dẫn thật để `touch()`, thời điểm ghi theo
 * đồng hồ của test (`travel()` dời được), và hỏi `lastModified()` từng file thì nổ, vì trên S3 đó
 * là một request HEAD cho mỗi file.
 */
class ObjectStorageFake extends InMemoryFilesystemAdapter
{
    public function write(string $path, string $contents, Config $config): void
    {
        parent::write($path, $contents, $config->withDefaults(['timestamp' => Date::now()->getTimestamp()]));
    }

    public function lastModified(string $path): FileAttributes
    {
        throw new LogicException("lastModified('{$path}') là một request HEAD trên S3; lấy thời điểm ghi từ listContents().");
    }
}
