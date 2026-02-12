<?php

namespace App\Security;

use App\Entity\Traitement;
use App\Entity\SuiviTraitement;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class TraitementVoter extends Voter
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, ['VIEW', 'EDIT', 'DELETE', 'MANAGE_SUIVIS'])
            && ($subject instanceof Traitement || $subject instanceof SuiviTraitement);
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Admin peut tout faire
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($subject instanceof Traitement) {
            return $this->voteOnTraitement($attribute, $subject, $user);
        } elseif ($subject instanceof SuiviTraitement) {
            return $this->voteOnSuivi($attribute, $subject, $user);
        }

        return false;
    }

    private function voteOnTraitement(string $attribute, Traitement $traitement, User $user): bool
    {
        switch ($attribute) {
            case 'VIEW':
                return $this->canViewTraitement($traitement, $user);

            case 'EDIT':
            case 'DELETE':
                return $this->canEditTraitement($traitement, $user);

            case 'MANAGE_SUIVIS':
                return $this->canManageSuivis($traitement, $user);

            default:
                return false;
        }
    }

    private function voteOnSuivi(string $attribute, SuiviTraitement $suivi, User $user): bool
    {
        switch ($attribute) {
            case 'VIEW':
                return $this->canViewSuivi($suivi, $user);

            case 'EDIT':
            case 'DELETE':
                return $this->canEditSuivi($suivi, $user);

            case 'MANAGE_SUIVIS':
                return $this->canManageSuivis($suivi->getTraitement(), $user);

            default:
                return false;
        }
    }

    private function canViewTraitement(Traitement $traitement, User $user): bool
    {
        // Responsable étudiant peut tout voir
        if ($this->security->isGranted('ROLE_RESPONSABLE_ETUDIANT')) {
            return true;
        }

        // Psychologue peut voir ses traitements
        if ($this->security->isGranted('ROLE_PSYCHOLOGUE') && $traitement->getPsychologue() === $user) {
            return true;
        }

        // Étudiant peut voir ses traitements
        if ($this->security->isGranted('ROLE_ETUDIANT') && $traitement->getEtudiant() === $user) {
            return true;
        }

        return false;
    }

    private function canEditTraitement(Traitement $traitement, User $user): bool
    {
        // Psychologue peut modifier ses traitements
        if ($this->security->isGranted('ROLE_PSYCHOLOGUE') && $traitement->getPsychologue() === $user) {
            return true;
        }

        return false;
    }

    private function canManageSuivis(Traitement $traitement, User $user): bool
    {
        // Psychologue peut gérer les suivis de ses traitements
        if ($this->security->isGranted('ROLE_PSYCHOLOGUE') && $traitement->getPsychologue() === $user) {
            return true;
        }

        // Étudiant peut gérer ses suivis (effectuer, commenter)
        if ($this->security->isGranted('ROLE_ETUDIANT') && $traitement->getEtudiant() === $user) {
            return true;
        }

        return false;
    }

    private function canViewSuivi(SuiviTraitement $suivi, User $user): bool
    {
        return $this->canViewTraitement($suivi->getTraitement(), $user);
    }

    private function canEditSuivi(SuiviTraitement $suivi, User $user): bool
    {
        // Psychologue peut modifier les suivis de ses traitements
        if ($this->security->isGranted('ROLE_PSYCHOLOGUE') && $suivi->getTraitement()->getPsychologue() === $user) {
            return true;
        }

        // Étudiant peut modifier ses suivis (uniquement pour marquer comme effectué et commenter)
        if ($this->security->isGranted('ROLE_ETUDIANT') && $suivi->getTraitement()->getEtudiant() === $user) {
            // L'étudiant ne peut pas modifier un suivi déjà validé
            return !$suivi->isValide();
        }

        return false;
    }
}
