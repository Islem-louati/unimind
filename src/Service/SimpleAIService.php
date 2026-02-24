<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SimpleAIService
{
    private HttpClientInterface $httpClient;
    private ParameterBagInterface $parameterBag;

    public function __construct(HttpClientInterface $httpClient, ParameterBagInterface $parameterBag)
    {
        $this->httpClient = $httpClient;
        $this->parameterBag = $parameterBag;
    }

    public function analyzeObservations(string $observations): array
    {
        // Valider que les observations ont un sens avant analyse
        $validation = $this->validateInput($observations);
        
        if (!$validation['is_valid']) {
            return [
                'keywords' => [],
                'emotion' => 'Non analysable',
                'recommendation' => $validation['message'],
                'confidence' => 0,
                'full_response' => $validation['message'],
                'error' => true
            ];
        }
        
        // Utiliser l'IA locale experte
        return $this->simulateAnalysis($observations);
    }

    public function generateTreatmentAdvice(string $traitementType, string $patientProfile): string
    {
        // Valider les entrées avant génération de conseil
        $validation = $this->validateTreatmentInput($traitementType, $patientProfile);
        
        if (!$validation['is_valid']) {
            return $validation['message'];
        }
        
        // Utiliser l'IA locale experte
        return $this->simulateAdvice($traitementType, $patientProfile);
    }

    private function parseAnalysisResponse(string $content): array
    {
        // Parser la réponse de l'IA
        $keywords = [];
        $emotion = 'Neutre';
        $recommendation = 'Continuez comme prévu.';

        // Extraction simple des mots-clés
        if (preg_match('/mots?[ -]cl[ée]s?\s*[:\-]\s*([^.\n]+)/i', $content, $matches)) {
            $keywords = array_map('trim', explode(',', $matches[1]));
        }

        // Extraction de l'émotion
        if (preg_match('/[ée]motion\s*[:\-]\s*([^.\n]+)/i', $content, $matches)) {
            $emotion = trim($matches[1]);
        }

        // Extraction de la recommandation
        if (preg_match('/recommandation\s*[:\-]\s*([^.\n]+)/i', $content, $matches)) {
            $recommendation = trim($matches[1]);
        }

        return [
            'keywords' => $keywords,
            'emotion' => $emotion,
            'recommendation' => $recommendation,
            'full_response' => $content
        ];
    }

    private function simulateAnalysis(string $observations): array
    {
        // Système d'IA experte locale avancée
        $observations = strtolower($observations);
        
        // Base de connaissances thérapeutiques
        $knowledgeBase = $this->getTherapeuticKnowledgeBase();
        
        // Analyse sémantique avancée
        $analysis = $this->performSemanticAnalysis($observations, $knowledgeBase);
        
        return [
            'keywords' => $analysis['keywords'],
            'emotion' => $analysis['emotion'],
            'recommendation' => $analysis['recommendation'],
            'confidence' => $analysis['confidence'],
            'full_response' => $analysis['full_response']
        ];
    }

    private function simulateAdvice(string $traitementType, string $patientProfile): string
    {
        // Système d'IA experte locale avancée
        $knowledgeBase = $this->getTherapeuticKnowledgeBase();
        
        // Analyse intelligente du profil et du traitement
        $analysis = $this->analyzeTreatmentNeeds($traitementType, $patientProfile, $knowledgeBase);
        
        // Génération de conseil personnalisé
        return $this->generatePersonalizedAdvice($analysis, $knowledgeBase);
    }

    /**
     * Base de connaissances thérapeutiques avancée
     */
    private function getTherapeuticKnowledgeBase(): array
    {
        return [
            'emotions' => [
                'anxiete' => [
                    'keywords' => ['anxié', 'stress', 'angoisse', 'inquiet', 'tendu', 'nerveux'],
                    'symptomes' => ['palpitations', 'souffle court', 'tremblements', 'insomnie'],
                    'techniques' => ['respiration profonde', 'relaxation musculaire', 'méditation', 'visualisation']
                ],
                'tristesse' => [
                    'keywords' => ['triste', 'déprim', 'morose', 'abattu', 'sans énergie', 'fatigué'],
                    'symptomes' => ['perte d\'intérêt', 'pleurs', 'isolement', 'troubles du sommeil'],
                    'techniques' => ['thérapie par l\'art', 'journalisation', 'activation comportementale', 'exercice physique']
                ],
                'colere' => [
                    'keywords' => ['col', 'énerv', 'furieux', 'irrité', 'agressif', 'tension'],
                    'symptomes' => ['pensées noires', 'comportement impulsif', 'maux de tête'],
                    'techniques' => ['comptage jusqu\'à 10', 'exercices de relaxation', 'communication non violente', 'sport']
                ]
            ],
            'traitements' => [
                'cognitif' => [
                    'approaches' => ['restructuration cognitive', 'thérapie rationnelle', 'identification des distorsions'],
                    'exercices' => ['journal de pensées', 'tableau des croyances', 'exercices de débat']
                ],
                'comportemental' => [
                    'approaches' => ['thérapie comportementale', 'exposition', 'renforcement positif'],
                    'exercices' => ['hiérarchie des peurs', 'plan d\'action', 'auto-observation']
                ],
                'emotionnel' => [
                    'approaches' => ['régulation émotionnelle', 'intelligence émotionnelle', 'mindfulness'],
                    'exercices' => ['identification des émotions', 'techniques de coping', 'méditation']
                ]
            ]
        ];
    }

    /**
     * Analyse sémantique avancée des observations
     */
    private function performSemanticAnalysis(string $observations, array $knowledgeBase): array
    {
        $scoreEmotionnel = [];
        $keywordsDetectedes = [];
        
        // Analyse par scoring sémantique
        foreach ($knowledgeBase['emotions'] as $emotion => $data) {
            $score = 0;
            
            // Score basé sur les mots-clés
            foreach ($data['keywords'] as $keyword) {
                $occurrences = substr_count($observations, $keyword);
                $score += $occurrences * 2;
            }
            
            // Score basé sur les symptômes
            foreach ($data['symptomes'] as $symptome) {
                $occurrences = substr_count($observations, $symptome);
                $score += $occurrences * 3;
            }
            
            if ($score > 0) {
                $scoreEmotionnel[$emotion] = $score;
                $keywordsDetectedes = array_merge($keywordsDetectedes, $data['keywords']);
            }
        }
        
        // Déterminer l'émotion dominante
        $emotionDominante = !empty($scoreEmotionnel) ? array_keys($scoreEmotionnel, max($scoreEmotionnel))[0] : 'neutre';
        $confidence = !empty($scoreEmotionnel) ? min(max($scoreEmotionnel[$emotionDominante] / 10, 1), 0.1) : 0.5;
        
        // Générer la recommandation
        $recommendation = $this->generateContextualRecommendation($emotionDominante, $observations, $knowledgeBase);
        
        return [
            'keywords' => array_unique($keywordsDetectedes),
            'emotion' => $this->getEmotionLabel($emotionDominante),
            'recommendation' => $recommendation,
            'confidence' => round($confidence, 2),
            'scores' => $scoreEmotionnel
        ];
    }

    /**
     * Analyse des besoins de traitement
     */
    private function analyzeTreatmentNeeds(string $traitementType, string $patientProfile, array $knowledgeBase): array
    {
        $needs = [];
        
        // Analyse du type de traitement
        if (isset($knowledgeBase['traitements'][$traitementType])) {
            $needs['traitement'] = $knowledgeBase['traitements'][$traitementType];
        }
        
        // Déterminer la stratégie optimale
        $needs['strategie'] = $this->determineOptimalStrategy($traitementType, $patientProfile, $knowledgeBase);
        
        return $needs;
    }

    /**
     * Génération de conseil personnalisé
     */
    private function generatePersonalizedAdvice(array $analysis, array $knowledgeBase): string
    {
        $conseil = "";
        
        // Conseil basé sur la stratégie optimale
        if (isset($analysis['strategie'])) {
            $conseil .= "🎯 **Stratégie recommandée :** " . $analysis['strategie']['titre'] . "\n\n";
            $conseil .= "📋 **Plan d'action :**\n";
            
            foreach ($analysis['strategie']['actions'] as $i => $action) {
                $conseil .= ($i + 1) . ". " . $action . "\n";
            }
            
            $conseil .= "\n💡 **Conseils pratiques :**\n";
            foreach ($analysis['strategie']['conseils'] as $conseilPratique) {
                $conseil .= "• " . $conseilPratique . "\n";
            }
        }
        
        return $conseil;
    }

    /**
     * Détermination de la stratégie optimale
     */
    private function determineOptimalStrategy(string $traitementType, string $patientProfile, array $knowledgeBase): array
    {
        $strategies = [
            'cognitif_anxieux' => [
                'titre' => 'Thérapie cognitive avec gestion de l\'anxiété',
                'actions' => [
                    'Identifier et questionner les pensées catastrophiques',
                    'Pratiquer la restructuration cognitive quotidienne',
                    'Utiliser des techniques de relaxation avant les situations stressantes'
                ],
                'conseils' => [
                    'Tenir un journal des pensées anxieuses',
                    'Pratiquer 5 minutes de respiration profonde 3 fois par jour',
                    'Établir une liste de pensées alternatives réalistes'
                ]
            ],
            'comportemental_depressif' => [
                'titre' => 'Activation comportementale progressive',
                'actions' => [
                    'Commencer par de petites activités plaisantes (15 min/jour)',
                    'Augmenter progressivement les interactions sociales',
                    'Établir un planning d\'activités structuré'
                ],
                'conseils' => [
                    'Fixer 1 petit objectif réalisable par jour',
                    'Noter les accomplissements dans un journal',
                    'Utiliser un système de récompenses personnelles'
                ]
            ],
            'emotionnel_anxieux' => [
                'titre' => 'Régulation émotionnelle et pleine conscience',
                'actions' => [
                    'Apprendre à identifier et nommer les émotions',
                    'Pratiquer des exercices de grounding (ancrage)',
                    'Développer des stratégies de coping saines'
                ],
                'conseils' => [
                    'Pratiquer la méditation de 5 minutes chaque matin',
                    'Utiliser des applications de relaxation guidée',
                    'Créer un espace calme personnel'
                ]
            ]
        ];
        
        // Nettoyer les entrées
        $traitementType = strtolower($traitementType);
        $patientProfile = strtolower($patientProfile);
        
        // Stratégie par défaut
        $defaultStrategy = [
            'titre' => 'Approche thérapeutique équilibrée',
            'actions' => [
                'Maintenir une pratique régulière des exercices',
                'Surveiller les progrès et ajuster l\'approche',
                'Communiquer régulièrement avec le thérapeute'
            ],
            'conseils' => [
                'Être patient et constant dans la pratique',
                'Célébrer les petits succès',
                'Demander du soutien quand nécessaire'
            ]
        ];
        
        // Sélectionner la stratégie appropriée
        $key = $traitementType . '_' . $patientProfile;
        return $strategies[$key] ?? $defaultStrategy;
    }

    /**
     * Génération de recommandation contextuelle
     */
    private function generateContextualRecommendation(string $emotion, string $observations, array $knowledgeBase): string
    {
        $recommendations = [
            'anxiete' => [
                'Pratiquer la cohérence cardiaque : 5 secondes inspiration, 5 secondes expiration',
                'Utiliser la technique de relaxation musculaire progressive',
                'Identifier les pensées irrationnelles et les remplacer par des pensées réalistes'
            ],
            'tristesse' => [
                'Planifier une activité plaisante chaque jour, même courte',
                'Pratiquer l\'exercice physique léger (15-20 minutes)',
                'Utiliser la journalisation pour exprimer les émotions'
            ],
            'colere' => [
                'Apprendre à reconnaître les signes avant-coureurs de la colère',
                'Pratiquer des techniques de retrait temporaire de la situation',
                'Utiliser des affirmations positives pour calmer le système nerveux'
            ]
        ];
        
        return $recommendations[$emotion] ?? 'Continuer le suivi régulier et noter les observations détaillées.';
    }

    /**
     * Obtenir le label d'émotion en français
     */
    private function getEmotionLabel(string $emotion): string
    {
        $labels = [
            'anxiete' => 'Anxieux',
            'tristesse' => 'Triste',
            'colere' => 'En colère',
            'neutre' => 'Stable'
        ];
        
        return $labels[$emotion] ?? 'Neutre';
    }

    /**
     * Valider que les observations ont un sens thérapeutique
     */
    private function validateInput(string $observations): array
    {
        $observations = trim($observations);
        
        // Vérifications de base
        if (empty($observations)) {
            return [
                'is_valid' => false,
                'message' => '❌ Veuillez décrire vos observations et ressentis pour que je puisse vous aider.'
            ];
        }
        
        if (strlen($observations) < 10) {
            return [
                'is_valid' => false,
                'message' => '❌ Votre description est trop courte. Veuillez donner plus de détails sur vos ressentis.'
            ];
        }
        
        // Vérifier si c'est du texte sans sens
        $invalidPatterns = [
            '/^abcde$/i',
            '/^test$/i',
            '/^hello$/i',
            '/^bonjour$/i',
            '/^[0-9]+$/',
            '/^[a-zA-Z]{1,3}$/',
            '/^.{1,5}$/'
        ];
        
        foreach ($invalidPatterns as $pattern) {
            if (preg_match($pattern, $observations)) {
                return [
                    'is_valid' => false,
                    'message' => '❌ Ce texte ne contient pas d\'informations thérapeutiques exploitables. Veuillez décrire vos émotions, ressentis ou observations.'
                ];
            }
        }
        
        // Vérifier s'il y a des mots pertinents pour l'analyse
        $relevantWords = [
            'sens', 'ressent', 'émotion', 'anx', 'stress', 'triste', 'col', 'peur', 'fatigu', 'bien', 'mal',
            'mieux', 'pire', 'calme', 'tendu', 'nerveux', 'dormi', 'rêve', 'cauchemar', 'appétit',
            'énergie', 'motivation', 'travail', 'école', 'famille', 'amis', 'relation', 'confiance'
        ];
        
        $hasRelevantWords = false;
        foreach ($relevantWords as $word) {
            if (strpos(strtolower($observations), $word) !== false) {
                $hasRelevantWords = true;
                break;
            }
        }
        
        if (!$hasRelevantWords) {
            return [
                'is_valid' => false,
                'message' => '❌ Je ne trouve pas de mots-clés pertinents dans votre description. Veuillez parler de vos émotions, ressentis ou état mental.'
            ];
        }
        
        return ['is_valid' => true];
    }

    /**
     * Valider les entrées pour le conseil de traitement
     */
    private function validateTreatmentInput(string $traitementType, string $patientProfile): array
    {
        $traitementType = trim($traitementType);
        $patientProfile = trim($patientProfile);
        
        // Types de traitement valides
        $validTreatments = ['cognitif', 'comportemental', 'emotionnel', 'relaxation'];
        
        if (!in_array(strtolower($traitementType), $validTreatments)) {
            return [
                'is_valid' => false,
                'message' => '❌ Type de traitement non reconnu. Types valides : cognitif, comportemental, émotionnel, relaxation.'
            ];
        }
        
        // Profils patients valides
        $validProfiles = ['anxieux', 'dépressif', 'impulsif', 'stable', 'motivé'];
        
        if (!in_array(strtolower($patientProfile), $validProfiles)) {
            return [
                'is_valid' => false,
                'message' => '❌ Profil patient non reconnu. Profils valides : anxieux, dépressif, impulsif, stable, motivé.'
            ];
        }
        
        return ['is_valid' => true];
    }
}
