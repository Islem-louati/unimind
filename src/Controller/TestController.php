<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    #[Route('/test', name: 'app_test')]
    public function index(): Response
    {
        return $this->render('test/index.html.twig', [
            'message' => 'Module de gestion des traitements - Test sans authentification'
        ]);
    }

    #[Route('/test/traitements', name: 'app_test_traitements')]
    public function traitements(): Response
    {
        // Créer des traitements de test sous forme de tableaux simples
        $traitements = [
            [
                'id' => 1,
                'titre' => 'Traitement Test 1',
                'description' => 'Description du premier traitement de test',
                'type' => 'Thérapie comportementale',
                'categorie' => 'COGNITIF',
                'categorieLabel' => 'Cognitif',
                'duree_jours' => 30,
                'date_debut' => new \DateTime('2024-01-01'),
                'objectif_therapeutique' => 'Objectif thérapeutique de test',
                'statut' => 'EN_COURS',
                'statutLabel' => 'En cours',
                'priorite' => 'MOYENNE',
                'prioriteLabel' => 'Moyenne',
                'created_at' => new \DateTime('2024-01-01'),
                'pourcentageCompletion' => 65.5,
                'psychologue' => ['nom' => 'Test', 'prenom' => 'Psychologue'],
                'etudiant' => ['nom' => 'Test', 'prenom' => 'Étudiant'],
                'nb_suivis' => 5,
                'suivis_effectues' => 3,
                'suivis_en_attente' => 2
            ],
            [
                'id' => 2,
                'titre' => 'Traitement Test 2',
                'description' => 'Description du deuxième traitement de test',
                'type' => 'Thérapie cognitive',
                'categorie' => 'EMOTIONNEL',
                'categorieLabel' => 'Émotionnel',
                'duree_jours' => 60,
                'date_debut' => new \DateTime('2024-01-15'),
                'objectif_therapeutique' => 'Objectif thérapeutique avancé',
                'statut' => 'EN_COURS',
                'statutLabel' => 'En cours',
                'priorite' => 'HAUTE',
                'prioriteLabel' => 'Haute',
                'created_at' => new \DateTime('2024-01-15'),
                'pourcentageCompletion' => 45.2,
                'psychologue' => ['nom' => 'Test', 'prenom' => 'Psychologue'],
                'etudiant' => ['nom' => 'Test', 'prenom' => 'Étudiant'],
                'nb_suivis' => 8,
                'suivis_effectues' => 4,
                'suivis_en_attente' => 4
            ]
        ];

        return $this->render('traitement/index.html.twig', [
            'traitements' => $traitements,
            'user_role' => 'admin',
            'message' => 'Test du module de traitements avec données fictives',
            'can_edit' => true
        ]);
    }

    #[Route('/test/search', name: 'app_test_search')]
    public function search(): Response
    {
        return $this->render('traitement/search.html.twig', [
            'user_role' => 'admin',
            'message' => 'Test de la recherche'
        ]);
    }
}
