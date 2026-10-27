<?php

namespace App\Support;

use App\Enums\ActorType;
use App\Models\StaffUser;
use App\Models\User;

/** Кто выполняет действие: сотрудник, клиент, система или интеграция. Пишется в историю. */
final class Actor
{
    private function __construct(
        public readonly ActorType $type,
        public readonly ?int $id = null,
        public readonly ?StaffUser $staff = null,
        public readonly ?User $user = null,
    ) {}

    public static function staff(StaffUser $staff): self
    {
        return new self(ActorType::Staff, $staff->id, staff: $staff);
    }

    public static function user(User $user): self
    {
        return new self(ActorType::User, $user->id, user: $user);
    }

    public static function system(): self
    {
        return new self(ActorType::System);
    }

    public static function integration(): self
    {
        return new self(ActorType::Integration);
    }

    /** Текущий пользователь запроса (сотрудник админки или клиент API), иначе — система. */
    public static function current(): self
    {
        $staff = auth('staff')->user();
        if ($staff instanceof StaffUser) {
            return self::staff($staff);
        }
        $user = auth('sanctum')->user();
        if ($user instanceof User) {
            return self::user($user);
        }

        return self::system();
    }
}
