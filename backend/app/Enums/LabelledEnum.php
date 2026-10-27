<?php

namespace App\Enums;

/** Подписи значений для админки и приложения. */
trait LabelledEnum
{
    abstract public function label(): string;

    /** @return array<string, string> значение => подпись (для выпадающих списков Filament). */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
