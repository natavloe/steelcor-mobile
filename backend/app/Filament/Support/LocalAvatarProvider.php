<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Аватар с инициалами, нарисованный на месте (SVG в data-URI).
 * Стандартный провайдер Filament отправляет имя сотрудника на внешний сервис ui-avatars.com — нам это не нужно (152-ФЗ).
 */
class LocalAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = collect(preg_split('/\s+/u', trim((string) Filament::getNameForDefaultAvatar($record))))
            ->map(fn (string $part) => mb_strtoupper(mb_substr(preg_replace('/^[^\p{L}\p{N}]+/u', '', $part), 0, 1)))
            ->filter()->take(2)->join('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" fill="#1e3a8a"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="sans-serif" font-size="26" fill="#fff">'
            .htmlspecialchars($initials, ENT_XML1).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
