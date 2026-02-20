<?php

namespace App\Controller\Evenement\ResponsableEtudiant;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Dashboard responsable étudiant – Module Événements (accès limité).
 */
#[Route('/responsable-etudiant', name: 'app_back_')]
final class BackController extends AbstractController
{
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->redirectToRoute('app_back_evenement_index');
    }
}
