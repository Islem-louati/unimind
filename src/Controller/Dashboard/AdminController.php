<?php
// src/Controller/Dashboard/AdminController.php

namespace App\Controller\Dashboard;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_dashboard_admin')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('Dashboard/admin/index.html.twig', [
            'user' => $user,
        ]);
    }
}
