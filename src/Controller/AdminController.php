<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Profil;
use App\Enum\RoleType;
use App\Form\UserType;
use App\Form\UserFilterType;
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
    public function dashboard(EntityManagerInterface $entityManager): Response
    {
        // Statistiques
        $userRepository = $entityManager->getRepository(User::class);
        
        $totalUsers = $userRepository->count([]);
        $pendingUsers = $userRepository->count(['statut' => 'en_attente']);
        $activeUsers = $userRepository->count(['statut' => 'actif', 'isActive' => true]);
        
        // Compter par rôle
        $students = $userRepository->count(['role' => RoleType::ETUDIANT]);
        $psychologists = $userRepository->count(['role' => RoleType::PSYCHOLOGUE]);
        $responsables = $userRepository->count(['role' => RoleType::RESPONSABLE_ETUDIANT]);
        
        // Derniers utilisateurs (10 derniers)
        $recentUsers = $userRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            10
        );

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'total_users' => $totalUsers,
                'pending_users' => $pendingUsers,
                'active_users' => $activeUsers,
                'students' => $students,
                'psychologues' => $psychologists,
                'responsables' => $responsables,
            ],
            'recent_users' => $recentUsers,
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

        // CORRECTION : Récupérer toutes les dates et traiter côté PHP
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
    #[Route('/admin/utilisateurs/export/excel', name: 'admin_users_export_excel')]
    public function exportExcel(
        EntityManagerInterface $entityManager,
        ExportService $exportService,
        Request $request
    ): Response {
        // Récupérer tous les utilisateurs
        $users = $entityManager->getRepository(User::class)->findAll();
        
        return $exportService->exportToExcel($users);
    }

    #[Route('/admin/utilisateurs/export/pdf', name: 'admin_users_export_pdf')]
    public function exportPdf(
        EntityManagerInterface $entityManager,
        ExportService $exportService,
        Request $request
    ): Response {
        // Récupérer tous les utilisateurs
        $users = $entityManager->getRepository(User::class)->findAll();
        
        return $exportService->exportToPdf($users);
    }

    #[Route('/admin/utilisateurs/export/csv', name: 'admin_users_export_csv')]
    public function exportCsv(
        EntityManagerInterface $entityManager,
        ExportService $exportService,
        Request $request
    ): Response {
        // Récupérer tous les utilisateurs
        $users = $entityManager->getRepository(User::class)->findAll();
        
        return $exportService->exportToCsv($users);
    }

    /**
     * Récupère les utilisateurs filtrés (même logique que la méthode users())
     */
    private function getFilteredUsers(EntityManagerInterface $entityManager, Request $request): array
    {
        $queryBuilder = $entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC');
        
        // Récupérer les paramètres de filtrage de la session ou de la requête
        $session = $request->getSession();
        
        // Si des filtres existent dans la session (depuis la page de liste)
        $filters = $session->get('user_filters', []);
        
        if ($filters) {
            if (isset($filters['role']) && $filters['role']) {
                $queryBuilder->andWhere('u.role = :role')
                    ->setParameter('role', $filters['role']);
            }
            
            if (isset($filters['statut']) && $filters['statut']) {
                $queryBuilder->andWhere('u.statut = :statut')
                    ->setParameter('statut', $filters['statut']);
            }
            
            if (isset($filters['search']) && $filters['search']) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->like('u.nom', ':search'),
                        $queryBuilder->expr()->like('u.prenom', ':search'),
                        $queryBuilder->expr()->like('u.email', ':search'),
                        $queryBuilder->expr()->like('u.cin', ':search')
                    )
                )
                ->setParameter('search', '%' . $filters['search'] . '%');
            }
        }
        
        return $queryBuilder->getQuery()->getResult();
    }
}