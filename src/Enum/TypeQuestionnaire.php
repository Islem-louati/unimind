<?php

/*namespace App\Enum;

enum TypeQuestionnaire: string
{
    case STRESS = 'stress';  
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
            $choices[$case->getLabel()] = $case->value;
        }
        return $choices;
    }
    
    public function getLabel(): string
    {
        return match($this) {
            self::STRESS => 'Stress',  
            self::DEPRESSION => 'Dépression',
            self::ANXIETE => 'Anxiété',
            self::SOMMEIL => 'Sommeil',
            self::BIENETRE => 'Bien-être',
        };
    }
    
    public static function isValid(string $type): bool
    {
        return in_array($type, self::getValues());
    }
}*/



namespace App\Enum;

enum TypeQuestionnaire: string
{
    case STRESS = 'stress';
    case ANXIETE = 'anxiete';
    case DEPRESSION = 'depression';
    case BIEN_ETRE = 'bienetre';
    case SOMMEIL = 'sommeil';

    public function getLabel(): string
    {
        return match($this) {
            self::STRESS     => 'Stress',
            self::ANXIETE    => 'Anxiété',
            self::DEPRESSION => 'Dépression',
            self::BIEN_ETRE  => 'Bien-être',
            self::SOMMEIL    => 'Sommeil',
        };
    }

    // ✅ CORRECTION : Ajout de getValues() utilisé dans Questionnaire::setType()
    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    // ✅ CORRECTION : Ajout de isValid() utilisé dans certaines validations
    public static function isValid(string $type): bool
    {
        return in_array($type, self::getValues(), true);
    }

    public static function getChoices(): array
    {
        return [
            'Stress'      => 'stress',
            'Anxiété'     => 'anxiete',
            'Dépression'  => 'depression',
            'Bien-être'   => 'bienetre',
            'Sommeil'     => 'sommeil',
        ];
    }
}