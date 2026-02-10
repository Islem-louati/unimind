<?php
namespace App\Entity\Enum;

enum TypeNiveau: string
{
    case DEBUTANT = 'débutant';
    case INTERMEDIAIRE = 'intermédiaire';
    case AVANCE = 'avancé';
    
    public static function getValues(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
}
