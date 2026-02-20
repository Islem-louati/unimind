<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardEtudiantController extends AbstractController
{
    #[Route('/etudiant', name: 'app_etudiant_home')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_evenement_etudiant_index');
    }
}
