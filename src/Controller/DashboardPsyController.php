<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardPsyController extends AbstractController
{
    #[Route('/dashboard/psy', name: 'app_dashboard_psy')]
    public function index(): Response
    {
        return $this->render('dashboard/dashboardPsy.html.twig', [
            'controller_name' => 'DashboardPsyController',
        ]);
    }
}
