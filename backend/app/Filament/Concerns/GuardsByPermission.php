<?php

namespace App\Filament\Concerns;

use App\Models\StaffUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Права раздела админки по справочнику App\Support\Permissions
 * и ограничение данных регионами сотрудника.
 *
 * В ресурсе задаются:
 *   protected static ?string $viewPermission  — кто видит раздел;
 *   protected static ?string $editPermission  — кто создаёт и меняет (null — только чтение);
 *   protected static ?string $regionColumn    — колонка региона для фильтрации (null — без фильтра).
 * Удаление выключено: деньги, документы и история не удаляются.
 */
trait GuardsByPermission
{
    protected static function staff(): ?StaffUser
    {
        $user = auth('staff')->user();

        return $user instanceof StaffUser ? $user : null;
    }

    protected static function allows(?string $permission): bool
    {
        return $permission !== null && (bool) static::staff()?->can($permission);
    }

    public static function canViewAny(): bool
    {
        return static::allows(static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::allows(static::$editPermission ?? null);
    }

    public static function canEdit(Model $record): bool
    {
        return static::allows(static::$editPermission ?? null);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $column = static::$regionColumn ?? null;
        $staff = static::staff();
        if ($column !== null && $staff !== null) {
            $ids = $staff->regionIds();
            if ($ids !== null) {
                $query->whereIn($column, $ids);
            }
        }

        return $query;
    }
}
