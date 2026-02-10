<?php

namespace App\Controller\Etudiant;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ETUDIANT')]
#[Route('/etudiant')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'etudiant_dashboard')]
    public function index(): Response
    {
        return $this->render('etudiant/dashboard/index.html.twig', [
            'controller_name' => 'DashboardController',
        ]);
    }
}
