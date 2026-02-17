<?php
// src/Controller/AdminController.php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Profil;
use App\Enum\RoleType;
use App\Form\UserType;
use App\Form\UserFilterType;
use App\Repository\QuestionnaireRepository;
use App\Repository\QuestionRepository;
use App\Repository\ReponseQuestionnaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Service\ExportService;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/dashboard', name: 'admin_dashboard')]
    public function dashboard(
        EntityManagerInterface $entityManager,
        QuestionnaireRepository $questionnaireRepository,
        QuestionRepository $questionRepository,
        ReponseQuestionnaireRepository $reponseRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        
        $userRepo = $entityManager->getRepository(User::class);
        
        // Statistiques utilisateurs
        $statsUtilisateurs = [
            'total_utilisateurs' => $userRepo->count([]),
            'utilisateurs_en_attente' => $userRepo->count(['statut' => 'en_attente']),
            'utilisateurs_actifs' => $userRepo->count(['isActive' => true]),
            'etudiants' => $userRepo->count(['role' => RoleType::ETUDIANT]),
            'psychologues' => $userRepo->count(['role' => RoleType::PSYCHOLOGUE]),
            'responsables' => $userRepo->count(['role' => RoleType::RESPONSABLE_ETUDIANT]),
        ];

        // Statistiques questionnaires
        $tousQuestionnaires = $questionnaireRepository->findAll();

        $questionnairesIncomplets = array_values(array_filter(
            $tousQuestionnaires,
            fn($q) => count($q->getQuestions()) < $q->getNbreQuestions()
        ));

        $derniersQuestionnaires = array_slice(
            array_reverse($tousQuestionnaires),
            0,
            5
        );

        $totalQuestionnaires = count($tousQuestionnaires);
        $totalQuestions = count($questionRepository->findAll());
        $totalReponses = count($reponseRepository->findAll());

        // Générer les données pour les graphiques
        $graphiqueLabels = [];
        $graphiqueData = [];
        
        // Récupérer les données des 30 derniers jours
        for ($i = 29; $i >= 0; $i--) {
            $date = new \DateTime("-$i days");
            $graphiqueLabels[] = $date->format('d/m');
            
            // Compter les réponses pour cette date
            $count = $reponseRepository->createQueryBuilder('r')
                ->select('COUNT(r)')
                ->where('r.created_at >= :start')
                ->andWhere('r.created_at < :end')
                ->setParameter('start', $date->format('Y-m-d 00:00:00'))
                ->setParameter('end', $date->format('Y-m-d 23:59:59'))
                ->getQuery()
                ->getSingleScalarResult();
            
            $graphiqueData[] = $count;
        }

        // Récupérer la répartition par type de questionnaire
        // CORRECTION: utiliser questionnaire_id au lieu de id
        $repartitionTypes = $questionnaireRepository->createQueryBuilder('q')
            ->select('q.type, COUNT(q.questionnaire_id) as nombre')
            ->groupBy('q.type')
            ->getQuery()
            ->getResult();

        $typesFormatted = [];
        foreach ($repartitionTypes as $type) {
            $typesFormatted[] = [
                'label' => $type['type'] ?? 'Non défini',
                'nombre_questionnaires' => $type['nombre'],
                'color' => $this->getColorForType($type['type'])
            ];
        }

        // Statistiques globales
        $statsGlobales = [
            'total_questionnaires' => $totalQuestionnaires,
            'total_questions' => $totalQuestions,
            'total_reponses' => $totalReponses,
            'total_etudiants' => $statsUtilisateurs['etudiants'],
            'questionnaires_complets' => $this->countQuestionnairesComplets($tousQuestionnaires),
            'reponses_ce_mois' => $this->countReponsesThisMonth($reponseRepository),
            'reponses_severes' => $this->countReponsesSeveres($reponseRepository),
            'evolution_pourcentage' => $this->calculateEvolutionPercentage($reponseRepository),
            'repartition_types' => $typesFormatted,
            'graphique_labels' => $graphiqueLabels,
            'graphique_data' => $graphiqueData,
        ];

        // Récupérer les utilisateurs récents
        $utilisateursRecents = $userRepo->findBy([], ['createdAt' => 'DESC'], 10);

        return $this->render('admin/dashboard/index.html.twig', [
            'user' => $user,
            'stats_utilisateurs' => $statsUtilisateurs,
            'stats_globales' => $statsGlobales,
            'questionnaires_count' => $totalQuestionnaires,
            'questions_count' => $totalQuestions,
            'reponses_count' => $totalReponses,
            'derniers_questionnaires' => $derniersQuestionnaires,
            'questionnaires_incomplets' => $questionnairesIncomplets,
            'utilisateurs_recents' => $utilisateursRecents,
        ]);
    }

    #[Route('/utilisateurs', name: 'admin_users')]
    public function users(
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        // Créer le formulaire de filtre
        $filterForm = $this->createForm(UserFilterType::class);
        $filterForm->handleRequest($request);
        
        // Construire la requête
        $queryBuilder = $entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC');
        
        // Appliquer les filtres
        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $data = $filterForm->getData();
            
            if ($data['role']) {
                $queryBuilder->andWhere('u.role = :role')
                    ->setParameter('role', $data['role']);
            }
            
            if ($data['statut']) {
                $queryBuilder->andWhere('u.statut = :statut')
                    ->setParameter('statut', $data['statut']);
            }
            
            if ($data['search']) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->like('u.nom', ':search'),
                        $queryBuilder->expr()->like('u.prenom', ':search'),
                        $queryBuilder->expr()->like('u.email', ':search')
                    )
                )
                ->setParameter('search', '%' . $data['search'] . '%');
            }
        }
        
        // Sauvegarder les filtres dans la session pour les exports
        $session = $request->getSession();
        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $session->set('user_filters', $filterForm->getData());
        } elseif (!$filterForm->isSubmitted()) {
            // Réinitialiser les filtres si pas de soumission
            $session->remove('user_filters');
        }
        
        // PAGINATION MANUELLE
        $page = $request->query->getInt('page', 1);
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        // Comptage total
        $countQueryBuilder = clone $queryBuilder;
        $total = $countQueryBuilder->select('COUNT(u.user_id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        // Récupérer les résultats paginés
        $users = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        
        // Calculer le nombre total de pages
        $pages = ceil($total / $limit);

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'limit' => $limit,
            'filterForm' => $filterForm->createView(),
        ]);
    }

    #[Route('/utilisateurs/demandes', name: 'admin_pending_users')]
    public function pendingUsers(EntityManagerInterface $entityManager): Response
    {
        $pendingUsers = $entityManager->getRepository(User::class)
            ->findBy(
                ['statut' => 'en_attente'],
                ['createdAt' => 'DESC']
            );

        return $this->render('admin/users/pending_users.html.twig', [
            'pending_users' => $pendingUsers,
        ]);
    }

    #[Route('/utilisateurs/{id}/valider', name: 'admin_validate_user', methods: ['POST'])]
    public function validateUser(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $user->setStatut('actif');
        $user->setIsActive(true);
        $entityManager->flush();

        $this->addFlash('success', 'Utilisateur validé avec succès !');
        return $this->redirectToRoute('admin_pending_users');
    }

    #[Route('/utilisateurs/{id}/rejeter', name: 'admin_reject_user', methods: ['POST'])]
    public function rejectUser(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $user->setStatut('rejeté');
        $user->setIsActive(false);
        $entityManager->flush();

        $this->addFlash('success', 'Demande d\'inscription rejetée.');
        return $this->redirectToRoute('admin_pending_users');
    }

    #[Route('/utilisateurs/{id}/activer', name: 'admin_activate_user', methods: ['POST'])]
    public function activateUser(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $user->setIsActive(true);
        $user->setStatut('actif');
        $entityManager->flush();

        $this->addFlash('success', 'Utilisateur activé avec succès !');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/utilisateurs/{id}/desactiver', name: 'admin_deactivate_user', methods: ['POST'])]
    public function deactivateUser(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $user->setIsActive(false);
        $user->setStatut('inactif');
        $entityManager->flush();

        $this->addFlash('success', 'Utilisateur désactivé avec succès !');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/utilisateurs/{id}/modifier', name: 'admin_edit_user')]
    public function editUser(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $form = $this->createForm(UserType::class, $user, [
            'is_creation' => false // Ne pas afficher le champ mot de passe pour l'édition
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Utilisateur modifié avec succès !');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/users/edit_user.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/utilisateurs/{id}/supprimer', name: 'admin_delete_user', methods: ['POST'])]
    public function deleteUser(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        // Vérifier que l'utilisateur n'est pas l'admin actuel
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte !');
            return $this->redirectToRoute('admin_users');
        }

        // Supprimer d'abord le profil associé
        if ($user->getProfil()) {
            $entityManager->remove($user->getProfil());
        }

        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('success', 'Utilisateur supprimé avec succès !');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/utilisateurs/{id}/profil', name: 'admin_view_profile')]
    public function viewProfile(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }
        
        $profil = $user->getProfil();

        return $this->render('profile/show.html.twig', [
            'user' => $user,
            'profil' => $profil,
        ]);
    }

    #[Route('/statistiques', name: 'admin_statistics')]
    public function statistics(EntityManagerInterface $entityManager): Response
    {
        $userRepository = $entityManager->getRepository(User::class);
        
        $userStats = $userRepository->createQueryBuilder('u')
            ->select('u.role, COUNT(u.user_id) as count')
            ->groupBy('u.role')
            ->getQuery()
            ->getResult();

        $statusStats = $userRepository->createQueryBuilder('u')
            ->select('u.statut, COUNT(u.user_id) as count')
            ->groupBy('u.statut')
            ->getQuery()
            ->getResult();

        // Récupérer toutes les dates
        $allUsers = $userRepository->findAll();
        $monthlyStats = [];
        $currentYear = date('Y');
        
        foreach ($allUsers as $user) {
            $createdAt = $user->getCreatedAt();
            if ($createdAt->format('Y') == $currentYear) {
                $month = (int) $createdAt->format('n'); // 1-12
                if (!isset($monthlyStats[$month])) {
                    $monthlyStats[$month] = 0;
                }
                $monthlyStats[$month]++;
            }
        }
        
        // Trier par mois
        ksort($monthlyStats);

        return $this->render('admin/statistics.html.twig', [
            'userStats' => $userStats,
            'statusStats' => $statusStats,
            'monthlyStats' => $monthlyStats,
            'currentYear' => $currentYear,
        ]);
    }

    #[Route('/utilisateurs/nouveau', name: 'admin_user_new')]
    public function newUser(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, [
            'is_creation' => true // Passer l'option pour afficher le champ mot de passe
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hasher le mot de passe
            $plainPassword = $form->get('plainPassword')->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            // Configurer les valeurs par défaut
            $user->setIsVerified(true);
            $user->setIsActive(true);
            $user->setCreatedAt(new \DateTime());
            
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès !');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/users/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/utilisateurs/{id}/verifier-email', name: 'admin_user_verify', methods: ['POST'])]
    public function verifyEmail(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $user->setIsVerified(true);
        $entityManager->flush();

        $this->addFlash('success', 'Email vérifié avec succès !');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/utilisateurs/export/excel', name: 'admin_users_export_excel')]
    public function exportExcel(
        EntityManagerInterface $entityManager,
        ExportService $exportService,
        Request $request
    ): Response {
        // Récupérer tous les utilisateurs
        $users = $entityManager->getRepository(User::class)->findAll();
        
        return $exportService->exportToExcel($users);
    }

    #[Route('/utilisateurs/export/pdf', name: 'admin_users_export_pdf')]
    public function exportPdf(
        EntityManagerInterface $entityManager,
        ExportService $exportService,
        Request $request
    ): Response {
        // Récupérer tous les utilisateurs
        $users = $entityManager->getRepository(User::class)->findAll();
        
        return $exportService->exportToPdf($users);
    }

    #[Route('/utilisateurs/export/csv', name: 'admin_users_export_csv')]
    public function exportCsv(
        EntityManagerInterface $entityManager,
        ExportService $exportService,
        Request $request
    ): Response {
        // Récupérer tous les utilisateurs
        $users = $entityManager->getRepository(User::class)->findAll();
        
        return $exportService->exportToCsv($users);
    }

    // Méthodes privées pour les calculs statistiques
    private function countQuestionnairesComplets(array $questionnaires): int
    {
        return count(array_filter(
            $questionnaires,
            fn($q) => count($q->getQuestions()) >= $q->getNbreQuestions()
        ));
    }

    private function countReponsesThisMonth(ReponseQuestionnaireRepository $reponseRepository): int
    {
        $now = new \DateTime();
        $start = (new \DateTime())->modify('first day of this month')->setTime(0, 0, 0);
        $end = (new \DateTime())->modify('last day of this month')->setTime(23, 59, 59);

        return $reponseRepository->createQueryBuilder('r')
            ->select('COUNT(r)')
            ->where('r.created_at BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countReponsesSeveres(ReponseQuestionnaireRepository $reponseRepository): int
    {
        return $reponseRepository->createQueryBuilder('r')
            ->select('COUNT(r)')
            ->where('r.niveau = :niveau')
            ->setParameter('niveau', 'sévère')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function calculateEvolutionPercentage(ReponseQuestionnaireRepository $reponseRepository): float
    {
        $moisActuel = $this->countReponsesThisMonth($reponseRepository);
        
        $moisDernier = $reponseRepository->createQueryBuilder('r')
            ->select('COUNT(r)')
            ->where('r.created_at BETWEEN :start AND :end')
            ->setParameter('start', (new \DateTime())->modify('first day of last month')->setTime(0, 0, 0))
            ->setParameter('end', (new \DateTime())->modify('last day of last month')->setTime(23, 59, 59))
            ->getQuery()
            ->getSingleScalarResult();

        if ($moisDernier > 0) {
            return round((($moisActuel - $moisDernier) / $moisDernier) * 100, 1);
        }
        
        return 0;
    }

    private function getColorForType(?string $type): string
    {
        $colors = [
            'ANXETE' => 'danger',
            'DEPRESSION' => 'warning',
            'STRESS' => 'info',
            'BIEN_ETRE' => 'success',
            'ESTIME_SOI' => 'primary',
        ];

        return $colors[$type] ?? 'secondary';
    }
}