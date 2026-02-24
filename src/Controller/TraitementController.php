<?php

namespace App\Controller;

use App\Entity\Traitement;
use App\Entity\User;
use App\Form\TraitementType;
use App\Repository\TraitementRepository;
use App\Service\EmailSchedulerService;
use App\Service\EmailService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
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
    private EntityManagerInterface $entityManager;
    private EmailSchedulerService $emailScheduler;

    public function __construct(Security $security, EntityManagerInterface $entityManager, EmailSchedulerService $emailScheduler)
    {
        $this->security = $security;
        $this->entityManager = $entityManager;
        $this->emailScheduler = $emailScheduler;
    }

    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('dashboard/index.html.twig', [
            'user_role' => $this->getMainRole($user)
        ]);
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
    public function index(TraitementRepository $traitementRepository, Request $request, PaginatorInterface $paginator): Response
    {
        $user = $this->security->getUser();
        
        // Récupérer les paramètres de tri et filtrage
        $sort = $request->query->get('custom_sort', '');  // Par défaut : pas de tri
        $order = $request->query->get('custom_order', 'asc');   // Par défaut : ordre ascendant
        $search = $request->query->get('search', '');
        $statutFilter = $request->query->get('statut', '');
        $prioriteFilter = $request->query->get('priorite', '');
        $categorieFilter = $request->query->get('categorie', '');

        if (!$user) {
            // Mode test : créer des données fictives
            $traitements = $this->createTestTraitements();
            $user_role = 'test';
            
            // Appliquer le tri manuel pour le mode test
            if (!empty($sort) && !empty($order)) {
                switch ($sort) {
                    case 't.statut':
                        // Tri par statut avec ordre personnalisé
                        usort($traitements, function($a, $b) use ($order) {
                            $statutOrder = ['EN_COURS' => 1, 'TERMINE' => 2, 'SUSPENDU' => 3];
                            $statutA = $statutOrder[$a['statut']] ?? 4;
                            $statutB = $statutOrder[$b['statut']] ?? 4;
                            
                            if ($order === 'desc') {
                                return $statutB - $statutA;
                            } else {
                                return $statutA - $statutB;
                            }
                        });
                        break;
                        
                    case 't.priorite':
                        // Tri par priorité avec ordre personnalisé
                        usort($traitements, function($a, $b) use ($order) {
                            $prioriteOrder = ['BASSE' => 1, 'MOYENNE' => 2, 'HAUTE' => 3];
                            $prioriteA = $prioriteOrder[$a['priorite']] ?? 4;
                            $prioriteB = $prioriteOrder[$b['priorite']] ?? 4;
                            
                            if ($order === 'desc') {
                                return $prioriteB - $prioriteA;
                            } else {
                                return $prioriteA - $prioriteB;
                            }
                        });
                        break;
                        
                    case 't.titre':
                        // Tri par titre
                        usort($traitements, function($a, $b) use ($order) {
                            $comparison = strcmp($a['titre'], $b['titre']);
                            return $order === 'desc' ? -$comparison : $comparison;
                        });
                        break;
                        
                    case 't.pourcentageCompletion':
                        // Tri par progression
                        usort($traitements, function($a, $b) use ($order) {
                            if ($order === 'desc') {
                                return $b['progression'] <=> $a['progression'];
                            } else {
                                return $a['progression'] <=> $b['progression'];
                            }
                        });
                        break;
                }
            }
            
            // Toujours trier par patient en dernier pour maintenir l'ordre alphabétique
            usort($traitements, function($a, $b) {
                $nomA = $a['etudiant']['nom'];
                $nomB = $b['etudiant']['nom'];
                if ($nomA === $nomB) {
                    return strcmp($a['etudiant']['prenom'], $b['etudiant']['prenom']);
                }
                return strcmp($nomA, $nomB);
            });
            
            // Créer une pagination manuelle pour le mode test
            $pagination = new \ArrayIterator($traitements);
        } else {
            // Mode normal : utiliser les données réelles avec filtrage et tri
            
            $user_role = $this->getMainRole($user);
            
            // Construire la requête de base avec tri hiérarchique
            $qb = $traitementRepository->createQueryBuilder('t')
                ->leftJoin('t.etudiant', 'e')
                ->leftJoin('t.psychologue', 'p')
                ->addSelect('t', 'e', 'p'); // Ajout de tous les alias pour le paginator

            // Appliquer le filtrage selon le rôle
            if ($this->isGranted('ROLE_RESPONSABLE_ETUDIANT')) {
                // Le responsable étudiant peut voir tous les traitements des étudiants
            } elseif ($this->isGranted('ROLE_PSYCHOLOGUE')) {
                $qb->where('t.psychologue = :user')
                   ->setParameter('user', $user);
            } elseif ($this->isGranted('ROLE_ETUDIANT')) {
                // L'étudiant ne voit que ses propres traitements
                $qb->where('t.etudiant = :user')
                   ->setParameter('user', $user);
            } else {
                // Les autres rôles ne voient que leurs propres traitements
            }

            // Appliquer les filtres
            if (!empty($search)) {
                $qb->andWhere('t.titre LIKE :search OR t.description LIKE :search OR e.nom LIKE :search OR e.prenom LIKE :search OR p.nom LIKE :search OR p.prenom LIKE :search')
                   ->setParameter('search', '%' . $search . '%');
            }

            if (!empty($statutFilter)) {
                $qb->andWhere('t.statut = :statut')
                   ->setParameter('statut', $statutFilter);
            }

            if (!empty($prioriteFilter)) {
                $qb->andWhere('t.priorite = :priorite')
                   ->setParameter('priorite', $prioriteFilter);
            }

            if (!empty($categorieFilter)) {
                $qb->andWhere('t.categorie = :categorie')
                   ->setParameter('categorie', $categorieFilter);
            }

            // GESTION COMPLÈTE DU TRI - LOGIQUE SIMPLIFIÉE
            if (!empty($sort) && !empty($order)) {
                switch ($sort) {
                    case 'e.nom':
                        // Tri par patient uniquement
                        $qb->orderBy('e.nom', $order)
                           ->addOrderBy('e.prenom', $order)
                           ->addOrderBy('t.traitement_id', 'ASC');
                        break;
                    case 't.titre':
                        // Tri par titre (patients d'abord pour cohérence)
                        $qb->orderBy('e.nom', 'ASC')
                           ->addOrderBy('e.prenom', 'ASC')
                           ->addOrderBy('t.titre', $order)
                           ->addOrderBy('t.traitement_id', 'ASC');
                        break;
                    case 't.statut':
                        // Tri par statut - logique simple et directe
                        $qb->orderBy('e.nom', 'ASC')
                           ->addOrderBy('e.prenom', 'ASC')
                           ->addOrderBy('t.statut', $order)
                           ->addOrderBy('t.traitement_id', 'ASC');
                        break;
                    case 't.priorite':
                        // Tri par priorité - ordre logique (basse < moyenne < haute)
                        $qb->orderBy('e.nom', 'ASC')
                           ->addOrderBy('e.prenom', 'ASC')
                           ->addOrderBy('CASE 
                               WHEN t.priorite = \'basse\' THEN 1
                               WHEN t.priorite = \'moyenne\' THEN 2
                               WHEN t.priorite = \'haute\' THEN 3
                               ELSE 4
                           END', $order)
                           ->addOrderBy('t.traitement_id', 'ASC');
                        break;
                    case 't.pourcentageCompletion':
                        // Tri par progression - logique simple et efficace
                        if ($order === 'desc') {
                            // DESC : plus grande progression d'abord
                            $qb->orderBy('e.nom', 'ASC')
                               ->addOrderBy('e.prenom', 'ASC')
                               ->addOrderBy('CASE 
                                   WHEN t.statut = \'termine\' THEN 100
                                   ELSE 0
                               END', 'DESC')
                               ->addOrderBy('t.date_debut', 'ASC')  // Plus anciens en premier
                               ->addOrderBy('t.traitement_id', 'ASC');
                        } else {
                            // ASC : plus petite progression d'abord
                            $qb->orderBy('e.nom', 'ASC')
                               ->addOrderBy('e.prenom', 'ASC')
                               ->addOrderBy('CASE 
                                   WHEN t.statut = \'termine\' THEN 100
                                   ELSE 0
                               END', 'ASC')
                               ->addOrderBy('t.date_debut', 'DESC')  // Plus récents en premier
                               ->addOrderBy('t.traitement_id', 'ASC');
                        }
                        break;
                    case 'p.nom':
                        // Tri par psychologue
                        $qb->orderBy('e.nom', 'ASC')
                           ->addOrderBy('e.prenom', 'ASC')
                           ->addOrderBy('p.nom', $order)
                           ->addOrderBy('p.prenom', $order)
                           ->addOrderBy('t.traitement_id', 'ASC');
                        break;
                    default:
                        // Par défaut: tri par patient puis par titre
                        $qb->orderBy('e.nom', 'ASC')
                           ->addOrderBy('e.prenom', 'ASC')
                           ->addOrderBy('t.titre', 'ASC')
                           ->addOrderBy('t.traitement_id', 'ASC');
                }
            } else {
                // Tri par défaut: tri par patient puis par titre
                $qb->orderBy('e.nom', 'ASC')
                   ->addOrderBy('e.prenom', 'ASC')
                   ->addOrderBy('t.titre', 'ASC')
                   ->addOrderBy('t.traitement_id', 'ASC');
            }

            // Paginer les résultats
            $pagination = $paginator->paginate(
                $qb,
                $request->query->getInt('page', 1),
                10 // items per page
            );

            // Calculer les statistiques sur TOUS les traitements (requête séparée avec filtres)
            $statsQb = $traitementRepository->createQueryBuilder('t')
                ->select('t')
                ->leftJoin('t.psychologue', 'p')
                ->leftJoin('t.etudiant', 'e')
                ->where('p.user_id = :user OR e.user_id = :user')
                ->setParameter('user', $user);

            // Appliquer les mêmes filtres que pour la pagination
            if ($search) {
                $statsQb->andWhere('t.titre LIKE :search OR e.nom LIKE :search OR e.prenom LIKE :search')
                   ->setParameter('search', '%' . $search . '%');
            }

            if ($statutFilter) {
                $statsQb->andWhere('t.statut = :statut')
                   ->setParameter('statut', $statutFilter);
            }

            if ($prioriteFilter) {
                $statsQb->andWhere('t.priorite = :priorite')
                   ->setParameter('priorite', $prioriteFilter);
            }

            if ($categorieFilter) {
                $statsQb->andWhere('t.categorie = :categorie')
                   ->setParameter('categorie', $categorieFilter);
            }

            $allTraitements = $statsQb->getQuery()->getResult();
            $stats = [
                'total' => count($allTraitements),
                'en_cours' => 0,
                'termines' => 0,
                'priorite_haute' => 0
            ];
            
            foreach ($allTraitements as $traitement) {
                if ($traitement->getStatut() === \App\Enum\StatutTraitement::EN_COURS->value) {
                    $stats['en_cours']++;
                }
                if ($traitement->getStatut() === \App\Enum\StatutTraitement::TERMINE->value) {
                    $stats['termines']++;
                }
                if ($traitement->getPriorite() === \App\Enum\PrioriteTraitement::HAUTE->value) {
                    $stats['priorite_haute']++;
                }
            }

            return $this->render('traitement/index.html.twig', [
                'pagination' => $pagination,
                'stats' => $stats,
                'user_role' => $user_role,
                'sort' => $sort,
                'order' => $order,
                'filters' => [
                    'search' => $search,
                    'statut' => $statutFilter,
                    'priorite' => $prioriteFilter,
                    'categorie' => $categorieFilter
                ]
            ]);
        }
    }

    /**
     * Page d'impression de la traduction d'un traitement
     */
    #[Route('/{id}/traduire/imprimer', name: 'app_traitement_translate_print', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function translatePrint(Traitement $traitement, Request $request, TranslationService $translationService): Response
    {
        $user = $this->security->getUser();
        
        // Vérification des permissions
        if (!$this->canViewTraitement($traitement, $user)) {
            throw $this->createAccessDeniedException('Accès non autorisé à ce traitement');
        }

        $targetLanguage = $request->query->get('language', 'en');
        
        // Préparation des données du traitement
        $traitementData = [
            'titre' => $traitement->getTitre(),
            'description' => $traitement->getDescription(),
            'objectifTherapeutique' => $traitement->getObjectifTherapeutique(),
            'type' => $traitement->getType(),
            'dosage' => $traitement->getDosage()
        ];

        // Traduction
        $result = $translationService->translateTraitement($traitementData, $targetLanguage);
        
        return $this->render('traitement/translate_print.html.twig', [
            'traitement' => $traitement,
            'supported_languages' => $translationService->getSupportedLanguages(),
            'target_language' => $targetLanguage,
            'translated_data' => $result,
            'translation_error' => $result['success'] ? null : ($result['error'] ?? 'Erreur lors de la traduction')
        ]);
    }

    /**
     * Page de traduction d'un traitement
     */
    #[Route('/{id}/traduire', name: 'app_traitement_translate', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function translate(Traitement $traitement, Request $request, TranslationService $translationService): Response
    {
        $user = $this->security->getUser();
        
        // Vérification des permissions
        if (!$this->canViewTraitement($traitement, $user)) {
            throw $this->createAccessDeniedException('Accès non autorisé à ce traitement');
        }

        $selectedLanguage = $request->query->get('language', 'en');
        $translatedData = null;
        $translationError = null;

        if ($request->isMethod('POST')) {
            $targetLanguage = $request->request->get('target_language', 'en');
            
            // Préparation des données du traitement
            $traitementData = [
                'titre' => $traitement->getTitre(),
                'description' => $traitement->getDescription(),
                'objectifTherapeutique' => $traitement->getObjectifTherapeutique(),
                'type' => $traitement->getType(),
                'dosage' => $traitement->getDosage()
            ];

            // Traduction
            $result = $translationService->translateTraitement($traitementData, $targetLanguage);
            
            if ($result['success']) {
                $translatedData = $result;
                $selectedLanguage = $targetLanguage;
            } else {
                $translationError = $result['error'] ?? 'Erreur lors de la traduction';
            }
        }

        return $this->render('traitement/translate.html.twig', [
            'traitement' => $traitement,
            'supported_languages' => $translationService->getSupportedLanguages(),
            'selected_language' => $selectedLanguage,
            'translated_data' => $translatedData,
            'translation_error' => $translationError
        ]);
    }

    /**
     * Vérifie si l'utilisateur peut voir le traitement
     */
    private function canViewTraitement(Traitement $traitement, $user): bool
    {
        if (!$user) {
            return false;
        }

        // Admin peut tout voir
        if ($this->isGranted('ROLE_ADMIN')) {
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

        // Responsable étudiant peut voir les traitements des étudiants
        if ($this->isGranted('ROLE_RESPONSABLE_ETUDIANT')) {
            return true;
        }

        return false;
    }

    /**
     * Obtenir les statistiques globales de tous les traitements (non paginés)
     */
    private function getGlobalStats($user): array
    {
        $user_role = $this->getMainRole($user);
        
        $qb = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from('App\Entity\Traitement', 't');
        
        // Appliquer les filtres de rôle selon le type d'utilisateur
        if ($user_role === 'psychologue') {
            $qb->where('t.psychologue = :user')
               ->setParameter('user', $user);
        } elseif ($user_role === 'etudiant') {
            $qb->where('t.etudiant = :user')
               ->setParameter('user', $user);
        }
        // Pour admin et responsable_etudiant, on voit tout
        
        // Obtenir tous les traitements (sans pagination)
        $allTraitements = $qb->getQuery()->getResult();
        
        // Calculer les statistiques
        $stats = [
            'total' => count($allTraitements),
            'en_cours' => 0,
            'termines' => 0,
            'priorite_haute' => 0,
            'suspendus' => 0
        ];
        
        foreach ($allTraitements as $traitement) {
            switch ($traitement->getStatut()) {
                case 'en cours':
                    $stats['en_cours']++;
                    break;
                case 'termine':
                    $stats['termines']++;
                    break;
                case 'suspendu':
                    $stats['suspendus']++;
                    break;
            }
            
            if ($traitement->getPriorite() === 'haute') {
                $stats['priorite_haute']++;
            }
        }
        
        return $stats;
    }

    #[Route('/new', name: 'app_traitement_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $traitement = new Traitement();
        $form = $this->createForm(TraitementType::class, $traitement, [
            'show_etudiant_field' => true
        ]);
        
        $form->handleRequest($request);
        
        // Définir le psychologue AVANT la validation
        if ($form->isSubmitted()) {
            $traitement->setPsychologue($this->security->getUser());
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Validation manuelle pour contourner le bug de validation
            if (!$traitement->getPsychologue()) {
                $this->addFlash('error', 'Le psychologue doit être défini.');
                return $this->render('traitement/new.html.twig', [
                    'traitement' => $traitement,
                    'form' => $form->createView(),
                ]);
            }
            
            // Vérification supplémentaire que l'étudiant est bien un étudiant
            $etudiant = $traitement->getEtudiant();
            if ($etudiant && !$etudiant->isEtudiant()) {
                $this->addFlash('error', 'L\'utilisateur sélectionné n\'est pas un étudiant valide.');
                return $this->render('traitement/new.html.twig', [
                    'traitement' => $traitement,
                    'form' => $form->createView(),
                ]);
            }
            
            $entityManager->persist($traitement);
            $entityManager->flush();
            
            // Envoyer l'email de notification automatique
            $this->emailScheduler->onTraitementCreated($traitement);
            
            $this->addFlash('success', 'Le traitement a été créé avec succès.');

            return $this->redirectToRoute('app_traitement_show', ['id' => $traitement->getId()]);
        }

        // Afficher les erreurs de validation si le formulaire est invalide
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Le formulaire contient des erreurs. Veuillez corriger les champs obligatoires.');
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

        $form = $this->createForm(TraitementType::class, $traitement, [
            'show_etudiant_field' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérification supplémentaire que l'étudiant est bien un étudiant
            $etudiant = $traitement->getEtudiant();
            if ($etudiant && !$etudiant->isEtudiant()) {
                $this->addFlash('error', 'L\'utilisateur sélectionné n\'est pas un étudiant valide.');
                return $this->render('traitement/edit.html.twig', [
                    'traitement' => $traitement,
                    'form' => $form->createView(),
                ]);
            }
            
            // Vérifier si le statut a changé vers "termine"
            $ancienStatut = $traitement->getStatut();
            $entityManager->flush();
            
            if ($traitement->getStatut() === 'termine' && $ancienStatut !== 'termine') {
                $this->emailScheduler->onTraitementFinished($traitement);
            }

            $this->addFlash('success', 'Le traitement a été modifié avec succès.');

            return $this->redirectToRoute('app_traitement_show', ['id' => $traitement->getId()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Le formulaire contient des erreurs. Veuillez corriger les champs obligatoires.');
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
        
        if ($traitement->getPsychologue() !== $user && !$this->isGranted('ROLE_ADMIN')) {
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

        }

    private function canAccessTraitement(Traitement $traitement, User $user): bool
    {
        // Responsable étudiant peut voir tous les traitements
        if ($this->isGranted('ROLE_RESPONSABLE_ETUDIANT')) {
            return true;
        }

        // Psychologue ne voit que ses propres traitements
        if ($this->isGranted('ROLE_PSYCHOLOGUE')) {
            return $traitement->getPsychologue() === $user;
        }

        // Étudiant ne voit que ses propres traitements
        if ($this->isGranted('ROLE_ETUDIANT')) {
            return $traitement->getEtudiant() === $user;
        }

        return false;
    }

    private function canEditTraitement(Traitement $traitement, User $user): bool
    {
        // Seul le psychologue peut modifier ses traitements
        if ($this->isGranted('ROLE_PSYCHOLOGUE') && $traitement->getPsychologue() === $user) {
            return true;
        }

        return false;
    }

    private function canManageSuivis(Traitement $traitement, User $user): bool
    {
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
        $traitements = [
            [
                'id' => 1,
                'titre' => 'Gestion du Stress',
                'description' => 'Techniques de gestion du stress',
                'categorie' => 'Comportemental',
                'statut' => 'TERMINE',
                'priorite' => 'BASSE',
                'progression' => 100,
                'dateDebut' => new \DateTime('2024-01-15'),
                'dateFin' => new \DateTime('2024-06-15'),
                'etudiant' => [
                    'nom' => 'Etudiant',
                    'prenom' => 'Jean',
                    'fullName' => 'Jean Etudiant',
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
                'titre' => 'Thérapie Comportementale',
                'description' => 'Thérapie basée sur la modification des comportements problématiques',
                'categorie' => 'Comportemental',
                'statut' => 'EN_COURS',
                'priorite' => 'HAUTE',
                'progression' => 100,
                'dateDebut' => new \DateTime('2024-02-01'),
                'dateFin' => new \DateTime('2024-08-01'),
                'etudiant' => [
                    'nom' => 'Etudiant',
                    'prenom' => 'Jean',
                    'fullName' => 'Jean Etudiant',
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
                'titre' => 'Thérapie Cognitive',
                'description' => 'Thérapie axée sur la modification des schémas de pensée',
                'categorie' => 'Cognitif',
                'statut' => 'EN_COURS',
                'priorite' => 'MOYENNE',
                'progression' => 100,
                'dateDebut' => new \DateTime('2024-03-01'),
                'dateFin' => new \DateTime('2024-09-01'),
                'etudiant' => [
                    'nom' => 'Etudiant',
                    'prenom' => 'Jean',
                    'fullName' => 'Jean Etudiant',
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
                'titre' => 'Gestion du stress des examens',
                'description' => 'Gestion du stress lié aux examens',
                'categorie' => 'Cognitif',
                'statut' => 'EN_COURS',
                'priorite' => 'MOYENNE',
                'progression' => 33.3,
                'dateDebut' => new \DateTime('2024-04-01'),
                'dateFin' => new \DateTime('2024-10-01'),
                'etudiant' => [
                    'nom' => 'Dubois',
                    'prenom' => 'Thomas',
                    'fullName' => 'Thomas Dubois',
                    'email' => 'thomas.dubois@etudiant.unimind.com'
                ],
                'psychologue' => [
                    'nom' => 'Psychologue',
                    'prenom' => 'Test',
                    'fullName' => 'Test Psychologue',
                    'email' => 'psy@test.com'
                ]
            ],
            [
                'id' => 5,
                'titre' => 'Gestion de l\'anxiété',
                'description' => 'Techniques de gestion de l\'anxiété',
                'categorie' => 'Cognitif',
                'statut' => 'EN_COURS',
                'priorite' => 'MOYENNE',
                'progression' => 40,
                'dateDebut' => new \DateTime('2024-05-01'),
                'dateFin' => new \DateTime('2024-11-01'),
                'etudiant' => [
                    'nom' => 'Dubois',
                    'prenom' => 'Thomas',
                    'fullName' => 'Thomas Dubois',
                    'email' => 'thomas.dubois@etudiant.unimind.com'
                ],
                'psychologue' => [
                    'nom' => 'Psychologue',
                    'prenom' => 'Test',
                    'fullName' => 'Test Psychologue',
                    'email' => 'psy@test.com'
                ]
            ],
            [
                'id' => 6,
                'titre' => 'Amélioration du sommeil',
                'description' => 'Techniques pour améliorer la qualité du sommeil',
                'categorie' => 'Cognitif',
                'statut' => 'EN_COURS',
                'priorite' => 'MOYENNE',
                'progression' => 40,
                'dateDebut' => new \DateTime('2024-06-01'),
                'dateFin' => new \DateTime('2024-12-01'),
                'etudiant' => [
                    'nom' => 'Dubois',
                    'prenom' => 'Thomas',
                    'fullName' => 'Thomas Dubois',
                    'email' => 'thomas.dubois@etudiant.unimind.com'
                ],
                'psychologue' => [
                    'nom' => 'Psychologue',
                    'prenom' => 'Test',
                    'fullName' => 'Test Psychologue',
                    'email' => 'psy@test.com'
                ]
            ],
            [
                'id' => 7,
                'titre' => 'Développement de la confiance en soi',
                'description' => 'Techniques pour développer la confiance en soi',
                'categorie' => 'Comportemental',
                'statut' => 'EN_COURS',
                'priorite' => 'MOYENNE',
                'progression' => 40,
                'dateDebut' => new \DateTime('2024-07-01'),
                'dateFin' => new \DateTime('2025-01-01'),
                'etudiant' => [
                    'nom' => 'Dubois',
                    'prenom' => 'Thomas',
                    'fullName' => 'Thomas Dubois',
                    'email' => 'thomas.dubois@etudiant.unimind.com'
                ],
                'psychologue' => [
                    'nom' => 'Psychologue',
                    'prenom' => 'Test',
                    'fullName' => 'Test Psychologue',
                    'email' => 'psy@test.com'
                ]
            ],
            [
                'id' => 8,
                'titre' => 'Gestion des conflits interpersonnels',
                'description' => 'Techniques de gestion des conflits',
                'categorie' => 'Cognitif',
                'statut' => 'EN_COURS',
                'priorite' => 'MOYENNE',
                'progression' => 40,
                'dateDebut' => new \DateTime('2024-08-01'),
                'dateFin' => new \DateTime('2025-02-01'),
                'etudiant' => [
                    'nom' => 'Dubois',
                    'prenom' => 'Thomas',
                    'fullName' => 'Thomas Dubois',
                    'email' => 'thomas.dubois@etudiant.unimind.com'
                ],
                'psychologue' => [
                    'nom' => 'Psychologue',
                    'prenom' => 'Test',
                    'fullName' => 'Test Psychologue',
                    'email' => 'psy@test.com'
                ]
            ],
            [
                'id' => 9,
                'titre' => 'rytuyuioipvg',
                'description' => 'Test traitement',
                'categorie' => 'Cognitif',
                'statut' => 'EN_COURS',
                'priorite' => 'MOYENNE',
                'progression' => 5,
                'dateDebut' => new \DateTime('2024-09-01'),
                'dateFin' => new \DateTime('2025-03-01'),
                'etudiant' => [
                    'nom' => 'Dubois',
                    'prenom' => 'Thomas',
                    'fullName' => 'Thomas Dubois',
                    'email' => 'thomas.dubois@etudiant.unimind.com'
                ],
                'psychologue' => [
                    'nom' => 'Psychologue',
                    'prenom' => 'Test',
                    'fullName' => 'Test Psychologue',
                    'email' => 'psy@test.com'
                ]
            ],
            [
                'id' => 10,
                'titre' => 'Motivation et objectifs académiques',
                'description' => 'Techniques de motivation',
                'categorie' => 'Cognitif',
                'statut' => 'EN_COURS',
                'priorite' => 'MOYENNE',
                'progression' => 40,
                'dateDebut' => new \DateTime('2024-10-01'),
                'dateFin' => new \DateTime('2025-04-01'),
                'etudiant' => [
                    'nom' => 'Dubois',
                    'prenom' => 'Thomas',
                    'fullName' => 'Thomas Dubois',
                    'email' => 'thomas.dubois@etudiant.unimind.com'
                ],
                'psychologue' => [
                    'nom' => 'Psychologue',
                    'prenom' => 'Test',
                    'fullName' => 'Test Psychologue',
                    'email' => 'psy@test.com'
                ]
            ]
        ];
        
        // Trier par nom de patient puis par titre de traitement
        usort($traitements, function($a, $b) {
            $nomA = $a['etudiant']['nom'];
            $nomB = $b['etudiant']['nom'];
            
            if ($nomA === $nomB) {
                return strcmp($a['titre'], $b['titre']);
            }
            
            return strcmp($nomA, $nomB);
        });
        
        return $traitements;
    }
}