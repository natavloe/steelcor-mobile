<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Статус утверждения. */
enum ApprovalStatus: string implements HasLabel
{
    use LabelledEnum;

    case Draft = 'draft';
    case Approved = 'approved';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Approved => 'Утверждено',
            self::Archived => 'В архиве',
        };
    }
}
