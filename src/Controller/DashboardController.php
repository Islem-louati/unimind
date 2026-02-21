<?php
// src/Controller/DashboardController.php

namespace App\Controller;

use App\Entity\User;
use App\Entity\RendezVous;
use App\Entity\Profil;
use App\Repository\QuestionnaireRepository;
use App\Repository\ConsultationRepository;
use App\Repository\QuestionRepository;
use App\Repository\ReponseQuestionnaireRepository;
use App\Repository\TraitementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\RendezVousRepository;
use App\Repository\DisponibilitePsyRepository;


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
    public function etudiantDashboard(
        RendezVousRepository $rdvRepository,
        ConsultationRepository $consultationRepository,
        TraitementRepository $traitementRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Récupérer les prochains rendez-vous
        $prochainsRdv = $rdvRepository->findProchainsRendezVous($user);

        // Récupérer tous les rendez-vous pour les statistiques
        $tousRdv = $rdvRepository->findBy(['etudiant' => $user]);

        // Calculer les statistiques
        $stats = [
            'prochains_rdv' => count($prochainsRdv),
            'total_consultations' => count(array_filter($tousRdv, fn($rdv) => method_exists($rdv, 'isTermine') ? $rdv->isTermine() : false)),
            'traitements_actifs' => $traitementRepository ? $traitementRepository->countActifsByEtudiant($user) : 0,
        ];

        // Rendez-vous passés (terminés)
        $rdvPasses = array_filter($tousRdv, fn($rdv) => method_exists($rdv, 'isTermine') ? $rdv->isTermine() : false);

        return $this->render('dashboard/etudiant.html.twig', [
            'user' => $user,
            'prochains_rdv' => $prochainsRdv,
            'rdv_passés' => $rdvPasses,
            'stats' => $stats,
        ]);
    }

    #[Route('/dashboard/etudiant/rendez-vous', name: 'app_etudiant_rdv')]
    #[IsGranted('ROLE_ETUDIANT')]
    public function etudiantRendezVous(RendezVousRepository $rdvRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $rendezVous = $rdvRepository->findByEtudiantOrderedByDate($user, 'DESC');

        return $this->render('dashboard/etudiant/rendez-vous.html.twig', [
            'user' => $user,
            'rendez_vous' => $rendezVous,
        ]);
    }

    #[Route('/dashboard/etudiant/consultations', name: 'app_etudiant_consultations')]
    #[IsGranted('ROLE_ETUDIANT')]
    public function etudiantConsultations(ConsultationRepository $consultationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $consultations = $consultationRepository->findBy(
            ['etudiant' => $user],
            ['date_consultation' => 'DESC']
        );

        return $this->render('dashboard/etudiant/consultations.html.twig', [
            'user' => $user,
            'consultations' => $consultations,
        ]);
    }

    #[Route('/dashboard/etudiant/traitements', name: 'app_etudiant_traitements')]
    #[IsGranted('ROLE_ETUDIANT')]
    public function etudiantTraitements(TraitementRepository $traitementRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $traitementsActifs = $traitementRepository ? $traitementRepository->findActifsByEtudiant($user) : [];
        $traitementsTermines = $traitementRepository ? $traitementRepository->findTerminesByEtudiant($user) : [];

        return $this->render('dashboard/etudiant/traitements.html.twig', [
            'user' => $user,
            'traitements_actifs' => $traitementsActifs,
            'traitements_termines' => $traitementsTermines,
        ]);
    }

    #[Route('/dashboard/etudiant/profil', name: 'app_etudiant_profil')]
    #[IsGranted('ROLE_ETUDIANT')]
    public function etudiantProfil(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $profil = $user->getProfil();

        if (!$profil) {
            $profil = new Profil();
            $profil->setUser($user);
        }

        return $this->render('profile/indexP.html.twig', [
            'user' => $user,
            'profil' => $profil,
        ]);
    }

    #[Route('/dashboard/psy')]
    #[IsGranted('ROLE_PSYCHOLOGUE')]

    #[Route('', name: 'app_dashboard_psy')]
    public function psychologueDashboard(
        EntityManagerInterface $em,
        RendezVousRepository $rdvRepository,
        ConsultationRepository $consultationRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Debug temporaire (removed dumps)

        // Statistiques pour le dashboard
        $stats = [
            'rdv_aujourdhui' => $rdvRepository->count([
                'psy' => $user,
                // Ajouter un filtre pour aujourd'hui si vous avez la méthode
            ]),
            'patients_actifs' => count($user->getConsultationsPsy()),
            'rdv_en_attente' => $rdvRepository->count([
                'psy' => $user,
                // Ajouter statut "en_attente" si vous l'avez
            ]),
        ];

        // Prochains rendez-vous
        $prochainsRdv = $rdvRepository->findByPsyOrderedByDate($user, 'ASC', 5);

        return $this->render('dashboard/psychologue.html.twig', [
            'user' => $user,
            'stats' => $stats,
            'prochains_rdv' => $prochainsRdv,
        ]);
    }

    #[Route('/consultations', name: 'app_psy_consultations')]
    public function consultations(ConsultationRepository $consultationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $consultations = $consultationRepository->findBy(
            ['psy' => $user],
            ['date_consultation' => 'DESC']
        );

        return $this->render('dashboard/psychologue/consultations.html.twig', [
            'user' => $user,
            'consultations' => $consultations,
        ]);
    }

    #[Route('/patients', name: 'app_psy_patients')]
    public function patients(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Récupérer tous les étudiants uniques qui ont eu des consultations avec ce psy
        $consultations = $user->getConsultationsPsy();
        $patients = [];

        foreach ($consultations as $consultation) {
            $etudiant = $consultation->getEtudiant();
            if ($etudiant && !isset($patients[$etudiant->getUserId()])) {
                $patients[$etudiant->getUserId()] = $etudiant;
            }
        }

        return $this->render('dashboard/psychologue/patients.html.twig', [
            'user' => $user,
            'patients' => $patients,
        ]);
    }

    #[Route('/rendez-vous', name: 'app_psy_rendez_vous')]
    public function rendezVous(RendezVousRepository $rendezVousRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Récupérer tous les rendez-vous du psychologue
        $rendez_vous_list = $rendezVousRepository->findByPsyOrderedByDate($user, 'DESC');

        // Organiser par statut
        $demandes = array_filter($rendez_vous_list, fn($rdv) => $rdv->getStatut() === 'demande');
        $confirmes = array_filter($rendez_vous_list, fn($rdv) => $rdv->getStatut() === 'confirme');
        $en_cours = array_filter($rendez_vous_list, fn($rdv) => $rdv->getStatut() === 'en-cours');
        $termines = array_filter($rendez_vous_list, fn($rdv) => $rdv->getStatut() === 'terminé');
        $annules = array_filter($rendez_vous_list, fn($rdv) => $rdv->getStatut() === 'annulé');

        return $this->render('psy/rendezvous/index.html.twig', [
            'user' => $user,
            'rendez_vous_list' => $rendez_vous_list,
            'demandes' => $demandes,
            'confirmes' => $confirmes,
            'en_cours' => $en_cours,
            'termines' => $termines,
            'annules' => $annules,
        ]);
    }

    #[Route('/profil', name: 'app_psy_profil')]
    public function profil(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $profil = $user->getProfil();

        if (!$profil) {
            $profil = new Profil();
            $profil->setUser($user);
        }

        return $this->render('profile/indexP.html.twig', [
            'user' => $user,
            'profil' => $profil,
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
