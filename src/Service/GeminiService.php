<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class GeminiService
{
    private HttpClientInterface $httpClient;
    private string $apiKey;
    private RequestStack $requestStack;

    // Instruction système pour définir le comportement du chatbot
    private const SYSTEM_INSTRUCTION = "
Tu es un assistant de soutien en santé mentale amical et empathique. 
Ton objectif principal est d'offrir un espace d'écoute et de soutien, et non de poser des diagnostics ou de donner des conseils médicaux.

Directives :
1. Écoute active : Reformule les propos de l'utilisateur et reconnais ses émotions.
2. Empathie : Sois compréhensif, patient et sans jugement.
3. Limites claires : Rappelle que tu n'es pas un professionnel de santé. Utilise une phrase comme : 'Je suis un assistant virtuel et non un médecin. Pour un accompagnement professionnel, consulte un spécialiste.'
4. Soutien concret : Propose des suggestions douces (respiration, écriture, exercice) ou oriente vers des ressources adaptées.
5. Gestion de crise : Si l'utilisateur exprime des pensées suicidaires ou d'automutilation, réponds avec empathie et fournis les coordonnées des lignes d'écoute (3114, 3115 pour la France).
";

    public function __construct(HttpClientInterface $httpClient, string $geminiApiKey, RequestStack $requestStack)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $geminiApiKey;
        $this->requestStack = $requestStack;
    }

    public function sendMessage(string $userMessage): string
    {
        $session = $this->requestStack->getSession();
        $history = $session->get('chat_history', []);

        // Prépare le contenu de la requête
        // L'API Gemini attend une liste de messages avec rôle 'user' ou 'model'
        $contents = [];

        // Ajoute l'instruction système en tant que premier message (rôle 'user' pour le contexte système, mais il est préférable d'utiliser le paramètre system_instruction dans la nouvelle API)
        // Pour Gemini 1.5, on peut passer system_instruction directement dans le corps de la requête.
        // Nous allons donc construire un tableau 'system_instruction' à part.

        // On construit l'historique au format attendu par Gemini
        foreach ($history as $message) {
            $contents[] = [
                'role' => $message['role'], // 'user' ou 'model'
                'parts' => [['text' => $message['text']]]
            ];
        }

        // Ajoute le nouveau message de l'utilisateur
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]]
        ];

        // Appel à l'API Gemini
        $response = $this->httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $this->apiKey, [
    'json' => [
        'system_instruction' => [
            'parts' => [['text' => self::SYSTEM_INSTRUCTION]]
        ],
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 800,
        ]
    ]
]);

        $data = $response->toArray();

        // Extrait la réponse du modèle
        $botReply = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Désolé, je n’ai pas pu générer de réponse.';

        // Met à jour l'historique en session
        $history[] = ['role' => 'user', 'text' => $userMessage];
        $history[] = ['role' => 'model', 'text' => $botReply];
        $session = $this->requestStack->getSession();
        $session->set('chat_history', $history);

        return $botReply;
    }

    /**
     * Efface l'historique de la conversation.
     */
    public function clearHistory(): void
    {
        $session = $this->requestStack->getSession();
        $session->remove('chat_history');
    }
}