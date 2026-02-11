<?php

namespace App\Entity;

use App\Enum\TypeQuestionnaire;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
    #[Assert\NotBlank(message: "Le code du questionnaire est obligatoire.")]
    #[Assert\Length(
        min: 3,
        max: 20,
        minMessage: "Le code doit faire au moins {{ limit }} caractères.",
        maxMessage: "Le code ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Assert\Regex(
        pattern: '/^[A-Z0-9\-_]+$/',
        message: "Le code ne peut contenir que des lettres majuscules, chiffres, tirets et underscores."
    )]
    private string $code;

    #[ORM\Column(type: 'string', length: 150)]
    #[Assert\NotBlank(message: "Le nom du questionnaire est obligatoire.")]
    #[Assert\Length(
        min: 3,
        max: 150,
        minMessage: "Le nom doit faire au moins {{ limit }} caractères.",
        maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères."
    )]
    private string $nom;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: "La description est obligatoire.")]
    #[Assert\Length(
        min: 10,
        max: 2000,
        minMessage: "La description doit faire au moins {{ limit }} caractères.",
        maxMessage: "La description ne peut pas dépasser {{ limit }} caractères."
    )]
    private string $description;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\NotBlank(message: "Le type de questionnaire est obligatoire.")]
    #[Assert\Choice(
        callback: [TypeQuestionnaire::class, 'getValues'],
        message: "Le type de questionnaire n'est pas valide. Choisissez parmi : {{ choices }}."
    )]
    private string $type;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: "L'interprétation légère est obligatoire.")]
    #[Assert\Length(
        min: 10,
        max: 1000,
        minMessage: "L'interprétation légère doit faire au moins {{ limit }} caractères.",
        maxMessage: "L'interprétation légère ne peut pas dépasser {{ limit }} caractères."
    )]
    private string $interpretat_legere;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: "L'interprétation modérée est obligatoire.")]
    #[Assert\Length(
        min: 10,
        max: 1000,
        minMessage: "L'interprétation modérée doit faire au moins {{ limit }} caractères.",
        maxMessage: "L'interprétation modérée ne peut pas dépasser {{ limit }} caractères."
    )]
    private string $interpretat_modere;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: "L'interprétation sévère est obligatoire.")]
    #[Assert\Length(
        min: 10,
        max: 1000,
        minMessage: "L'interprétation sévère doit faire au moins {{ limit }} caractères.",
        maxMessage: "L'interprétation sévère ne peut pas dépasser {{ limit }} caractères."
    )]
    private string $interpretat_severe;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: "Le seuil léger est obligatoire.")]
    #[Assert\PositiveOrZero(message: "Le seuil léger doit être positif ou zéro.")]
    #[Assert\Type(type: 'integer', message: "Le seuil léger doit être un nombre entier.")]
    private int $seuil_leger;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: "Le seuil modéré est obligatoire.")]
    #[Assert\PositiveOrZero(message: "Le seuil modéré doit être positif ou zéro.")]
    #[Assert\Type(type: 'integer', message: "Le seuil modéré doit être un nombre entier.")]
    private int $seuil_modere;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: "Le seuil sévère est obligatoire.")]
    #[Assert\PositiveOrZero(message: "Le seuil sévère doit être positif ou zéro.")]
    #[Assert\Type(type: 'integer', message: "Le seuil sévère doit être un nombre entier.")]
    private int $seuil_severe;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: "Le nombre de questions est obligatoire.")]
    #[Assert\Positive(message: "Le nombre de questions doit être positif.")]
    #[Assert\Range(
        min: 1,
        max: 100,
        notInRangeMessage: "Le nombre de questions doit être compris entre {{ min }} et {{ max }}."
    )]
    #[Assert\Type(type: 'integer', message: "Le nombre de questions doit être un nombre entier.")]
    private int $nbre_questions;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'questionnairesAdmin')]
    #[ORM\JoinColumn(name: 'admin_id', referencedColumnName: 'user_id', nullable: true)]
    private ?User $admin = null;

    #[ORM\OneToMany(mappedBy: 'questionnaire', targetEntity: Question::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Assert\Valid]
    private Collection $questions;

    #[ORM\OneToMany(mappedBy: 'questionnaire', targetEntity: ReponseQuestionnaire::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $reponses;

    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->questions = new ArrayCollection();
        $this->reponses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->questionnaire_id;
    }

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
        $validTypes = TypeQuestionnaire::getValues();
        if (!in_array($type, $validTypes, true)) {
            $type = $validTypes[0] ?? 'anxiete';
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
        $this->admin = $admin;
        return $this;
    }

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
            if ($question->getQuestionnaire() === $this) {
                $question->setQuestionnaire(null);
            }
        }

        return $this;
    }

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
            if ($reponse->getQuestionnaire() === $this) {
                $reponse->setQuestionnaire(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->nom, $this->code);
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updated_at = new \DateTime();
    }

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

    public function isComplete(): bool
    {
        return $this->questions->count() === $this->nbre_questions;
    }

    public function getCompletionPercentage(): float
    {
        $questionCount = $this->questions->count();
        if ($this->nbre_questions === 0) {
            return $questionCount > 0 ? 100 : 0;
        }

        return min(($questionCount / $this->nbre_questions) * 100, 100);
    }

    public function getTypeLabel(): string
    {
        return $this->getTypeEnum()->getLabel();
    }

    public function getNombreReponses(): int
    {
        return $this->reponses->count();
    }

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

    // ⚠️ SUPPRIMEZ cette méthode car elle est dupliquée avec le callback
    // public function validateSeuils(): bool
    // {
    //     return $this->seuil_leger < $this->seuil_modere &&
    //         $this->seuil_modere < $this->seuil_severe;
    // }

    public function generateCode(): string
    {
        $prefix = strtoupper(substr($this->type, 0, 3));
        $timestamp = time();
        $random = bin2hex(random_bytes(2));

        return sprintf('%s-%s-%s', $prefix, $timestamp, $random);
    }

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
            $totalScore += $reponse->getScoreTotal();
            $statistiques['scores'][] = $reponse->getScoreTotal();

            $niveau = $reponse->getNiveau();
            if (isset($statistiques['distribution_niveaux'][$niveau])) {
                $statistiques['distribution_niveaux'][$niveau]++;
            }

            if ($reponse->isABesoinPsy()) {
                $statistiques['besoin_psy']++;
            }
        }

        $statistiques['score_moyen'] = $totalScore / $this->reponses->count();

        return $statistiques;
    }

    /**
     * Validation personnalisée pour les seuils
     */
    #[Assert\Callback]
    public function validateSeuils(ExecutionContextInterface $context, $payload): void
    {
        // Vérification que les seuils sont dans l'ordre croissant
        if ($this->seuil_leger >= $this->seuil_modere) {
            $context->buildViolation('Le seuil léger doit être strictement inférieur au seuil modéré.')
                ->atPath('seuil_leger')
                ->addViolation();
        }

        if ($this->seuil_modere >= $this->seuil_severe) {
            $context->buildViolation('Le seuil modéré doit être strictement inférieur au seuil sévère.')
                ->atPath('seuil_modere')
                ->addViolation();
        }

        // Vérification que le seuil sévère n'est pas trop élevé
        $scoreMaxPossible = $this->nbre_questions * 4;
        if ($this->seuil_severe > $scoreMaxPossible) {
            $context->buildViolation(sprintf(
                'Le seuil sévère (%d) ne peut pas dépasser le score maximum possible (%d) pour %d questions.',
                $this->seuil_severe,
                $scoreMaxPossible,
                $this->nbre_questions
            ))
                ->atPath('seuil_severe')
                ->addViolation();
        }
    }

    /**
     * Validation de la cohérence entre le nombre de questions attendues et réelles
     */
    #[Assert\Callback]
    public function validateQuestionsCount(ExecutionContextInterface $context, $payload): void
    {
        $existingQuestionsCount = $this->questions->count();
        
        if ($this->nbre_questions < $existingQuestionsCount) {
            $context->buildViolation(sprintf(
                'Impossible de définir %d questions car %d questions existent déjà. ' .
                'Supprimez d\'abord les questions en trop ou augmentez le nombre.',
                $this->nbre_questions,
                $existingQuestionsCount
            ))
                ->atPath('nbre_questions')
                ->addViolation();
        }
    }

    /**
     * Validation globale du questionnaire
     */
    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, $payload): void
    {
        // Vérification que toutes les interprétations sont différentes
        $interpretations = [
            'Légère' => $this->interpretat_legere,
            'Modérée' => $this->interpretat_modere,
            'Sévère' => $this->interpretat_severe
        ];

        foreach ($interpretations as $niveau1 => $text1) {
            foreach ($interpretations as $niveau2 => $text2) {
                if ($niveau1 !== $niveau2 && $text1 === $text2) {
                    $context->buildViolation(sprintf(
                        'Les interprétations "%s" et "%s" ne peuvent pas être identiques.',
                        $niveau1,
                        $niveau2
                    ))
                        ->atPath('interpretat_' . strtolower($niveau1))
                        ->addViolation();
                }
            }
        }

        // Vérification que le questionnaire a au moins une question
        if ($this->questions->isEmpty() && $this->nbre_questions > 0) {
            $context->buildViolation('Vous devez ajouter au moins une question au questionnaire.')
                ->atPath('questions')
                ->addViolation();
        }
    }
}