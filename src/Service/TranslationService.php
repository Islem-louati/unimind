<?php

namespace App\Service;

use Stichoza\GoogleTranslate\GoogleTranslate;
use Psr\Log\LoggerInterface;

class TranslationService
{
    private LoggerInterface $logger;
    private array $supportedLanguages;
    private string $defaultLanguage;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->supportedLanguages = [
            'fr' => 'Français',
            'en' => 'English',
            'es' => 'Español',
            'de' => 'Deutsch',
            'it' => 'Italiano',
            'pt' => 'Português',
            'ar' => 'العربية',
            'zh' => '中文',
            'ja' => '日本語',
            'ru' => 'Русский'
        ];
        $this->defaultLanguage = 'fr';
    }

    /**
     * Traduit un texte dans la langue cible
     */
    public function translate(string $text, string $targetLanguage, string $sourceLanguage = 'auto'): array
    {
        try {
            // Validation des entrées
            if (empty(trim($text))) {
                return [
                    'success' => false,
                    'error' => 'Le texte à traduire ne peut pas être vide',
                    'translated_text' => $text
                ];
            }

            if (!array_key_exists($targetLanguage, $this->supportedLanguages)) {
                return [
                    'success' => false,
                    'error' => 'Langue cible non supportée',
                    'translated_text' => $text,
                    'available_languages' => array_keys($this->supportedLanguages)
                ];
            }

            // Configuration du traducteur
            $translate = new GoogleTranslate();
            $translate->setTarget($targetLanguage);
            
            if ($sourceLanguage !== 'auto') {
                $translate->setSource($sourceLanguage);
            }

            // Traduction
            $translatedText = $translate->translate($text);

            // Détection de la langue source si auto
            $detectedLanguage = $sourceLanguage === 'auto' ? $translate->getLastDetectedSource() : $sourceLanguage;

            $this->logger->info('Traduction réussie', [
                'original_text' => substr($text, 0, 100),
                'translated_text' => substr($translatedText, 0, 100),
                'source_language' => $detectedLanguage,
                'target_language' => $targetLanguage
            ]);

            return [
                'success' => true,
                'translated_text' => $translatedText,
                'source_language' => $detectedLanguage,
                'target_language' => $targetLanguage,
                'original_text' => $text
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erreur de traduction', [
                'error' => $e->getMessage(),
                'text' => substr($text, 0, 100),
                'target_language' => $targetLanguage
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de la traduction: ' . $e->getMessage(),
                'translated_text' => $text
            ];
        }
    }

    /**
     * Traduit les champs d'un traitement
     */
    public function translateTraitement(array $traitementData, string $targetLanguage): array
    {
        $translatedData = $traitementData;
        $fieldsToTranslate = ['titre', 'description', 'objectifTherapeutique', 'type', 'dosage'];
        $hasError = false;

        foreach ($fieldsToTranslate as $field) {
            if (isset($traitementData[$field]) && !empty($traitementData[$field])) {
                $result = $this->translate($traitementData[$field], $targetLanguage);
                
                if ($result['success']) {
                    $translatedData[$field . '_translated'] = $result['translated_text'];
                    $translatedData[$field . '_original'] = $traitementData[$field];
                } else {
                    $translatedData[$field . '_translated'] = $traitementData[$field];
                    $translatedData[$field . '_error'] = $result['error'];
                    $hasError = true;
                }
            }
        }

        $translatedData['translation_language'] = $targetLanguage;
        $translatedData['translation_success'] = true;
        $translatedData['success'] = !$hasError;

        return $translatedData;
    }

    /**
     * Traduit les observations d'un suivi
     */
    public function translateSuiviObservations(array $suiviData, string $targetLanguage): array
    {
        $translatedData = $suiviData;
        $fieldsToTranslate = ['observations', 'observationsPsy'];

        foreach ($fieldsToTranslate as $field) {
            if (isset($suiviData[$field]) && !empty($suiviData[$field])) {
                $result = $this->translate($suiviData[$field], $targetLanguage);
                
                if ($result['success']) {
                    $translatedData[$field . '_translated'] = $result['translated_text'];
                    $translatedData[$field . '_original'] = $suiviData[$field];
                } else {
                    $translatedData[$field . '_translated'] = $suiviData[$field];
                    $translatedData[$field . '_error'] = $result['error'];
                }
            }
        }

        $translatedData['translation_language'] = $targetLanguage;
        $translatedData['translation_success'] = true;

        return $translatedData;
    }

    /**
     * Retourne la liste des langues supportées
     */
    public function getSupportedLanguages(): array
    {
        return $this->supportedLanguages;
    }

    /**
     * Vérifie si une langue est supportée
     */
    public function isLanguageSupported(string $languageCode): bool
    {
        return array_key_exists($languageCode, $this->supportedLanguages);
    }

    /**
     * Détecte automatiquement la langue d'un texte
     */
    public function detectLanguage(string $text): array
    {
        try {
            $translate = new GoogleTranslate();
            $detectedLanguage = $translate->detectLanguage($text);

            return [
                'success' => true,
                'language_code' => $detectedLanguage,
                'language_name' => $this->supportedLanguages[$detectedLanguage] ?? 'Langue inconnue'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors de la détection de langue: ' . $e->getMessage(),
                'language_code' => 'unknown'
            ];
        }
    }

    /**
     * Traduction en masse pour plusieurs textes
     */
    public function translateMultiple(array $texts, string $targetLanguage, string $sourceLanguage = 'auto'): array
    {
        $results = [];
        
        foreach ($texts as $key => $text) {
            if (!empty($text)) {
                $results[$key] = $this->translate($text, $targetLanguage, $sourceLanguage);
            } else {
                $results[$key] = [
                    'success' => false,
                    'error' => 'Texte vide',
                    'translated_text' => $text
                ];
            }
        }

        return $results;
    }
}
