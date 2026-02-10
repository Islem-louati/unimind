<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'suivi_traitement')]
#[ORM\HasLifecycleCallbacks]
class SuiviTraitement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $suivi_id = null;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $date_suivi;

    #[ORM\Column(type: 'boolean')]
    private bool $effectue;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $heure_effective = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaires = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $evaluation = null;

    #[ORM\Column(type: 'boolean')]
    private bool $valide;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\ManyToOne(targetEntity: Traitement::class, inversedBy: 'suivis')]
    #[ORM\JoinColumn(name: 'traitement_id', referencedColumnName: 'traitement_id', nullable: false)]
    private ?Traitement $traitement = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->date_suivi = new \DateTime();
        $this->effectue = false;
        $this->valide = false;
    }

    // Getters et setters
    public function getId(): ?int
    {
        return $this->suivi_id;
    }

    public function getSuiviId(): ?int
    {
        return $this->suivi_id;
    }

    public function getDateSuivi(): \DateTimeInterface
    {
        return $this->date_suivi;
    }

    public function setDateSuivi(\DateTimeInterface $date_suivi): self
    {
        $this->date_suivi = $date_suivi;
        return $this;
    }

    public function isEffectue(): bool
    {
        return $this->effectue;
    }

    public function setEffectue(bool $effectue): self
    {
        $this->effectue = $effectue;

        // Si marqué comme effectué et pas d'heure, mettre l'heure actuelle
        if ($effectue && $this->heure_effective === null) {
            $this->heure_effective = new \DateTime();
        }

        return $this;
    }

    public function getHeureEffective(): ?\DateTimeInterface
    {
        return $this->heure_effective;
    }

    public function setHeureEffective(?\DateTimeInterface $heure_effective): self
    {
        $this->heure_effective = $heure_effective;
        return $this;
    }

    public function getCommentaires(): ?string
    {
        return $this->commentaires;
    }

    public function setCommentaires(?string $commentaires): self
    {
        $this->commentaires = $commentaires;
        return $this;
    }

    public function getEvaluation(): ?int
    {
        return $this->evaluation;
    }

    public function setEvaluation(?int $evaluation): self
    {
        // Valider que l'évaluation est entre 1 et 5
        if ($evaluation !== null && ($evaluation < 1 || $evaluation > 5)) {
            throw new \InvalidArgumentException('L\'évaluation doit être entre 1 et 5');
        }

        $this->evaluation = $evaluation;
        return $this;
    }

    public function isValide(): bool
    {
        return $this->valide;
    }

    public function setValide(bool $valide): self
    {
        $this->valide = $valide;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeInterface $updated_at): self
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    public function getTraitement(): ?Traitement
    {
        return $this->traitement;
    }

    public function setTraitement(?Traitement $traitement): self
    {
        $this->traitement = $traitement;
        return $this;
    }

    // Méthodes utilitaires
    public function __toString(): string
    {
        return sprintf(
            'Suivi #%d - %s',
            $this->suivi_id,
            $this->date_suivi->format('d/m/Y')
        );
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updated_at = new \DateTime();
    }

    // Méthode pour marquer comme effectué avec l'heure actuelle
    public function marquerEffectue(): self
    {
        $this->effectue = true;
        $this->heure_effective = new \DateTime();
        $this->updateTimestamp();

        return $this;
    }

    // Méthode pour valider le suivi
    public function valider(): self
    {
        if (!$this->effectue) {
            throw new \LogicException('Un suivi non effectué ne peut pas être validé');
        }

        $this->valide = true;
        $this->updateTimestamp();

        return $this;
    }

    // Méthode pour obtenir l'évaluation en étoiles
    public function getEvaluationEtoiles(): ?string
    {
        if ($this->evaluation === null) {
            return null;
        }

        return str_repeat('★', $this->evaluation) . str_repeat('☆', 5 - $this->evaluation);
    }

    // Méthode pour vérifier si le suivi est en retard
    public function isEnRetard(): bool
    {
        $aujourdhui = new \DateTime();
        return !$this->effectue && $this->date_suivi < $aujourdhui;
    }

    // Méthode pour vérifier si le suivi est à venir
    public function isAVenir(): bool
    {
        $aujourdhui = new \DateTime();
        return !$this->effectue && $this->date_suivi > $aujourdhui;
    }

    // Méthode pour vérifier si le suivi est pour aujourd'hui
    public function isAujourdhui(): bool
    {
        $aujourdhui = new \DateTime();
        return $this->date_suivi->format('Y-m-d') === $aujourdhui->format('Y-m-d');
    }

    // Méthode pour obtenir l'âge du suivi
    public function getAge(): string
    {
        $now = new \DateTime();
        $interval = $this->created_at->diff($now);

        if ($interval->y > 0) {
            return $interval->y . ' an(s)';
        } elseif ($interval->m > 0) {
            return $interval->m . ' mois';
        } elseif ($interval->d > 0) {
            return $interval->d . ' jour(s)';
        } elseif ($interval->h > 0) {
            return $interval->h . ' heure(s)';
        } else {
            return $interval->i . ' minute(s)';
        }
    }

    // Méthode pour obtenir la durée depuis la date de suivi
    public function getDureeDepuisSuivi(): string
    {
        $now = new \DateTime();
        $interval = $this->date_suivi->diff($now);

        if ($interval->y > 0) {
            return $interval->y . ' an(s)';
        } elseif ($interval->m > 0) {
            return $interval->m . ' mois';
        } elseif ($interval->d > 0) {
            return $interval->d . ' jour(s)';
        } else {
            return 'Aujourd\'hui';
        }
    }

    // Méthode pour obtenir un résumé du suivi
    public function getResume(): string
    {
        $etat = $this->effectue ? 'Effectué' : ($this->isEnRetard() ? 'En retard' : 'À faire');

        return sprintf(
            "Suivi #%d\n" .
                "Date: %s\n" .
                "État: %s\n" .
                "Effectué: %s\n" .
                "Heure: %s\n" .
                "Validé: %s\n" .
                "Évaluation: %s\n" .
                "Traitement: %s",
            $this->suivi_id,
            $this->date_suivi->format('d/m/Y'),
            $etat,
            $this->effectue ? 'Oui' : 'Non',
            $this->heure_effective ? $this->heure_effective->format('H:i') : 'N/A',
            $this->valide ? 'Oui' : 'Non',
            $this->getEvaluationEtoiles() ?? 'Non évalué',
            $this->traitement ? $this->traitement->getTitre() : 'Non défini'
        );
    }

    // Méthode pour obtenir le jour de la semaine
    public function getJourSemaine(): string
    {
        $jours = [
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
            7 => 'Dimanche'
        ];

        $numeroJour = (int)$this->date_suivi->format('N');
        return $jours[$numeroJour] ?? 'Jour inconnu';
    }

    // Méthode pour créer un suivi
    public static function create(
        Traitement $traitement,
        \DateTimeInterface $dateSuivi,
        bool $effectue = false,
        ?string $commentaires = null,
        ?int $evaluation = null
    ): self {
        $suivi = new self();
        $suivi->setTraitement($traitement);
        $suivi->setDateSuivi($dateSuivi);
        $suivi->setEffectue($effectue);
        $suivi->setCommentaires($commentaires);
        $suivi->setEvaluation($evaluation);

        return $suivi;
    }

    // Méthode pour obtenir le statut détaillé
    public function getStatutDetaille(): string
    {
        if ($this->valide) {
            return 'Validé';
        }

        if ($this->effectue) {
            return 'Effectué (en attente de validation)';
        }

        if ($this->isEnRetard()) {
            return 'En retard';
        }

        if ($this->isAujourdhui()) {
            return 'À faire aujourd\'hui';
        }

        if ($this->isAVenir()) {
            return 'À venir';
        }

        return 'Non effectué';
    }

    // Méthode pour obtenir la couleur selon le statut
    public function getCouleurStatut(): string
    {
        if ($this->valide) {
            return 'success';
        }

        if ($this->effectue) {
            return 'info';
        }

        if ($this->isEnRetard()) {
            return 'danger';
        }

        if ($this->isAujourdhui()) {
            return 'warning';
        }

        return 'secondary';
    }
}
