<?php

use App\Inventory\Access\Role;
use App\Inventory\Catalog\ContentFieldDraft;
use App\Inventory\Catalog\ProductCatalog;
use App\Inventory\Catalog\ProductDraft;
use App\Inventory\Catalog\ProductTypeCatalog;
use App\Inventory\Catalog\ProductTypeDraft;
use App\Inventory\Catalog\StockForm;
use App\Inventory\Intake\BatchDraft;
use App\Inventory\Intake\BatchIntake;
use App\Inventory\Intake\BatchLineDraft;
use App\Inventory\Intake\ExpiryRule;
use App\Models\Batch;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use Tests\Support\ObjectStorageFake;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Test đồng thời cần dữ liệu commit thật giữa các tiến trình: không bọc transaction.
pest()->extend(TestCase::class)
    ->in('Concurrency');

/**
 * Nhân viên mà kho đã thấy mang các Vai trò cho trước (bản chụp Vai trò). Qua `actingAs` thì
 * platform giả trả đúng các Permission đó.
 */
function staffMember(Role ...$roles): User
{
    return User::factory()->create(['roles' => $roles]);
}

/**
 * Loại sản phẩm mang bộ Trường nội dung cho trước. Tên tự đánh số để nhiều Loại trong một
 * test không đụng nhau; test nào quan tâm tới tên thì truyền `name:`.
 *
 * @param  list<ContentFieldDraft>  $fields
 */
function productTypeOf(StockForm $form, array $fields, mixed ...$overrides): ProductType
{
    static $sequence = 0;

    return app(ProductTypeCatalog::class)->create(staffMember(Role::Admin), new ProductTypeDraft(...[
        'name' => 'Loại '.++$sequence,
        'form' => $form,
        'fields' => $fields,
        ...$overrides,
    ]));
}

/**
 * Sản phẩm kèm một Loại sản phẩm dựng riêng cho nó: dạng hay gặp nhất trong test, khi test
 * chỉ cần "một Sản phẩm khai bộ Trường nội dung thế này".
 *
 * @param  list<ContentFieldDraft>  $fields
 */
function productOf(StockForm $form, array $fields, string $name, string $code, mixed ...$overrides): Product
{
    return productIn(productTypeOf($form, $fields), $name, $code, ...$overrides);
}

/**
 * Sản phẩm thuộc một Loại sản phẩm có sẵn, cho test cần nhiều Sản phẩm dùng chung một Loại.
 */
function productIn(ProductType $type, string $name, string $code, mixed ...$overrides): Product
{
    return app(ProductCatalog::class)->create(staffMember(Role::Admin), new ProductDraft(...[
        'productType' => $type,
        'name' => $name,
        'code' => $code,
        ...$overrides,
    ]));
}

/**
 * Tạm đánh dấu Sản phẩm đã có hàng, cho test về cấu hình bị khoá khỏi phải nhập hàng thật.
 */
function withStock(Product $product): Product
{
    $product->forceFill(['stocked_at' => now()])->save();

    return $product;
}

/**
 * Request vào API xuất kho, đã gắn Khoá API. Cần `$this->secret` trong beforeEach; truyền khoá khác
 * để thử một khoá cụ thể. Test nào cần gọi *không* kèm header thì tự dựng request, đừng qua đây.
 */
function apiAs(?string $secret = null): TestCase
{
    return test()->withHeaders(['Authorization' => 'Bearer '.($secret ?? test()->secret)]);
}

/**
 * Nhập và xác nhận một Lô nhập một Dòng nhập vào kho, để test có hàng mà giao. Cần `$this->admin`
 * (Quản trị hoặc Nhập kho) và `$this->supplier` trong beforeEach.
 */
function stockUp(Product $product, string $content, ?ExpiryRule $expiry = null): Batch
{
    $intake = app(BatchIntake::class);

    return $intake->confirm(test()->admin, $intake->submit(test()->admin, new BatchDraft(
        supplier: test()->supplier,
        receivedOn: CarbonImmutable::today(),
        lines: [new BatchLineDraft($product, 100_000, $content, expiry: $expiry)],
    )));
}

/**
 * Thay disk bằng kho object giả như S3 ({@see ObjectStorageFake}): purge chỉ chạy được khi lấy
 * thời điểm ghi từ một lần liệt kê thư mục.
 */
function fakeObjectStorage(string $disk): FilesystemAdapter
{
    $adapter = new ObjectStorageFake;
    $storage = new FilesystemAdapter(new Filesystem($adapter), $adapter);
    Storage::set($disk, $storage);

    return $storage;
}
