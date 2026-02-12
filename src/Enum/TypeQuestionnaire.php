<?php

namespace App\Enum;

enum TypeQuestionnaire: string
{
    case DEPRESSION = 'depression';
    case ANXIETE = 'anxieté';
    case SOMMEIL = 'sommeil';
    case BIENETRE = 'bienetre';

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
    
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->value] = $case->value;
        }
        return $choices;
    }
    
    public function getLabel(): string
    {
        return match($this) {
            self::DEPRESSION => 'Dépression',
            self::ANXIETE => 'Anxiété',
            self::SOMMEIL => 'Sommeil',
            self::BIENETRE => 'Bien-être',
        };
    }
}