<?php
// src/Controller/Dashboard/ResponsableEtudiantController.php

namespace App\Controller\Dashboard;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/responsable')]
#[IsGranted('ROLE_RESPONSABLE_ETUDIANT')]
class ResponsableEtudiantController extends AbstractController
{
    #[Route('', name: 'app_dashboard_responsable')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('Dashboard/responsable/index.html.twig', [
            'user' => $user,
        ]);
    }
}
