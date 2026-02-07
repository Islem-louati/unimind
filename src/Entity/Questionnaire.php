<?php

namespace App\Entity;

use App\Enum\TypeQuestionnaire;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'questionnaire')]
#[ORM\HasLifecycleCallbacks]
class Questionnaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $questionnaire_id = null;

    #[ORM\Column(type: 'string', length: 20, unique: true)]
    private string $code;

    #[ORM\Column(type: 'string', length: 150)]
    private string $nom;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: 'text')]
    private string $interpretat_legere;

    #[ORM\Column(type: 'text')]
    private string $interpretat_modere;

    #[ORM\Column(type: 'text')]
    private string $interpretat_severe;

    #[ORM\Column(type: 'integer')]
    private int $seuil_leger;

    #[ORM\Column(type: 'integer')]
    private int $seuil_modere;

    #[ORM\Column(type: 'integer')]
    private int $seuil_severe;

    #[ORM\Column(type: 'integer')]
    private int $nbre_questions;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'questionnairesAdmin')]
    #[ORM\JoinColumn(name: 'admin_id', referencedColumnName: 'user_id', nullable: false)]
    private ?User $admin = null;

    #[ORM\OneToMany(mappedBy: 'questionnaire', targetEntity: Question::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $questions;

    #[ORM\OneToMany(mappedBy: 'questionnaire', targetEntity: ReponseQuestionnaire::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $reponses;

    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->questions = new ArrayCollection();
        $this->reponses = new ArrayCollection();
    }

    // Getters et setters
    public function getQuestionnaireId(): ?int
    {
        return $this->questionnaire_id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
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
        if (!in_array($type, TypeQuestionnaire::getValues(), true)) {
            throw new \InvalidArgumentException('Type de questionnaire invalide');
        }
        $this->type = $type;
        return $this;
    }

    public function getTypeEnum(): TypeQuestionnaire
    {
        return TypeQuestionnaire::from($this->type);
    }

    public function setTypeEnum(TypeQuestionnaire $type): self
    {
        $this->type = $type->value;
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

    public function getInterpretatLegere(): string
    {
        return $this->interpretat_legere;
    }

    public function setInterpretatLegere(string $interpretat_legere): self
    {
        $this->interpretat_legere = $interpretat_legere;
        return $this;
    }

    public function getInterpretatModere(): string
    {
        return $this->interpretat_modere;
    }

    public function setInterpretatModere(string $interpretat_modere): self
    {
        $this->interpretat_modere = $interpretat_modere;
        return $this;
    }

    public function getInterpretatSevere(): string
    {
        return $this->interpretat_severe;
    }

    public function setInterpretatSevere(string $interpretat_severe): self
    {
        $this->interpretat_severe = $interpretat_severe;
        return $this;
    }

    public function getSeuilLeger(): int
    {
        return $this->seuil_leger;
    }

    public function setSeuilLeger(int $seuil_leger): self
    {
        $this->seuil_leger = $seuil_leger;
        return $this;
    }

    public function getSeuilModere(): int
    {
        return $this->seuil_modere;
    }

    public function setSeuilModere(int $seuil_modere): self
    {
        $this->seuil_modere = $seuil_modere;
        return $this;
    }

    public function getSeuilSevere(): int
    {
        return $this->seuil_severe;
    }

    public function setSeuilSevere(int $seuil_severe): self
    {
        $this->seuil_severe = $seuil_severe;
        return $this;
    }

    public function getNbreQuestions(): int
    {
        return $this->nbre_questions;
    }

    public function setNbreQuestions(int $nbre_questions): self
    {
        $this->nbre_questions = $nbre_questions;
        return $this;
    }

    public function getAdmin(): ?User
    {
        return $this->admin;
    }

    public function setAdmin(?User $admin): self
    {
        // Vérifier que l'utilisateur est bien un admin
        if ($admin && !$admin->isAdmin()) {
            throw new \InvalidArgumentException('L\'utilisateur doit être un administrateur');
        }

        $this->admin = $admin;
        return $this;
    }

    // Méthodes pour les questions
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(Question $question): self
    {
        if (!$this->questions->contains($question)) {
            $this->questions[] = $question;
            $question->setQuestionnaire($this);
        }

        return $this;
    }

    public function removeQuestion(Question $question): self
    {
        if ($this->questions->removeElement($question)) {
            // set the owning side to null (unless already changed)
            if ($question->getQuestionnaire() === $this) {
                $question->setQuestionnaire(null);
            }
        }

        return $this;
    }

    // Méthodes pour les réponses
    public function getReponses(): Collection
    {
        return $this->reponses;
    }

    public function addReponse(ReponseQuestionnaire $reponse): self
    {
        if (!$this->reponses->contains($reponse)) {
            $this->reponses[] = $reponse;
            $reponse->setQuestionnaire($this);
        }

        return $this;
    }

    public function removeReponse(ReponseQuestionnaire $reponse): self
    {
        if ($this->reponses->removeElement($reponse)) {
            // set the owning side to null (unless already changed)
            if ($reponse->getQuestionnaire() === $this) {
                $reponse->setQuestionnaire(null);
            }
        }

        return $this;
    }

    // Méthodes utilitaires
    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->nom, $this->code);
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updated_at = new \DateTime();
    }

    // Méthode pour obtenir l'interprétation selon le score
    public function getInterpretationForScore(int $score): string
    {
        if ($score <= $this->seuil_leger) {
            return $this->interpretat_legere;
        } elseif ($score <= $this->seuil_modere) {
            return $this->interpretat_modere;
        } else {
            return $this->interpretat_severe;
        }
    }

    // Méthode pour obtenir le niveau selon le score
    public function getNiveauForScore(int $score): string
    {
        if ($score <= $this->seuil_leger) {
            return 'léger';
        } elseif ($score <= $this->seuil_modere) {
            return 'modéré';
        } else {
            return 'sévère';
        }
    }

    // Méthode pour vérifier si le questionnaire est complet
    public function isComplete(): bool
    {
        return $this->questions->count() === $this->nbre_questions;
    }

    // Méthode pour obtenir le pourcentage de complétion
    public function getCompletionPercentage(): float
    {
        $questionCount = $this->questions->count();
        if ($this->nbre_questions === 0) {
            return $questionCount > 0 ? 100 : 0;
        }

        $percentage = ($questionCount / $this->nbre_questions) * 100;

        // Limiter à 100%
        return min($percentage, 100);
    }
    // Méthode pour obtenir le type sous forme lisible
    public function getTypeLabel(): string
    {
        return $this->getTypeEnum()->getLabel();
    }

    // Méthode pour obtenir le nombre de réponses
    public function getNombreReponses(): int
    {
        return $this->reponses->count();
    }

    // Méthode pour obtenir le score moyen
    public function getScoreMoyen(): ?float
    {
        if ($this->reponses->isEmpty()) {
            return null;
        }

        $total = 0;
        foreach ($this->reponses as $reponse) {
            $total += $reponse->getScoreTotal();
        }

        return $total / $this->reponses->count();
    }

    // Méthode pour vérifier les seuils
    public function validateSeuils(): bool
    {
        return $this->seuil_leger < $this->seuil_modere &&
            $this->seuil_modere < $this->seuil_severe;
    }

    // Méthode pour générer un code unique
    public function generateCode(): string
    {
        $prefix = strtoupper(substr($this->type, 0, 3));
        $timestamp = time();
        $random = bin2hex(random_bytes(2));

        return sprintf('%s-%s-%s', $prefix, $timestamp, $random);
    }

    // Méthode pour obtenir un résumé
    public function getResume(): string
    {
        return sprintf(
            "Questionnaire: %s\n" .
                "Code: %s\n" .
                "Type: %s\n" .
                "Nombre de questions: %d/%d\n" .
                "Seuils: Léger ≤ %d, Modéré ≤ %d, Sévère > %d\n" .
                "Réponses: %d",
            $this->nom,
            $this->code,
            $this->getTypeLabel(),
            $this->questions->count(),
            $this->nbre_questions,
            $this->seuil_leger,
            $this->seuil_modere,
            $this->seuil_severe,
            $this->getNombreReponses()
        );
    }
    // Méthode pour obtenir les statistiques des réponses
public function getStatistiquesReponses(): array
{
    $statistiques = [
        'total_reponses' => $this->reponses->count(),
        'score_moyen' => null,
        'distribution_niveaux' => [
            'léger' => 0,
            'modéré' => 0,
            'sévère' => 0
        ],
        'besoin_psy' => 0,
        'scores' => []
    ];
    
    if ($this->reponses->isEmpty()) {
        return $statistiques;
    }
    
    $totalScore = 0;
    
    foreach ($this->reponses as $reponse) {
        $totalScore += $reponse->getScoreTotale();
        $statistiques['scores'][] = $reponse->getScoreTotale();
        
        // Distribution des niveaux
        $niveau = $reponse->getNiveau();
        if (isset($statistiques['distribution_niveaux'][$niveau])) {
            $statistiques['distribution_niveaux'][$niveau]++;
        }
        
        // Besoin psy
        if ($reponse->isABesoinPsy()) {
            $statistiques['besoin_psy']++;
        }
    }
    
    $statistiques['score_moyen'] = $totalScore / $this->reponses->count();
    
    return $statistiques;
}
}
