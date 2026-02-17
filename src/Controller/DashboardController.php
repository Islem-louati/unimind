<?php
// src/Controller/DashboardController.php

namespace App\Controller;

use App\Entity\User;
use App\Entity\RendezVous;
use App\Repository\QuestionnaireRepository;
use App\Repository\QuestionRepository;
use App\Repository\ReponseQuestionnaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('L\'utilisateur n\'est pas une instance de App\Entity\User');
        }

        // Vérifier les rôles Symfony
        $roles = $user->getRoles();
        
        // Vérifier chaque rôle possible
        if (in_array('ROLE_ADMIN', $roles)) {
            return $this->redirectToRoute('admin_dashboard');
        } elseif (in_array('ROLE_PSYCHOLOGUE', $roles)) {
            return $this->redirectToRoute('app_dashboard_psy');
        } elseif (in_array('ROLE_RESPONSABLE_ETUDIANT', $roles)) {
            return $this->redirectToRoute('resp_dashboard');
        } else {
            // Par défaut, rediriger vers le dashboard étudiant
            return $this->redirectToRoute('etudiant_dashboard');
        }
    }

    #[Route('/dashboard/etudiant', name: 'etudiant_dashboard')]
    #[IsGranted('ROLE_ETUDIANT')]
    public function etudiantDashboard(EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Récupérer les questionnaires disponibles
        $totalQuestionnaires = $em->getRepository(\App\Entity\Questionnaire::class)
            ->createQueryBuilder('q')
            ->select('COUNT(q)')
            ->where('q.nbre_questions > 0')
            ->getQuery()
            ->getSingleScalarResult();

        // Récupérer les questionnaires récents - CORRECTION: utiliser created_at au lieu de createdAt
        $questionnairesRecents = $em->getRepository(\App\Entity\Questionnaire::class)
            ->findBy([], ['created_at' => 'DESC'], 5);

        return $this->render('etudiant/dashboard/index.html.twig', [
            'user' => $user,
            'total_questionnaires' => $totalQuestionnaires,
            'questionnaires_recents' => $questionnairesRecents,
        ]);
    }

    #[Route('/dashboard/psychologue', name: 'app_dashboard_psy')]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function psychologueDashboard(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        

        return $this->render('layout/dashboard.html.twig', [
            'user' => $user,
            
        ]);
    }

    #[Route('/dashboard/responsable', name: 'resp_dashboard')]
    #[IsGranted('ROLE_RESPONSABLE_ETUDIANT')]
    public function responsableDashboard(EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Données réelles pour le responsable
        $stats = [
            'total_etudiants' => $em->getRepository(User::class)->count(['role' => 'Etudiant']),
            'psychologues_actifs' => $em->getRepository(User::class)->countPsychologuesActifs(),
            'rendez_vous_mois' => $em->getRepository(RendezVous::class)->countRendezVousMois(),
            'taux_satisfaction' => $this->calculateSatisfactionRate($em),
            'nouveaux_etudiants' => $em->getRepository(User::class)->countNouveauxEtudiants(30),
            'croissance_etudiants' => $this->calculateStudentGrowth($em),
            'taux_utilisation' => $this->calculateUsageRate($em),
            'rendez_vous_presentiel' => $em->getRepository(RendezVous::class)->countRendezVousPresentiel(),
            'rendez_vous_en_ligne' => $em->getRepository(RendezVous::class)->countRendezVousEnLigne(),
            'pourcentage_presentiel' => $this->calculatePercentagePresentiel($em),
            'pourcentage_en_ligne' => $this->calculatePercentageEnLigne($em),
        ];

        return $this->render('responsable/dashboard/index.html.twig', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }

    #[Route('/dashboard/admin', name: 'admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDashboard(
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
            'etudiants' => $userRepo->count(['role' => 'Etudiant']),
            'psychologues' => $userRepo->count(['role' => 'Psychologue']),
            'responsables' => $userRepo->count(['role' => 'Responsable Etudiant']),
        ];

        // Statistiques questionnaires
        $tousQuestionnaires = $questionnaireRepository->findAll();

        $questionnairesIncomplets = array_values(array_filter(
            $tousQuestionnaires,
            fn($q) => count($q->getQuestions()) < $q->getNbreQuestions()
        ));

        // CORRECTION: utiliser created_at au lieu de createdAt
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
            
            // Compter les réponses pour cette date - CORRECTION: utiliser created_at au lieu de dateReponse
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

        // Récupérer la répartition par type de questionnaire - CORRECTION: utiliser questionnaire_id au lieu de id
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

        // Récupérer les utilisateurs récents - CORRECTION: utiliser created_at au lieu de createdAt
        $utilisateursRecents = $userRepo->findBy([], ['created_at' => 'DESC'], 10);

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

    #[Route('/admin/stats', name: 'admin_global_stats')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminGlobalStats(
        QuestionnaireRepository $questionnaireRepository,
        ReponseQuestionnaireRepository $reponseRepository
    ): Response {
        // Page dédiée aux statistiques avancées
        return $this->render('admin/stats/index.html.twig', [
            'stats_questionnaires' => $questionnaireRepository->getDetailedStats(),
            'stats_reponses' => $reponseRepository->getDetailedStats(),
            'evolution_mensuelle' => $reponseRepository->getMonthlyEvolution(),
        ]);
    }

    #[Route('/admin/users', name: 'admin_users')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminUsers(EntityManagerInterface $entityManager): Response
    {
        $users = $entityManager->getRepository(User::class)->findAll();

        return $this->render('admin/users/users.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/admin/users/pending', name: 'admin_users_pending')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminUsersPending(EntityManagerInterface $entityManager): Response
    {
        $pendingUsers = $entityManager->getRepository(User::class)->findBy([
            'statut' => 'en_attente'
        ], ['created_at' => 'DESC']);

        return $this->render('admin/users/pending_users.html.twig', [
            'pending_users' => $pendingUsers,
        ]);
    }

    #[Route('/admin/user/{id}/activate', name: 'admin_user_activate')]
    #[IsGranted('ROLE_ADMIN')]
    public function activateUser(User $user, EntityManagerInterface $entityManager): Response
    {
        $user->setStatut('actif');
        $user->setIsActive(true);
        $entityManager->flush();

        $this->addFlash('success', 'Utilisateur activé avec succès.');

        return $this->redirectToRoute('admin_users_pending');
    }

    #[Route('/admin/user/{id}/reject', name: 'admin_user_reject')]
    #[IsGranted('ROLE_ADMIN')]
    public function rejectUser(User $user, EntityManagerInterface $entityManager): Response
    {
        $user->setStatut('rejeté');
        $user->setIsActive(false);
        $entityManager->flush();

        $this->addFlash('warning', 'Utilisateur rejeté.');

        return $this->redirectToRoute('admin_users_pending');
    }

    #[Route('/admin/user/{id}/delete', name: 'admin_user_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteUser(User $user, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('danger', 'Utilisateur supprimé avec succès.');

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/user/{id}/toggle-active', name: 'admin_user_toggle_active')]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleUserActive(User $user, EntityManagerInterface $entityManager): Response
    {
        $user->setIsActive(!$user->isIsActive());
        $entityManager->flush();

        $status = $user->isIsActive() ? 'activé' : 'désactivé';
        $this->addFlash('info', "Utilisateur $status.");

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/user/{id}/edit', name: 'admin_user_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function editUser(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $nom = $request->request->get('nom');
            $prenom = $request->request->get('prenom');
            $email = $request->request->get('email');
            $statut = $request->request->get('statut');

            if ($nom && $prenom && $email) {
                $user->setNom($nom);
                $user->setPrenom($prenom);
                $user->setEmail($email);
                $user->setStatut($statut);

                $entityManager->flush();

                $this->addFlash('success', 'Utilisateur mis à jour avec succès.');
                return $this->redirectToRoute('admin_users');
            } else {
                $this->addFlash('error', 'Tous les champs sont obligatoires.');
            }
        }

        return $this->render('admin/users/edit_user.html.twig', [
            'user' => $user,
        ]);
    }

    // Méthodes privées pour les calculs statistiques
    private function calculateSatisfactionRate(EntityManagerInterface $em): float
    {
        // Implémentez votre logique de calcul du taux de satisfaction
        return 88.5;
    }

    private function calculateStudentGrowth(EntityManagerInterface $em): float
    {
        // Implémentez votre logique de calcul de croissance
        return 8.3;
    }

    private function calculateUsageRate(EntityManagerInterface $em): float
    {
        // Implémentez votre logique de calcul du taux d'utilisation
        return 65.0;
    }

    private function calculatePercentagePresentiel(EntityManagerInterface $em): float
    {
        // Implémentez votre logique de calcul du pourcentage de rendez-vous en présentiel
        return 67.0;
    }

    private function calculatePercentageEnLigne(EntityManagerInterface $em): float
    {
        // Implémentez votre logique de calcul du pourcentage de rendez-vous en ligne
        return 33.0;
    }

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

        // CORRECTION: utiliser created_at au lieu de dateReponse
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
        // CORRECTION: utiliser niveau au lieu de score
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
        
        // CORRECTION: utiliser created_at au lieu de dateReponse
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