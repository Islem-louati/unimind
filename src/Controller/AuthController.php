<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('Authentification/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/dashboard-redirect', name: 'app_dashboard_redirect')]
    #[IsGranted('ROLE_USER')]
    public function dashboardRedirect(): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $userRole = $user->getRole();

        // Redirection basée sur le rôle
        return match ($userRole) {
            'Psychologue' => $this->redirectToRoute('app_dashboard_psy'),
            'Responsable Etudiant' => $this->redirectToRoute('app_dashboard_responsable'),
            'Admin' => $this->redirectToRoute('app_dashboard_admin'),
            default => $this->redirectToRoute('app_dashboard_etudiant'),
        };
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // This method can be empty - it will be intercepted by the logout key on your firewall
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall');
    }
}
