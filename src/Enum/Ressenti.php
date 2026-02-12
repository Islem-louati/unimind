<?php

namespace App\Entity\Enum;

enum Ressenti: string
{
    case TRES_BIEN = 'très_bien';
    case BIEN = 'bien';
    case NEUTRE = 'neutre';
    case DIFFICILE = 'difficile';
    case TRES_DIFFICILE = 'très_difficile';

    public function getLabel(): string
    {
        return match($this) {
            self::TRES_BIEN => 'Très bien',
            self::BIEN => 'Bien',
            self::NEUTRE => 'Neutre',
            self::DIFFICILE => 'Difficile',
            self::TRES_DIFFICILE => 'Très difficile',
        };
    }

    public static function getValues(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    public static function getChoices(): array
    {
        return array_combine(
            array_map(fn($case) => $case->getLabel(), self::cases()),
            self::cases()
        );
    }

    public static function getFormChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case->value;
        }
        return $choices;
    }
}
