<?php

namespace App\Entity;

use App\Enum\StatutTraitement;
use App\Enum\CategorieTraitement;
use App\Enum\PrioriteTraitement;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'traitement')]
#[ORM\HasLifecycleCallbacks]
class Traitement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $traitement_id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $titre;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'string', length: 255)]
    private string $type;

    #[ORM\Column(type: 'string', length: 255)]
    private string $categorie;

    #[ORM\Column(type: 'integer')]
    private int $duree_jours;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $dosage = null;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $date_debut;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $date_fin = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $statut;

    #[ORM\Column(type: 'string', length: 255)]
    private string $priorite;

    #[ORM\Column(type: 'text')]
    private string $objectif_therapeutique;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'traitementsPsychologue')]
    #[ORM\JoinColumn(name: 'psychologue_id', referencedColumnName: 'user_id', nullable: false)]
    private ?User $psychologue = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'traitementsEtudiant')]
    #[ORM\JoinColumn(name: 'etudiant_id', referencedColumnName: 'user_id', nullable: false)]
    private ?User $etudiant = null;

    #[ORM\OneToMany(mappedBy: 'traitement', targetEntity: SuiviTraitement::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $suivis;

    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->statut = StatutTraitement::EN_COURS->value;
        $this->priorite = PrioriteTraitement::MOYENNE->value;
        $this->categorie = CategorieTraitement::COGNITIF->value;
        $this->suivis = new ArrayCollection();
    }

    // Getters et setters
    public function getId(): ?int
    {
        return $this->traitement_id;
    }

    public function getTraitementId(): ?int
    {
        return $this->traitement_id;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getCategorie(): string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): self
    {
        if (!in_array($categorie, CategorieTraitement::getValues(), true)) {
            throw new \InvalidArgumentException('Catégorie de traitement invalide');
        }
        $this->categorie = $categorie;
        return $this;
    }

    public function getCategorieEnum(): CategorieTraitement
    {
        return CategorieTraitement::from($this->categorie);
    }

    public function setCategorieEnum(CategorieTraitement $categorie): self
    {
        $this->categorie = $categorie->value;
        return $this;
    }

    public function getDureeJours(): int
    {
        return $this->duree_jours;
    }

    public function setDureeJours(int $duree_jours): self
    {
        $this->duree_jours = $duree_jours;
        return $this;
    }

    public function getDosage(): ?string
    {
        return $this->dosage;
    }

    public function setDosage(?string $dosage): self
    {
        $this->dosage = $dosage;
        return $this;
    }

    public function getDateDebut(): \DateTimeInterface
    {
        return $this->date_debut;
    }

    public function setDateDebut(\DateTimeInterface $date_debut): self
    {
        $this->date_debut = $date_debut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->date_fin;
    }

    public function setDateFin(?\DateTimeInterface $date_fin): self
    {
        $this->date_fin = $date_fin;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        if (!in_array($statut, StatutTraitement::getValues(), true)) {
            throw new \InvalidArgumentException('Statut de traitement invalide');
        }
        $this->statut = $statut;
        return $this;
    }

    public function getStatutEnum(): StatutTraitement
    {
        return StatutTraitement::from($this->statut);
    }

    public function setStatutEnum(StatutTraitement $statut): self
    {
        $this->statut = $statut->value;
        return $this;
    }

    public function getPriorite(): string
    {
        return $this->priorite;
    }

    public function setPriorite(string $priorite): self
    {
        if (!in_array($priorite, PrioriteTraitement::getValues(), true)) {
            throw new \InvalidArgumentException('Priorité de traitement invalide');
        }
        $this->priorite = $priorite;
        return $this;
    }

    public function getPrioriteEnum(): PrioriteTraitement
    {
        return PrioriteTraitement::from($this->priorite);
    }

    public function setPrioriteEnum(PrioriteTraitement $priorite): self
    {
        $this->priorite = $priorite->value;
        return $this;
    }

    public function getObjectifTherapeutique(): string
    {
        return $this->objectif_therapeutique;
    }

    public function setObjectifTherapeutique(string $objectif_therapeutique): self
    {
        $this->objectif_therapeutique = $objectif_therapeutique;
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

    public function getPsychologue(): ?User
    {
        return $this->psychologue;
    }

    public function setPsychologue(?User $psychologue): self
    {
        // Vérifier que l'utilisateur est bien un psychologue
        if ($psychologue && !$psychologue->isPsychologue()) {
            throw new \InvalidArgumentException('L\'utilisateur doit être un psychologue');
        }

        $this->psychologue = $psychologue;
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

    // Méthodes pour les suivis
    public function getSuivis(): Collection
    {
        return $this->suivis;
    }

    public function addSuivi(SuiviTraitement $suivi): self
    {
        if (!$this->suivis->contains($suivi)) {
            $this->suivis[] = $suivi;
            $suivi->setTraitement($this);
        }

        return $this;
    }

    public function removeSuivi(SuiviTraitement $suivi): self
    {
        if ($this->suivis->removeElement($suivi)) {
            // set the owning side to null (unless already changed)
            if ($suivi->getTraitement() === $this) {
                $suivi->setTraitement(null);
            }
        }

        return $this;
    }

    // Méthodes utilitaires
    public function __toString(): string
    {
        return sprintf('%s - %s', $this->titre, $this->etudiant ? $this->etudiant->getFullName() : 'N/A');
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updated_at = new \DateTime();
    }

    // Méthodes pour vérifier le statut
    public function isEnCours(): bool
    {
        return $this->statut === StatutTraitement::EN_COURS->value;
    }

    public function isTermine(): bool
    {
        return $this->statut === StatutTraitement::TERMINE->value;
    }

    public function isSuspendu(): bool
    {
        return $this->statut === StatutTraitement::SUSPENDU->value;
    }

    // Méthodes pour vérifier la priorité
    public function isPrioriteBasse(): bool
    {
        return $this->priorite === PrioriteTraitement::BASSE->value;
    }

    public function isPrioriteMoyenne(): bool
    {
        return $this->priorite === PrioriteTraitement::MOYENNE->value;
    }

    public function isPrioriteHaute(): bool
    {
        return $this->priorite === PrioriteTraitement::HAUTE->value;
    }

    // Méthodes pour calculer la progression
    public function getJoursRestants(): ?int
    {
        if ($this->date_fin === null) {
            return $this->duree_jours;
        }

        $now = new \DateTime();
        if ($now > $this->date_fin) {
            return 0;
        }

        $interval = $now->diff($this->date_fin);
        return $interval->days;
    }

    public function getJoursEcoules(): int
    {
        $now = new \DateTime();
        if ($now < $this->date_debut) {
            return 0;
        }

        $interval = $this->date_debut->diff($now);
        return min($interval->days, $this->duree_jours);
    }

    public function getPourcentageCompletion(): float
    {
        if ($this->duree_jours === 0) {
            return 0;
        }

        $joursEcoules = $this->getJoursEcoules();
        $pourcentage = ($joursEcoules / $this->duree_jours) * 100;

        return min($pourcentage, 100);
    }

    // Méthode pour vérifier si le traitement est en retard
    public function isEnRetard(): bool
    {
        if ($this->isTermine() || $this->isSuspendu()) {
            return false;
        }

        $now = new \DateTime();
        if ($this->date_fin && $now > $this->date_fin) {
            return true;
        }

        return false;
    }

    // Méthode pour calculer la date de fin estimée
    public function getDateFinEstimee(): \DateTimeInterface
    {
        if ($this->date_fin) {
            return $this->date_fin;
        }

        $dateFin = \DateTime::createFromInterface($this->date_debut);
        $dateFin->modify("+{$this->duree_jours} days");

        return $dateFin;
    }

    // Méthode pour obtenir les statuts sous forme lisible
    public function getStatutLabel(): string
    {
        return $this->getStatutEnum()->getLabel();
    }

    public function getCategorieLabel(): string
    {
        return $this->getCategorieEnum()->getLabel();
    }

    public function getPrioriteLabel(): string
    {
        return $this->getPrioriteEnum()->getLabel();
    }

    // Méthode pour obtenir un résumé du traitement
    public function getResume(): string
    {
        return sprintf(
            "Traitement: %s\n" .
                "Patient: %s\n" .
                "Psychologue: %s\n" .
                "Catégorie: %s\n" .
                "Statut: %s\n" .
                "Priorité: %s\n" .
                "Durée: %d jours\n" .
                "Progression: %.1f%%\n" .
                "Date début: %s\n" .
                "Date fin estimée: %s",
            $this->titre,
            $this->etudiant ? $this->etudiant->getFullName() : 'Non défini',
            $this->psychologue ? $this->psychologue->getFullName() : 'Non défini',
            $this->getCategorieLabel(),
            $this->getStatutLabel(),
            $this->getPrioriteLabel(),
            $this->duree_jours,
            $this->getPourcentageCompletion(),
            $this->date_debut->format('d/m/Y'),
            $this->getDateFinEstimee()->format('d/m/Y')
        );
    }

    // Méthode pour créer un traitement
    public static function create(
        string $titre,
        string $description,
        User $psychologue,
        User $etudiant,
        int $dureeJours,
        \DateTimeInterface $dateDebut,
        string $objectifTherapeutique,
        ?string $type = null,
        ?string $dosage = null,
        ?CategorieTraitement $categorie = null,
        ?PrioriteTraitement $priorite = null
    ): self {
        $traitement = new self();
        $traitement->setTitre($titre);
        $traitement->setDescription($description);
        $traitement->setPsychologue($psychologue);
        $traitement->setEtudiant($etudiant);
        $traitement->setDureeJours($dureeJours);
        $traitement->setDateDebut($dateDebut);
        $traitement->setObjectifTherapeutique($objectifTherapeutique);

        if ($type) {
            $traitement->setType($type);
        }

        if ($dosage) {
            $traitement->setDosage($dosage);
        }

        if ($categorie) {
            $traitement->setCategorieEnum($categorie);
        }

        if ($priorite) {
            $traitement->setPrioriteEnum($priorite);
        }

        return $traitement;
    }
}
