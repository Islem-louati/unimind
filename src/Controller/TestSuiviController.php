<?php

namespace App\Controller;

use App\Entity\SuiviTraitement;
use App\Entity\Traitement;
use App\Entity\User;
use App\Entity\Enum\Ressenti;
use App\Entity\Enum\SaisiPar;
use App\Form\SuiviTraitementType;
use App\Repository\SuiviTraitementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Security;

#[Route('/test-suivi')]
class TestSuiviController extends AbstractController
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[Route('/', name: 'app_test_suivi_index', methods: ['GET'])]
    public function index(): Response
    {
        // Créer des données de test fictives
        $suivis = $this->createTestSuivis();

        return $this->render('suivi_traitement/test_index.html.twig', [
            'suivis' => $suivis,
            'user_role' => 'test'
        ]);
    }

    #[Route('/mes-suivis', name: 'app_test_suivi_mes_suivis', methods: ['GET'])]
    public function mesSuivis(): Response
    {
        // Créer des données de test fictives pour un étudiant
        $suivis = $this->createTestSuivis();

        return $this->render('suivi_traitement/test_mes_suivis.html.twig', [
            'suivis' => $suivis,
            'user_role' => 'etudiant'
        ]);
    }

    #[Route('/a-valider', name: 'app_test_suivi_a_valider', methods: ['GET'])]
    public function aValider(): Response
    {
        // Créer des données de test fictives pour un psychologue
        $suivis = $this->createTestSuivis();

        return $this->render('suivi_traitement/test_a_valider.html.twig', [
            'suivis' => $suivis,
            'user_role' => 'psychologue'
        ]);
    }

    #[Route('/{id}', name: 'app_test_suivi_show', methods: ['GET'])]
    public function show(string $id): Response
    {
        // Convertir l'ID en entier
        $idInt = (int) $id;
        
        // Créer un suivi de test fictif
        $suivi = $this->createTestSuivi($idInt);

        return $this->render('suivi_traitement/test_show.html.twig', [
            'suivi' => $suivi,
            'user_role' => 'test'
        ]);
    }

    #[Route('/new', name: 'app_test_suivi_new', methods: ['GET', 'POST'])]
    public function new(): Response
    {
        // Créer un traitement de test fictif
        $traitement = [
            'id' => 1,
            'titre' => 'Thérapie Comportementale',
            'categorie' => 'Comportemental'
        ];

        return $this->render('suivi_traitement/test_new.html.twig', [
            'traitement' => $traitement,
            'user_role' => 'etudiant'
        ]);
    }

    private function createTestSuivis(): array
    {
        $suivis = [];
        
        // Suivi 1 - Effectué et validé
        $heureEffective1 = new \DateTime('-2 days 14:30');
        $suivi1 = [
            'id' => 1,
            'dateSuivi' => new \DateTime('-2 days'),
            'dateSaisie' => new \DateTime('-2 days'),
            'observations' => 'J\'ai bien suivi les exercices aujourd\'hui. Je me sens mieux.',
            'observationsPsy' => 'Excellent travail. Le patient montre une bonne progression.',
            'evaluation' => 8,
            'ressenti' => Ressenti::BIEN->value,
            'saisiPar' => SaisiPar::ETUDIANT->value,
            'effectue' => true,
            'valide' => true,
            'heureEffective' => $heureEffective1->format('H:i'),
            'traitement' => [
                'id' => 1,
                'titre' => 'Thérapie Comportementale',
                'categorie' => 'Comportemental',
                'categorieLabel' => 'Thérapie Comportementale',
                'etudiant' => [
                    'nom' => 'Étudiant',
                    'prenom' => 'Test',
                    'fullName' => 'Test Étudiant',
                    'email' => 'etudiant@test.com'
                ],
                'psychologue' => [
                    'nom' => 'Psychologue',
                    'prenom' => 'Test',
                    'fullName' => 'Test Psychologue',
                    'email' => 'psy@test.com'
                ]
            ]
        ];
        $suivis[] = $suivi1;

        // Suivi 2 - Effectué mais en attente de validation
        $heureEffective2 = new \DateTime('-1 day 10:15');
        $suivi2 = [
            'id' => 2,
            'dateSuivi' => new \DateTime('-1 day'),
            'dateSaisie' => new \DateTime('-1 day'),
            'observations' => 'J\'ai trouvé ça un peu difficile aujourd\'hui, mais j\'ai fait de mon mieux.',
            'observationsPsy' => '',
            'evaluation' => 6,
            'ressenti' => Ressenti::DIFFICILE->value,
            'saisiPar' => SaisiPar::ETUDIANT->value,
            'effectue' => true,
            'valide' => false,
            'heureEffective' => $heureEffective2->format('H:i'),
            'traitement' => [
                'id' => 1,
                'titre' => 'Thérapie Comportementale',
                'categorie' => 'Comportemental',
                'categorieLabel' => 'Thérapie Comportementale',
                'etudiant' => [
                    'nom' => 'Étudiant',
                    'prenom' => 'Test',
                    'fullName' => 'Test Étudiant',
                    'email' => 'etudiant@test.com'
                ],
                'psychologue' => [
                    'nom' => 'Psychologue',
                    'prenom' => 'Test',
                    'fullName' => 'Test Psychologue',
                    'email' => 'psy@test.com'
                ]
            ]
        ];
        $suivis[] = $suivi2;

        // Suivi 3 - À faire aujourd'hui
        $suivi3 = [
            'id' => 3,
            'dateSuivi' => new \DateTime(),
            'dateSaisie' => new \DateTime(),
            'observations' => '',
            'observationsPsy' => '',
            'evaluation' => 0,
            'ressenti' => Ressenti::NEUTRE->value,
            'saisiPar' => SaisiPar::SYSTEME->value,
            'effectue' => false,
            'valide' => false,
            'heureEffective' => null,
            'traitement' => [
                'id' => 2,
                'titre' => 'Thérapie Cognitive',
                'categorie' => 'Cognitif',
                'categorieLabel' => 'Thérapie Cognitive',
                'etudiant' => [
                    'nom' => 'Étudiant',
                    'prenom' => 'Test',
                    'fullName' => 'Test Étudiant',
                    'email' => 'etudiant@test.com'
                ],
                'psychologue' => [
                    'nom' => 'Psychologue',
                    'prenom' => 'Test',
                    'fullName' => 'Test Psychologue',
                    'email' => 'psy@test.com'
                ]
            ]
        ];
        $suivis[] = $suivi3;

        // Suivi 4 - En retard
        $suivi4 = [
            'id' => 4,
            'dateSuivi' => new \DateTime('-3 days'),
            'dateSaisie' => new \DateTime('-3 days'),
            'observations' => 'J\'ai oublié de faire le suivi...',
            'observationsPsy' => '',
            'evaluation' => 0,
            'ressenti' => Ressenti::DIFFICILE->value,
            'saisiPar' => SaisiPar::ETUDIANT->value,
            'effectue' => false,
            'valide' => false,
            'heureEffective' => null,
            'traitement' => [
                'id' => 2,
                'titre' => 'Thérapie Cognitive',
                'categorie' => 'Cognitif',
                'categorieLabel' => 'Thérapie Cognitive',
                'etudiant' => [
                    'nom' => 'Étudiant',
                    'prenom' => 'Test',
                    'fullName' => 'Test Étudiant',
                    'email' => 'etudiant@test.com'
                ],
                'psychologue' => [
                    'nom' => 'Psychologue',
                    'prenom' => 'Test',
                    'fullName' => 'Test Psychologue',
                    'email' => 'psy@test.com'
                ]
            ]
        ];
        $suivis[] = $suivi4;

        return $suivis;
    }

    private function createTestSuivi(int $id): array
    {
        $suivis = $this->createTestSuivis();
        
        foreach ($suivis as $suivi) {
            if ($suivi['id'] === $id) {
                return $suivi;
            }
        }
        
        // Retourner le premier si non trouvé
        return $suivis[0];
    }
}
