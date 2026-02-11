<?php
// src/Controller/Etudiant/DashboardController.php

namespace App\Controller\Etudiant;

use App\Entity\Questionnaire;
use App\Entity\ReponseQuestionnaire;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/etudiant')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'etudiant_dashboard')]
    public function index(EntityManagerInterface $em): Response
    {
        $etudiant = $this->getUser();
        
        // ✅ CORRECTION : Utiliser 'nbre_questions'
        $totalQuestionnaires = $em->getRepository(Questionnaire::class)
            ->createQueryBuilder('q')
            ->select('COUNT(q)')
            ->where('q.nbre_questions > 0')
            ->getQuery()
            ->getSingleScalarResult();
        
        // ... reste du code inchangé ...
        
        return $this->render('etudiant/dashboard/index.html.twig', [
            'total_questionnaires' => $totalQuestionnaires,
            // ... autres variables
        ]);
    }
}