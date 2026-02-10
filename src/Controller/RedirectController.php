<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RedirectController extends AbstractController
{
    #[Route('/redirect', name: 'app_redirect_by_role')]
    public function redirectByRole(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Utilisez isGranted() qui vérifie les rôles Symfony
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        } elseif ($this->isGranted('ROLE_ETUDIANT')) {
            return $this->redirectToRoute('etudiant_dashboard');
        } elseif ($this->isGranted('ROLE_PSYCHOLOGUE')) {
            return $this->redirectToRoute('psy_dashboard');
        } elseif ($this->isGranted('ROLE_RESPONSABLE_ETUDIANT')) {
            return $this->redirectToRoute('responsable_dashboard');
        }

        // Par défaut, rediriger vers l'accueil
        return $this->redirectToRoute('app_home');
    }
}