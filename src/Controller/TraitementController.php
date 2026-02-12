<?php

namespace App\Controller;

use App\Entity\Traitement;
use App\Entity\User;
use App\Form\TraitementType;
use App\Repository\TraitementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Security;

#[Route('/traitement')]
class TraitementController extends AbstractController
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[Route('/search', name: 'app_traitement_search', methods: ['GET'])]
    public function search(): Response
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('traitement/search.html.twig', [
            'user_role' => $this->getMainRole($user)
        ]);
    }

    #[Route('/', name: 'app_traitement_index', methods: ['GET'])]
    public function index(TraitementRepository $traitementRepository): Response
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            // Mode test : créer des données fictives
            $traitements = $this->createTestTraitements();
            $user_role = 'test';
        } else {
            // Mode normal : utiliser les données réelles
            $traitements = [];
            
            if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_RESPONSABLE_ETUDIANT')) {
                // Admin et responsable peuvent voir tous les traitements
                $traitements = $traitementRepository->createQueryBuilder('t')
                    ->leftJoin('t.etudiant', 'e')
                    ->leftJoin('t.psychologue', 'p')
                    ->addSelect('e', 'p')
                    ->getQuery()
                    ->getResult();
            } elseif ($this->isGranted('ROLE_PSYCHOLOGUE')) {
                // Psychologue voit seulement ses traitements
                $traitements = $traitementRepository->createQueryBuilder('t')
                    ->leftJoin('t.etudiant', 'e')
                    ->leftJoin('t.psychologue', 'p')
                    ->addSelect('e', 'p')
                    ->where('t.psychologue = :user')
                    ->setParameter('user', $user)
                    ->getQuery()
                    ->getResult();
                // Debug détaillé
                dump('Psychologue traitements DÉTAILLÉ', [
                    'user_id' => $user->getId(),
                    'user_email' => $user->getEmail(),
                    'traitements_count' => count($traitements),
                    'first_traitement' => $traitements[0] ? [
                        'id' => $traitements[0]->getId(),
                        'titre' => $traitements[0]->getTitre(),
                        'etudiant_loaded' => $traitements[0]->getEtudiant() !== null,
                        'etudiant_class' => $traitements[0]->getEtudiant() ? get_class($traitements[0]->getEtudiant()) : 'null',
                        'psychologue_loaded' => $traitements[0]->getPsychologue() !== null,
                        'psychologue_class' => $traitements[0]->getPsychologue() ? get_class($traitements[0]->getPsychologue()) : 'null',
                    ] : 'none'
                ]);
            } elseif ($this->isGranted('ROLE_ETUDIANT')) {
                // Étudiant voit seulement ses traitements
                $traitements = $traitementRepository->createQueryBuilder('t')
                    ->leftJoin('t.etudiant', 'e')
                    ->leftJoin('t.psychologue', 'p')
                    ->addSelect('e', 'p')
                    ->where('t.etudiant = :user')
                    ->setParameter('user', $user)
                    ->getQuery()
                    ->getResult();
            }
            $user_role = $this->getMainRole($user);
        }

        return $this->render('traitement/index.html.twig', [
            'traitements' => $traitements,
            'user_role' => $user_role
        ]);
    }

    #[Route('/new', name: 'app_traitement_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $traitement = new Traitement();
        $form = $this->createForm(TraitementType::class, $traitement, [
            'show_etudiant_field' => true
        ]);
        
        // Gérer manuellement les données POST si le formulaire est vide
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            if (empty($data)) {
                // Essayer de récupérer les données brutes
                $rawData = $request->getContent();
                if ($rawData) {
                    $data = json_decode($rawData, true);
                }
            }
            
            // Si on a des données, les soumettre manuellement
            if (!empty($data)) {
                // Forcer les valeurs par défaut pour les champs vides
                if (isset($data['traitement'])) {
                    $formData = $data['traitement'];
                    $formData['titre'] = $formData['titre'] ?: 'Nouveau traitement';
                    $formData['type'] = $formData['type'] ?: 'Thérapie';
                    $formData['description'] = $formData['description'] ?: 'Description du traitement';
                    $formData['duree_jours'] = $formData['duree_jours'] ?: 30;
                    $formData['objectif_therapeutique'] = $formData['objectif_therapeutique'] ?: 'Objectif thérapeutique';
                    $form->submit($formData);
                } else {
                    $form->submit($data);
                }
            } else {
                $form->handleRequest($request);
            }
        } else {
            $form->handleRequest($request);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Définir le psychologue actuel comme créateur du traitement
            $traitement->setPsychologue($this->security->getUser());
            
            $entityManager->persist($traitement);
            $entityManager->flush();
            
            $this->addFlash('success', 'Le traitement a été créé avec succès.');

            return $this->redirectToRoute('app_traitement_show', ['id' => $traitement->getId()]);
        }

        // Afficher les erreurs de validation si le formulaire est invalide
        if ($form->isSubmitted() && !$form->isValid()) {
            $errors = $form->getErrors(true);
            $this->addFlash('error', 'Le formulaire contient des erreurs. Veuillez corriger les champs indiqués.');
            
            // Debug pour voir les erreurs dans la console
            dump('Erreurs de validation:', $errors);
            
            // Rediriger pour éviter le blocage
            return $this->render('traitement/new.html.twig', [
                'traitement' => $traitement,
                'form' => $form->createView(),
            ]);
        }

        return $this->render('traitement/new.html.twig', [
            'traitement' => $traitement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_traitement_show', methods: ['GET'])]
    public function show(Traitement $traitement): Response
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifier les droits d'accès
        if (!$this->canAccessTraitement($traitement, $user)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce traitement.');
        }

        return $this->render('traitement/show.html.twig', [
            'traitement' => $traitement,
            'user_role' => $this->getMainRole($user),
            'can_edit' => $this->canEditTraitement($traitement, $user),
            'can_manage_suivis' => $this->canManageSuivis($traitement, $user)
        ]);
    }

    #[Route('/{id}/edit', name: 'app_traitement_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function edit(Request $request, Traitement $traitement, EntityManagerInterface $entityManager): Response
    {
        $user = $this->security->getUser();
        
        if ($traitement->getPsychologue() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que vos propres traitements.');
        }

        $form = $this->createForm(TraitementType::class, $traitement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le traitement a été modifié avec succès.');

            return $this->redirectToRoute('app_traitement_show', ['id' => $traitement->getId()]);
        }

        return $this->render('traitement/edit.html.twig', [
            'traitement' => $traitement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_traitement_delete', methods: ['POST'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function delete(Request $request, Traitement $traitement, EntityManagerInterface $entityManager): Response
    {
        $user = $this->security->getUser();
        
        // Debug
        dump('Delete traitement', [
            'traitement_id' => $traitement->getId(),
            'user_id' => $user->getId(),
            'psychologue_id' => $traitement->getPsychologue()?->getId(),
            'is_owner' => $traitement->getPsychologue() === $user
        ]);
        
        if ($traitement->getPsychologue() !== $user) {
            throw $this->createAccessDeniedException('Vous ne pouvez supprimer que vos propres traitements.');
        }

        if ($this->isCsrfTokenValid('delete'.$traitement->getId(), $request->request->get('_token'))) {
            try {
                $entityManager->remove($traitement);
                $entityManager->flush();
                $this->addFlash('success', 'Le traitement a été supprimé avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_traitement_index');
    }

    private function canAccessTraitement(Traitement $traitement, User $user): bool
    {
        // Admin et responsable peuvent tout voir
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_RESPONSABLE_ETUDIANT')) {
            return true;
        }

        // Psychologue peut voir ses traitements
        if ($this->isGranted('ROLE_PSYCHOLOGUE') && $traitement->getPsychologue() === $user) {
            return true;
        }

        // Étudiant peut voir ses traitements
        if ($this->isGranted('ROLE_ETUDIANT') && $traitement->getEtudiant() === $user) {
            return true;
        }

        return false;
    }

    private function canEditTraitement(Traitement $traitement, User $user): bool
    {
        // Admin peut tout modifier
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Psychologue peut modifier ses traitements
        if ($this->isGranted('ROLE_PSYCHOLOGUE') && $traitement->getPsychologue() === $user) {
            return true;
        }

        return false;
    }

    private function canManageSuivis(Traitement $traitement, User $user): bool
    {
        // Admin peut tout gérer
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Psychologue peut gérer les suivis de ses traitements
        if ($this->isGranted('ROLE_PSYCHOLOGUE') && $traitement->getPsychologue() === $user) {
            return true;
        }

        // Étudiant peut gérer ses suivis (effectuer, commenter)
        if ($this->isGranted('ROLE_ETUDIANT') && $traitement->getEtudiant() === $user) {
            return true;
        }

        return false;
    }

    private function getMainRole(User $user): string
    {
        $roles = $user->getRoles();
        
        if (in_array('ROLE_ADMIN', $roles)) {
            return 'admin';
        } elseif (in_array('ROLE_PSYCHOLOGUE', $roles)) {
            return 'psychologue';
        } elseif (in_array('ROLE_ETUDIANT', $roles)) {
            return 'etudiant';
        } elseif (in_array('ROLE_RESPONSABLE_ETUDIANT', $roles)) {
            return 'responsable';
        }
        
        return 'user';
    }

    private function createTestTraitements(): array
    {
        return [
            [
                'id' => 1,
                'titre' => 'Thérapie Comportementale',
                'description' => 'Thérapie basée sur la modification des comportements problématiques',
                'categorie' => 'Comportemental',
                'statut' => 'EN_COURS',
                'priorite' => 'HAUTE',
                'progression' => 65,
                'dateDebut' => new \DateTime('2024-01-15'),
                'dateFin' => new \DateTime('2024-06-15'),
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
            ],
            [
                'id' => 2,
                'titre' => 'Thérapie Cognitive',
                'description' => 'Thérapie axée sur la modification des schémas de pensée',
                'categorie' => 'Thérapie',
                'statut' => 'EN_COURS',
                'priorite' => 'MOYENNE',
                'progression' => 40,
                'dateDebut' => new \DateTime('2024-02-01'),
                'dateFin' => new \DateTime('2024-08-01'),
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
            ],
            [
                'id' => 3,
                'titre' => 'Traitement Médicament',
                'description' => 'Suivi médical avec prescription adaptée',
                'categorie' => 'Médicament',
                'statut' => 'TERMINE',
                'priorite' => 'BASSE',
                'progression' => 100,
                'dateDebut' => new \DateTime('2023-12-01'),
                'dateFin' => new \DateTime('2024-03-01'),
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
            ],
            [
                'id' => 4,
                'titre' => 'Thérapie de Groupe',
                'description' => 'Sessions de thérapie en groupe pour le soutien mutuel',
                'categorie' => 'Thérapie',
                'statut' => 'EN_ATTENTE',
                'priorite' => 'MOYENNE',
                'progression' => 0,
                'dateDebut' => new \DateTime('2024-04-01'),
                'dateFin' => new \DateTime('2024-10-01'),
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
    }
}
