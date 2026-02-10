<?php

namespace App\Entity;

use App\Enum\StatutParticipation;
use App\Repository\ParticipationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\Table(name: 'participation')]
#[ORM\HasLifecycleCallbacks]
class Participation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $participation_id = null; // Clé primaire comme demandé

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $date_inscription = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $statut = 'attente';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\ManyToOne(targetEntity: Evenement::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(name: 'evenement_id', referencedColumnName: 'evenement_id', nullable: false)]
    #[Assert\NotNull(message: "L'événement est obligatoire")]
    private ?Evenement $evenement = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(name: 'etudiant_id', referencedColumnName: 'user_id', nullable: false)]
    #[Assert\NotNull(message: "L'étudiant est obligatoire")]
    private ?User $etudiant = null;

    public function __construct()
    {
        $this->date_inscription = new \DateTime();
        $this->created_at = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?int
    {
        return $this->participation_id;
    }

    public function getParticipationId(): ?int
    {
        return $this->participation_id;
    }

    public function getDateInscription(): ?\DateTimeInterface
    {
        return $this->date_inscription;
    }

    public function setDateInscription(\DateTimeInterface $date_inscription): self
    {
        $this->date_inscription = $date_inscription;
        return $this;
    }

    public function getStatut(): StatutParticipation
    {
        return StatutParticipation::from($this->statut);
    }

    public function setStatut(StatutParticipation $statut): self
    {
        $this->statut = $statut->value;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
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

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): self
    {
        $this->evenement = $evenement;
        return $this;
    }

    public function getEtudiant(): ?User
    {
        return $this->etudiant;
    }

    public function setEtudiant(?User $etudiant): self
    {
        // Vérifier que l'utilisateur est bien un étudiant
        if ($etudiant && !$etudiant->isEtudiant()) {
            throw new \InvalidArgumentException('L\'utilisateur doit être un étudiant');
        }

        $this->etudiant = $etudiant;
        return $this;
    }

    // Méthodes utilitaires
    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updated_at = new \DateTime();
    }

    public function isConfirme(): bool
    {
        return $this->statut === 'confirme';
    }

    public function isAttente(): bool
    {
        return $this->statut === 'attente';
    }

    public function isAnnule(): bool
    {
        return $this->statut === 'annule';
    }

    public function confirmer(): self
    {
        $this->statut = 'confirme';
        $this->updateTimestamp();
        return $this;
    }

    public function annuler(): self
    {
        $this->statut = 'annule';
        $this->updateTimestamp();

        // Décrémenter le nombre d'inscrits dans l'événement
        if ($this->evenement && $this->isConfirme()) {
            $this->evenement->decrementNombreInscrits();
        }

        return $this;
    }

    public function mettreEnAttente(): self
    {
        $this->statut = 'attente';
        $this->updateTimestamp();
        return $this;
    }

    public function __toString(): string
    {
        return sprintf(
            'Participation #%d - %s',
            $this->participation_id,
            $this->etudiant ? $this->etudiant->getFullName() : 'N/A'
        );
    }

    // Méthode pour créer une participation
    public static function create(Evenement $evenement, User $etudiant): self
    {
        $participation = new self();
        $participation->setEvenement($evenement);
        $participation->setEtudiant($etudiant);

        // Si l'événement a encore des places disponibles, confirmer directement
        if ($evenement->getPlacesDisponibles() > 0) {
            $participation->confirmer();
            $evenement->incrementNombreInscrits();
        }

        return $participation;
    }
}
