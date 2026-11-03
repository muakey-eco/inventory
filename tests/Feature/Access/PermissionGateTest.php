<?php

use App\Inventory\Access\MissingPermission;
use App\Inventory\Access\Permission;
use App\Inventory\Access\PermissionGate;
use App\Inventory\Access\PermissionGroup;
use App\Inventory\Dispatch\DispatchFreeze;
use App\Inventory\Intake\ImportReversal;
use App\Inventory\Reports\ProfitReport;
use App\Inventory\Reports\StockReport;
use App\Inventory\Reveal\ContentReveal;
use App\Inventory\Stock\ContentExposure;
use App\Inventory\Stock\SlotStatus;
use App\Inventory\Stock\StockDefect;
use App\Inventory\Stock\StockVoid;
use App\Inventory\Stock\VoidReason;
use App\Inventory\Warranty\ReplacementDelivery;
use App\Models\ApiKey;
use App\Models\Batch;
use App\Models\DefectReport;
use App\Models\Dispatch;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\SecurityLogEntry;
use App\Models\Slot;
use App\Models\StockUnit;
use App\Models\Supplier;
use App\Models\SupplierClaim;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/*
 * Kho chỉ biết Quyền (ADR 0012): mỗi thao tác kiểm đúng Quyền của nó, không Quyền nào bao trùm
 * Quyền khác. Mỗi dòng dưới đây là một thao tác đại diện của một Quyền.
 */

/**
 * Thao tác có bị chặn vì thiếu Quyền không: kiểm tra trả `false`, hoặc thao tác ném lỗi thiếu
 * Quyền. Lỗi khác (dữ liệu giả không hợp lệ) nghĩa là đã qua cửa Quyền.
 */
function deniedByPermission(User $user, Closure $attempt): bool
{
    try {
        return $attempt($user) === false;
    } catch (MissingPermission) {
        return true;
    } catch (Throwable) {
        return false;
    }
}

/**
 * Thao tác đại diện của mỗi Quyền.
 */
function permissionAttempt(Permission $permission): Closure
{
    return match ($permission) {
        Permission::StockView => fn (User $user) => Gate::forUser($user)->allows('viewAny', Product::class),
        Permission::CostView => fn (User $user) => app(StockReport::class)->seesCost($user),
        Permission::Intake => fn (User $user) => Gate::forUser($user)->allows('create', Batch::class),
        Permission::IntakeReverse => fn (User $user) => app(ImportReversal::class)->plan($user, new Batch),
        Permission::SupplierManage => fn (User $user) => Gate::forUser($user)->allows('viewAny', Supplier::class),
        Permission::SupplierClaim => fn (User $user) => Gate::forUser($user)->allows('viewAny', SupplierClaim::class),
        Permission::Dispatch => fn (User $user) => Gate::forUser($user)->allows('viewAny', Dispatch::class),
        Permission::DefectReport => fn (User $user) => Gate::forUser($user)->allows('viewAny', DefectReport::class),
        Permission::ReplacementApprove => fn (User $user) => app(ReplacementDelivery::class)->approve($user, new DefectReport),
        Permission::StockVoid => fn (User $user) => app(StockVoid::class)->voidSlot($user, new Slot, VoidReason::WrongDelivery),
        Permission::StockDefect => fn (User $user) => app(StockDefect::class)->restore($user, new StockUnit, 'Nhà cung cấp sửa xong'),
        Permission::ContentExposure => fn (User $user) => app(ContentExposure::class)->canVoid($user),
        Permission::RevealInStock => fn (User $user) => app(ContentReveal::class)->canRevealInStock($user, (new Slot)->forceFill(['status' => SlotStatus::InStock])),
        Permission::CatalogManage => fn (User $user) => Gate::forUser($user)->allows('create', Product::class),
        Permission::ReportProfit => fn (User $user) => app(ProfitReport::class)->canView($user),
        Permission::StaffManage => fn (User $user) => Gate::forUser($user)->allows('viewAny', User::class),
        Permission::SalesChannelManage => fn (User $user) => Gate::forUser($user)->allows('viewAny', SalesChannel::class),
        Permission::ApiKeyManage => fn (User $user) => Gate::forUser($user)->allows('viewAny', ApiKey::class),
        Permission::DispatchFreeze => fn (User $user) => app(DispatchFreeze::class)->freeze($user, 'Khôi phục từ backup'),
        Permission::LogView => fn (User $user) => Gate::forUser($user)->allows('viewAny', SecurityLogEntry::class),
        Permission::DeadlineOverride => throw new LogicException('Vượt hạn cần dữ liệu ngoài hạn: soát trong DefectReportTest và CorrectiveDeliveryTest.'),
    };
}

it('mỗi Quyền: thiếu nó thì bị chặn dù có mọi Quyền khác, có đúng nó thì làm được', function (Permission $permission) {
    $others = array_values(array_filter(Permission::cases(), fn (Permission $other): bool => $other !== $permission));

    expect(deniedByPermission(staffMember(...$others), permissionAttempt($permission)))->toBeTrue()
        ->and(deniedByPermission(staffMember($permission), permissionAttempt($permission)))->toBeFalse();
})->with(fn (): array => array_values(array_filter(Permission::cases(), fn (Permission $permission): bool => $permission !== Permission::DeadlineOverride)));

it('Quyền lấy từ Permission platform, bỏ qua Permission không phải Quyền', function () {
    expect(Permission::fromPlatform(['dispatch', 'access', 'admin', 'stock.view']))->toBe([Permission::StockView, Permission::Dispatch]);
});

it('mỗi Quyền mang nhãn và nhóm tiếng Việt', function () {
    expect(Permission::DeadlineOverride->label())->toBe('Vượt hạn')
        ->and(Permission::DeadlineOverride->group())->toBe(PermissionGroup::Dispatch)
        ->and(Permission::StaffManage->group()->label())->toBe('Quản trị kho');
});

it('nhân viên bị Khoá nhân viên không qua Quyền nào, kể cả Quyền mình có', function () {
    $manager = staffMember(Permission::StaffManage);
    $manager->forceFill(['deactivated_at' => now()])->save();

    expect(app(PermissionGate::class)->allows($manager, Permission::StaffManage))->toBeFalse();
});

it('thiếu Quyền thì báo lỗi nêu tên Quyền', function () {
    expect(fn () => app(PermissionGate::class)->authorize(staffMember(Permission::Dispatch), Permission::Intake))
        ->toThrow(MissingPermission::class, 'Thao tác cần Quyền: Nhập hàng.');
});
