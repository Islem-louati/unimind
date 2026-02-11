<?php

namespace App\Controller\Admin;

use App\Entity\Questionnaire;
use App\Entity\Question;
use App\Entity\ReponseQuestionnaire;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function index(EntityManagerInterface $em): Response
    {
        // Récupérer les statistiques
        $questionnairesCount = $em->getRepository(Questionnaire::class)->count([]);
        $questionsCount = $em->getRepository(Question::class)->count([]);
        $reponsesCount = $em->getRepository(ReponseQuestionnaire::class)->count([]);
        
        // Derniers questionnaires
        $derniersQuestionnaires = $em->getRepository(Questionnaire::class)
            ->createQueryBuilder('q')
            ->orderBy('q.created_at', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
        
        // Questionnaires incomplets
        $questionnairesIncomplets = [];
        $allQuestionnaires = $em->getRepository(Questionnaire::class)->findAll();
        foreach ($allQuestionnaires as $questionnaire) {
            $questions = $questionnaire->getQuestions();
            if (count($questions) < $questionnaire->getNbreQuestions()) {
                $questionnairesIncomplets[] = $questionnaire;
            }
        }
        
        return $this->render('admin/dashboard/index.html.twig', [
            'questionnaires_count' => $questionnairesCount,
            'questions_count' => $questionsCount,
            'reponses_count' => $reponsesCount,
            'derniers_questionnaires' => $derniersQuestionnaires,
            'questionnaires_incomplets' => $questionnairesIncomplets,
        ]);
    }
    
    #[Route('/admin/stats', name: 'admin_stats')]
    public function stats(EntityManagerInterface $em): Response
    {
        // Statistiques détaillées
        $reponses = $em->getRepository(ReponseQuestionnaire::class)->findAll();
        
        $statsParType = [];
        $questionnaires = $em->getRepository(Questionnaire::class)->findAll();
        
        foreach ($questionnaires as $questionnaire) {
            $reponsesQuestionnaire = $em->getRepository(ReponseQuestionnaire::class)
                ->findBy(['questionnaire' => $questionnaire]);
            
            if (count($reponsesQuestionnaire) > 0) {
                $scores = array_map(fn($r) => $r->getScore(), $reponsesQuestionnaire);
                $statsParType[$questionnaire->getType()] = [
                    'questionnaire' => $questionnaire,
                    'count' => count($reponsesQuestionnaire),
                    'moyenne' => array_sum($scores) / count($scores),
                    'min' => min($scores),
                    'max' => max($scores),
                ];
            }
        }
        
        return $this->render('admin/dashboard/stats.html.twig', [
            'stats_par_type' => $statsParType,
            'total_reponses' => count($reponses),
        ]);
    }
}