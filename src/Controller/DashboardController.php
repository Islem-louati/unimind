<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\RendezVous;
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
            return $this->redirectToRoute('psy_dashboard');
        } elseif (in_array('ROLE_RESPONSABLE_ETUDIANT', $roles)) {
            return $this->redirectToRoute('resp_dashboard');
        } else {
            // Par défaut, rediriger vers le dashboard étudiant
            return $this->redirectToRoute('etudiant_dashboard');
        }
    }

    #[Route('/dashboard/etudiant', name: 'etudiant_dashboard')]
    #[IsGranted('ROLE_ETUDIANT')]
    public function etudiantDashboard(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Données de démo pour l'affichage uniquement
        $stats = [
            'total_appointments' => 3,
            'unread_messages' => 2,
            'progress' => 65,
        ];

        return $this->render('dashboard/etudiant.html.twig', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }

    #[Route('/dashboard/psychologue', name: 'psy_dashboard')]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function psychologueDashboard(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Données de démo pour l'affichage uniquement
        $stats = [
            'today_appointments' => 4,
            'active_patients' => 15,
            'weekly_appointments' => 20,
            'fill_rate' => 75,
        ];

        return $this->render('dashboard/psychologue.html.twig', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }

    #[Route('/dashboard/responsable', name: 'resp_dashboard')]
    #[IsGranted('ROLE_RESPONSABLE_ETUDIANT')]
    public function responsableDashboard(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Données de démo pour l'affichage uniquement
        $stats = [
            'total_students' => 150,
            'active_psychologists' => 8,
            'monthly_appointments' => 45,
            'satisfaction_rate' => 88,
            'new_students_30d' => 12,
            'student_growth' => 8,
            'usage_rate' => 65,
            'in_person_appointments' => 30,
            'online_appointments' => 15,
            'in_person_percentage' => 67,
            'online_percentage' => 33,
        ];

        return $this->render('dashboard/responsable.html.twig', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }


    #[Route('/dashboard/admin', name: 'admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDashboard(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $userRepo = $entityManager->getRepository(User::class);
        
        // Calculer les statistiques
        $stats = [
            'total_users' => $userRepo->count([]),
            'pending_users' => $userRepo->count(['statut' => 'en_attente']),
            'active_users' => $userRepo->count(['isActive' => true]),
            'students' => $userRepo->count(['role' => 'Etudiant']),
            'psychologues' => $userRepo->count(['role' => 'Psychologue']),
            'responsables' => $userRepo->count(['role' => 'Responsable Etudiant']),
        ];

        // Récupérer les utilisateurs récents
        $recentUsers = $userRepo->findBy([], ['createdAt' => 'DESC'], 10);

        return $this->render('admin/dashboard.html.twig', [
            'user' => $user,
            'stats' => $stats,
            'recent_users' => $recentUsers,
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
    ], ['createdAt' => 'DESC']);

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
}