<?php

namespace App\Filament\Resources\Staff;

use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Filament\Support\NavGroup;
use App\Inventory\Access\MissingRole;
use App\Inventory\Access\Role;
use App\Inventory\Staff\LastActiveAdmin;
use App\Inventory\Staff\StaffManager;
use App\Inventory\Staff\StaffMirror;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Danh sách nhân viên trong panel. Nhân viên, tên, email, Vai trò và việc còn quyền vào kho
 * đều đến từ platform (ADR 0011), nên ở đây chỉ đọc; thao tác duy nhất là Khoá nhân viên và
 * mở khoá. Adapter mỏng: mọi thao tác gọi StaffManager, nơi kiểm tra Vai trò, chặn khoá Quản
 * trị đang hoạt động cuối cùng và ghi Nhật ký bảo mật. Không có thao tác xoá nhân viên.
 */
class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::HeThong;

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'nhân viên';

    protected static ?string $pluralModelLabel = 'Nhân viên';

    protected static ?string $navigationLabel = 'Nhân viên';

    protected static ?string $slug = 'nhan-vien';

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Tên')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                // Bản chụp theo lần cuối kho thấy nhân viên (ADR 0011): danh bạ của platform không
                // mang Permission, nên Vai trò đổi trên platform chỉ hiện ở đây khi họ vào kho lại.
                TextColumn::make('roles')
                    ->label('Vai trò')
                    ->tooltip('Theo lần cuối kho thấy nhân viên này')
                    ->badge()
                    // Xếp theo thứ tự khai báo Vai trò cho dễ dò, không theo thứ tự platform trả.
                    ->state(fn (User $record): array => StaffMirror::roleNames($record->roles))
                    ->formatStateUsing(fn (string $state): string => Role::from($state)->label()),
                TextColumn::make('deactivated_at')
                    ->label('Khoá nhân viên')
                    ->badge()
                    ->state(fn (User $record): string => $record->isDeactivated() ? 'Đã khoá' : 'Hoạt động')
                    ->color(fn (User $record): string => $record->isDeactivated() ? 'danger' : 'success'),
                // Tách cột với Khoá nhân viên: mất quyền theo platform tự hết khi platform cấp
                // lại, còn Khoá nhân viên thì chỉ Quản trị mở được. Chỉ thấy được khi nhân viên
                // bị đình chỉ hay bị xoá; mất mỗi quyền vào kho thì danh bạ không báo.
                TextColumn::make('platform_revoked_at')
                    ->label('Platform')
                    ->badge()
                    ->state(fn (User $record): string => $record->platform_revoked_reason === null
                        ? 'Có quyền'
                        : 'Mất quyền: '.$record->platform_revoked_reason->label())
                    ->tooltip(fn (User $record): ?string => $record->platform_revoked_at === null
                        ? null
                        : 'Từ '.$record->platform_revoked_at->format('H:i d/m/Y'))
                    ->color(fn (User $record): string => $record->isRevokedByPlatform() ? 'warning' : 'success'),
            ])
            ->recordActions([
                Action::make('deactivate')
                    ->label('Khoá')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Nhân viên bị đăng xuất ngay và không đăng nhập lại được.')
                    ->visible(fn (User $record): bool => ! $record->isDeactivated())
                    ->action(self::attempt(
                        fn (StaffManager $staff, User $record) => $staff->deactivate(self::actor(), $record),
                        'Đã khoá nhân viên.',
                    )),
                Action::make('reactivate')
                    ->label('Mở khoá')
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->isDeactivated())
                    ->action(self::attempt(
                        fn (StaffManager $staff, User $record) => $staff->reactivate(self::actor(), $record),
                        'Đã mở khoá nhân viên.',
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStaff::route('/'),
        ];
    }

    public static function actor(): User
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }

    /**
     * Chạy thao tác của module Kho, đổi lỗi nghiệp vụ thành thông báo cho Quản trị.
     */
    private static function attempt(Closure $operation, string $success): Closure
    {
        return function (Action $action) use ($operation, $success): void {
            try {
                $action->evaluate($operation);
            } catch (LastActiveAdmin|MissingRole $exception) {
                Notification::make()->danger()->title($exception->getMessage())->send();

                return;
            }

            Notification::make()->success()->title($success)->send();
        };
    }
}
