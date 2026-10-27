<?php

namespace App\Filament\Concerns;

use App\Exceptions\WorkflowException;
use App\Models\StaffUser;
use App\Support\Actor;
use Filament\Notifications\Notification;

/** Выполнить бизнес-действие и показать результат или понятную ошибку сотруднику. */
trait RunsWorkflow
{
    protected static function actor(): Actor
    {
        /** @var StaffUser $staff */
        $staff = auth('staff')->user();

        return Actor::staff($staff);
    }

    protected static function run(callable $action, string $success): bool
    {
        try {
            $action(static::actor());
        } catch (WorkflowException $e) {
            Notification::make()->title($e->getMessage())->danger()->persistent()->send();

            return false;
        }
        Notification::make()->title($success)->success()->send();

        return true;
    }
}
