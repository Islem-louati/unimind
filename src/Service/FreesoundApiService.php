<?php
// src/Service/FreesoundApiService.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class FreesoundApiService
{
    private $httpClient;
    private $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $apiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
    }

    /**
     * Recherche des sons sur Freesound.
     *
     * @param string $query Le terme à rechercher (ex: "rain")
     * @param int $maxResults Le nombre maximum de résultats (max 150) [citation:3]
     * @return array La liste des sons trouvés avec leurs previews.
     */
    public function searchSounds(string $query, int $maxResults = 5): array
    {
        $response = $this->httpClient->request('GET', 'https://freesound.org/apiv2/search/text/', [
            'query' => [
                'query' => $query,
                'token' => $this->apiKey,
                'fields' => 'id,name,previews,duration',
                'page_size' => $maxResults,
            ],
        ]);

        $data = $response->toArray();
        // La réponse contient une clé 'results' avec la liste des sons [citation:2][citation:3]
        return $data['results'] ?? [];
    }

    /**
     * Récupère les détails d'un son spécifique (alternative).
     */
    public function getSoundDetails(int $soundId): array
    {
        $response = $this->httpClient->request('GET', "https://freesound.org/apiv2/sounds/$soundId/", [
            'query' => [
                'token' => $this->apiKey,
                'fields' => 'id,name,previews,duration',
            ],
        ]);
        return $response->toArray();
    }
}