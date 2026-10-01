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
use Filament\Facades\Filament;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Test đồng thời cần dữ liệu commit thật giữa các tiến trình: không bọc transaction.
pest()->extend(TestCase::class)
    ->in('Concurrency');

/**
 * Nhân viên mang các Vai trò cho trước. Cần chạy RoleSeeder trước.
 */
function staffMember(Role ...$roles): User
{
    return tap(User::factory()->create())->assignRole($roles);
}

/**
 * Loại sản phẩm mang bộ Trường nội dung cho trước. Tên tự đánh số để nhiều Loại trong một
 * test không đụng nhau; test nào quan tâm tới tên thì truyền `name:`. Cần RoleSeeder.
 *
 * @param  list<ContentFieldDraft>  $fields
 */
function productTypeOf(StockForm $form, array $fields, mixed ...$overrides): ProductType
{
    static $sequence = 0;

    return app(ProductTypeCatalog::class)->create(staffMember(Role::Owner), new ProductTypeDraft(...[
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
    return app(ProductCatalog::class)->create(staffMember(Role::Owner), new ProductDraft(...[
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

const AUTHENTIK_ISSUER = 'https://auth.shop.test/application/o/kho/';
const AUTHENTIK_UUID = '6f1c2a8e-4b1d-4c5e-9a3f-2d7b8e0c1a55';

/**
 * Cấu hình kho trỏ tới một Authentik giả: discovery, JWKS và token endpoint trả id_token ký từ
 * `$this->idTokenClaims` bằng `$this->idTokenKey`.
 */
function fakeAuthentik(): void
{
    config([
        'services.authentik.issuer' => AUTHENTIK_ISSUER,
        'services.authentik.client_id' => 'kho-client',
        'services.authentik.client_secret' => 'kho-secret',
    ]);

    test()->idTokenClaims = [];
    test()->idTokenKey = authentikKeyPair()['private'];

    Http::fake([
        AUTHENTIK_ISSUER.'.well-known/openid-configuration' => Http::response([
            'issuer' => AUTHENTIK_ISSUER,
            'authorization_endpoint' => 'https://auth.shop.test/application/o/authorize/',
            'token_endpoint' => 'https://auth.shop.test/application/o/token/',
            'jwks_uri' => AUTHENTIK_ISSUER.'jwks/',
        ]),
        AUTHENTIK_ISSUER.'jwks/' => Http::response(['keys' => [authentikKeyPair()['jwk']]]),
        'https://auth.shop.test/application/o/token/' => fn () => Http::response([
            'access_token' => 'access',
            'token_type' => 'Bearer',
            'id_token' => JWT::encode(test()->idTokenClaims, test()->idTokenKey, 'RS256', 'kho-key'),
        ]),
    ]);
}

/**
 * Một cặp khoá RSA cho cả lượt chạy: sinh khoá chậm, mà test chỉ cần nó khác khoá của kẻ giả mạo.
 *
 * @return array{private: string, jwk: array<string, string>}
 */
function authentikKeyPair(bool $forger = false): array
{
    static $pairs = [];

    return $pairs[$forger] ??= (function (): array {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        $rsa = openssl_pkey_get_details($key)['rsa'];
        $base64url = fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return [
            'private' => $private,
            'jwk' => ['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'kho-key', 'n' => $base64url($rsa['n']), 'e' => $base64url($rsa['e'])],
        ];
    })();
}

/**
 * Đi trọn một vòng đăng nhập: vào trang đăng nhập của panel, "đăng nhập ở Authentik", rồi quay
 * về callback. `$claims` đè lên id_token hợp lệ mặc định; `$callback` đè lên query của callback.
 * Cần {@see fakeAuthentik()} trong beforeEach.
 *
 * @param  array<string, mixed>  $claims
 * @param  array<string, string>  $callback
 */
function signInViaAuthentik(array $claims = [], array $callback = []): TestResponse
{
    $location = test()->get(Filament::getLoginUrl())->headers->get('Location');
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

    test()->idTokenClaims = [
        'iss' => AUTHENTIK_ISSUER,
        'aud' => 'kho-client',
        'sub' => AUTHENTIK_UUID,
        'nonce' => $query['nonce'],
        'iat' => time(),
        'exp' => time() + 300,
        'name' => 'An Nguyễn',
        'email' => 'an@shop.test',
        'kho_groups' => ['kho-ban-hang'],
        'amr' => ['pwd', 'mfa'],
        ...$claims,
    ];

    return test()->get(route('filament.admin.auth.authentik.callback', [
        'code' => 'authorization-code',
        'state' => $query['state'],
        ...$callback,
    ]));
}
