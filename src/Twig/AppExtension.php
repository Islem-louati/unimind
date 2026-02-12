<?php

namespace App\Twig;

use App\Entity\Enum\CategorieTraitement;
use App\Entity\Enum\PrioriteTraitement;
use App\Entity\Enum\StatutTraitement;
use App\Entity\Enum\Ressenti;
use App\Entity\Enum\SaisiPar;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('enum_label', [$this, 'getEnumLabel']),
            new TwigFilter('categorie_label', [$this, 'getCategorieLabel']),
            new TwigFilter('priorite_label', [$this, 'getPrioriteLabel']),
            new TwigFilter('statut_label', [$this, 'getStatutLabel']),
            new TwigFilter('ressenti_label', [$this, 'getRessentiLabel']),
            new TwigFilter('saisi_par_label', [$this, 'getSaisiParLabel']),
        ];
    }

    public function getEnumLabel($value): string
    {
        if (is_string($value)) {
            return $this->getEnumLabelFromString($value);
        }
        
        if (method_exists($value, 'getLabel')) {
            return $value->getLabel();
        }
        
        return (string) $value;
    }

    private function getEnumLabelFromString(string $value): string
    {
        return match($value) {
            'BIEN' => 'Bien',
            'MOYEN' => 'Moyen', 
            'DIFFICILE' => 'Difficile',
            'TRES_BIEN' => 'Très bien',
            'TRES_DIFFICILE' => 'Très difficile',
            'ANXIETE' => 'Anxiété',
            'STRESS' => 'Stress',
            'CALME' => 'Calme',
            'ENERGIQUE' => 'Énergique',
            'FATIGUE' => 'Fatigué',
            'DOUTEUX' => 'Douteux',
            'CONFIANT' => 'Confiant',
            'FAIBLE' => 'Faible',
            'MOYEN' => 'Moyen',
            'ELEVE' => 'Élevé',
            'URGENT' => 'Urgent',
            'NON_COMMENCE' => 'Non commencé',
            'EN_COURS' => 'En cours',
            'TERMINE' => 'Terminé',
            'SUSPENDU' => 'Suspendu',
            'ANNULE' => 'Annulé',
            'PSYCHOLOGUE' => 'Psychologue',
            'ETUDIANT' => 'Étudiant',
            'RESPONSABLE_ETUDIANT' => 'Responsable étudiant',
            'ADMIN' => 'Administrateur',
            default => $value
        };
    }

    public function getCategorieLabel(?string $categorie): string
    {
        return $this->getEnumLabelFromString($categorie);
    }

    public function getPrioriteLabel(?string $priorite): string
    {
        return $this->getEnumLabelFromString($priorite);
    }

    public function getStatutLabel(?string $statut): string
    {
        return $this->getEnumLabelFromString($statut);
    }

    public function getRessentiLabel(?string $ressenti): string
    {
        return $this->getEnumLabelFromString($ressenti);
    }

    public function getSaisiParLabel(?string $saisiPar): string
    {
        return $this->getEnumLabelFromString($saisiPar);
    }
}
