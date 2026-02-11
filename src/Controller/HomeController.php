<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_questionnaire_index');
        } elseif ($this->isGranted('ROLE_ETUDIANT')) {
            return $this->redirectToRoute('etudiant_questionnaire_liste');
        }
        
        // Page publique
        return $this->render('home/index.html.twig');
    }
}