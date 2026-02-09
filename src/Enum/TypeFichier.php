<?php
namespace App\Entity\Enum;

enum TypeFichier: string
{
    case AUDIO = 'audio';
    case VIDEO = 'video';
    
    public static function getValues(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
}
