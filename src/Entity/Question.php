<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'question')]
#[ORM\HasLifecycleCallbacks]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $question_id = null;

    #[ORM\Column(type: 'text')]
    private string $texte;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: 'json')]
    private array $options_quest = [];

    #[ORM\Column(type: 'json')]
    private array $score_options = [];

    #[ORM\ManyToOne(targetEntity: Questionnaire::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(name: 'questionnaire_id', referencedColumnName: 'questionnaire_id', nullable: false)]
    private ?Questionnaire $questionnaire = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    // Getters et setters
    public function getQuestionId(): ?int
    {
        return $this->question_id;
    }

    public function getTexte(): string
    {
        return $this->texte;
    }

    public function setTexte(string $texte): self
    {
        $this->texte = $texte;
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

    public function getOptionsQuest(): array
    {
        return $this->options_quest;
    }

    public function setOptionsQuest(array $options_quest): self
    {
        $this->options_quest = $options_quest;
        return $this;
    }

    public function getScoreOptions(): array
    {
        return $this->score_options;
    }

    public function setScoreOptions(array $score_options): self
    {
        $this->score_options = $score_options;
        return $this;
    }

    public function getQuestionnaire(): ?Questionnaire
    {
        return $this->questionnaire;
    }

    public function setQuestionnaire(?Questionnaire $questionnaire): self
    {
        $this->questionnaire = $questionnaire;
        return $this;
    }

    // Méthodes utilitaires
    public function __toString(): string
    {
        return sprintf('Question #%d', $this->question_id);
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updated_at = new \DateTime();
    }

    // Méthodes pour gérer les options
    public function addOption(string $option, int $score): self
    {
        $this->options_quest[] = $option;
        $this->score_options[] = $score;
        
        return $this;
    }

    public function removeOption(int $index): self
    {
        if (isset($this->options_quest[$index])) {
            unset($this->options_quest[$index]);
            $this->options_quest = array_values($this->options_quest);
        }
        
        if (isset($this->score_options[$index])) {
            unset($this->score_options[$index]);
            $this->score_options = array_values($this->score_options);
        }
        
        return $this;
    }

    public function getOptionCount(): int
    {
        return count($this->options_quest);
    }

    public function getOptionsWithScores(): array
    {
        $optionsWithScores = [];
        for ($i = 0; $i < count($this->options_quest); $i++) {
            $optionsWithScores[] = [
                'option' => $this->options_quest[$i] ?? '',
                'score' => $this->score_options[$i] ?? 0
            ];
        }
        
        return $optionsWithScores;
    }

    public function getScoreForOption(string $option): ?int
    {
        $index = array_search($option, $this->options_quest, true);
        
        if ($index !== false && isset($this->score_options[$index])) {
            return $this->score_options[$index];
        }
        
        return null;
    }

    public function getScoreForOptionIndex(int $index): ?int
    {
        return $this->score_options[$index] ?? null;
    }

    public function getOptionByIndex(int $index): ?string
    {
        return $this->options_quest[$index] ?? null;
    }

    // Méthode pour valider les options et scores
    public function validateOptionsAndScores(): bool
    {
        // Vérifier que les deux tableaux ont la même taille
        if (count($this->options_quest) !== count($this->score_options)) {
            return false;
        }
        
        // Vérifier qu'il y a au moins 2 options
        if (count($this->options_quest) < 2) {
            return false;
        }
        
        // Vérifier que toutes les options sont des strings non vides
        foreach ($this->options_quest as $option) {
            if (!is_string($option) || trim($option) === '') {
                return false;
            }
        }
        
        // Vérifier que tous les scores sont des entiers
        foreach ($this->score_options as $score) {
            if (!is_int($score)) {
                return false;
            }
        }
        
        return true;
    }

    // Méthode pour obtenir les options formatées pour un formulaire
    public function getFormChoices(): array
    {
        $choices = [];
        foreach ($this->options_quest as $index => $option) {
            $choices[$option] = $index; // Stocke l'index comme valeur
        }
        
        return $choices;
    }

    // Méthode pour obtenir le score maximum possible
    public function getMaxScore(): int
    {
        if (empty($this->score_options)) {
            return 0;
        }
        
        return max($this->score_options);
    }

    // Méthode pour obtenir le score minimum possible
    public function getMinScore(): int
    {
        if (empty($this->score_options)) {
            return 0;
        }
        
        return min($this->score_options);
    }

    // Méthode pour calculer le score pour une réponse donnée
    public function calculateScoreForResponse($response): ?int
    {
        if (is_int($response) && isset($this->score_options[$response])) {
            // Si la réponse est un index
            return $this->score_options[$response];
        } elseif (is_string($response)) {
            // Si la réponse est une option textuelle
            return $this->getScoreForOption($response);
        }
        
        return null;
    }

    // Méthode pour obtenir un résumé de la question
    public function getResume(): string
    {
        $optionsText = '';
        for ($i = 0; $i < count($this->options_quest); $i++) {
            $optionsText .= sprintf("\n  %d. %s (score: %d)", 
                $i + 1, 
                $this->options_quest[$i] ?? '', 
                $this->score_options[$i] ?? 0
            );
        }
        
        return sprintf(
            "Question #%d\n" .
            "Texte: %s\n" .
            "Options:%s\n" .
            "Questionnaire: %s",
            $this->question_id,
            substr($this->texte, 0, 100) . (strlen($this->texte) > 100 ? '...' : ''),
            $optionsText,
            $this->questionnaire ? $this->questionnaire->getNom() : 'Non assigné'
        );
    }

    // Méthode pour créer une question à partir d'un tableau
    public static function createFromArray(array $data, Questionnaire $questionnaire): self
    {
        $question = new self();
        $question->setTexte($data['texte'] ?? '');
        $question->setOptionsQuest($data['options'] ?? []);
        $question->setScoreOptions($data['scores'] ?? []);
        $question->setQuestionnaire($questionnaire);
        
        return $question;
    }
}