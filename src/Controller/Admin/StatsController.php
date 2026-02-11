<?php
// src/Controller/Admin/StatsController.php

namespace App\Controller\Admin;

use App\Entity\Questionnaire;
use App\Entity\Question;
use App\Entity\ReponseQuestionnaire;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class StatsController extends AbstractController
{
    #[Route('/statistiques', name: 'admin_statistiques')]
    public function index(EntityManagerInterface $em): Response
    {
        // === COMPTEURS PRINCIPAUX ===
        // Nombre total de questionnaires
        $questionnairesCount = $em->getRepository(Questionnaire::class)->count([]);
        
        // Nombre total de questions
        $questionsCount = $em->getRepository(Question::class)->count([]);
        
        // Nombre total de réponses
        $reponsesCount = $em->getRepository(ReponseQuestionnaire::class)->count([]);
        
        // === QUESTIONNAIRES INCOMPLETS ===
        $questionnairesIncomplets = $em->createQuery(
            'SELECT q FROM App\Entity\Questionnaire q 
             WHERE q.nbre_questions = 0 OR 
                   SIZE(q.questions) < q.nbre_questions'
        )->getResult();
        
        // === STATISTIQUES PAR TYPE ===
        $types = ['STRESS', 'ANXIETE', 'DEPRESSION', 'BIEN-ETRE'];
        $statsParType = [];
        
        foreach ($types as $type) {
            // Questionnaires de ce type
            $questionnairesType = $em->getRepository(Questionnaire::class)
                ->findBy(['type' => $type]);
            
            $totalReponsesType = 0;
            $questionnairesDetails = [];
            $scoresTotaux = [];
            
            foreach ($questionnairesType as $questionnaire) {
                // Réponses pour ce questionnaire
                $reponses = $em->getRepository(ReponseQuestionnaire::class)
                    ->findBy(['questionnaire' => $questionnaire]);
                
                $countReponses = count($reponses);
                $totalReponsesType += $countReponses;
                
                if ($countReponses > 0) {
                    // Calculer les scores
                    $scores = array_map(function($reponse) {
                        return $reponse->getScoreTotal() ?? 0;
                    }, $reponses);
                    
                    $questionnairesDetails[] = [
                        'questionnaire' => $questionnaire,
                        'count' => $countReponses,
                        'moyenne' => $countReponses > 0 ? round(array_sum($scores) / $countReponses, 2) : 0,
                        'min' => $countReponses > 0 ? min($scores) : 0,
                        'max' => $countReponses > 0 ? max($scores) : 0
                    ];
                    
                    $scoresTotaux = array_merge($scoresTotaux, $scores);
                }
            }
            
            // Calculer les statistiques globales pour ce type
            if ($totalReponsesType > 0) {
                $statsParType[strtolower($type)] = [
                    'total_reponses' => $totalReponsesType,
                    'questionnaires' => $questionnairesDetails,
                    'score_moyen_global' => round(array_sum($scoresTotaux) / count($scoresTotaux), 2),
                    'score_min_global' => min($scoresTotaux),
                    'score_max_global' => max($scoresTotaux)
                ];
            }
        }
        
        // === DERNIERS QUESTIONNAIRES ===
        $derniersQuestionnaires = $em->getRepository(Questionnaire::class)
            ->createQueryBuilder('q')
            ->orderBy('q.created_at', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        
        // === DONNÉES POUR GRAPHIQUES ===
        // Données factices pour les graphiques (à remplacer par vos vraies données)
        $evolutionData = [12, 19, 15, 25, 22, 30, 35];
        $scoresMoyens = [];
        $repartitionTypes = [];
        
        foreach ($types as $type) {
            $typeLower = strtolower($type);
            if (isset($statsParType[$typeLower])) {
                $scoresMoyens[] = $statsParType[$typeLower]['score_moyen_global'];
                $repartitionTypes[] = $statsParType[$typeLower]['total_reponses'];
            } else {
                $scoresMoyens[] = 0;
                $repartitionTypes[] = 0;
            }
        }
        
        return $this->render('admin/dashboard/stats.html.twig', [
            // Compteurs principaux
            'questionnaires_count' => $questionnairesCount,
            'questions_count' => $questionsCount,
            'reponses_count' => $reponsesCount,
            'questionnaires_incomplets' => $questionnairesIncomplets,
            
            // Statistiques détaillées
            'stats_par_type' => $statsParType,
            'derniers_questionnaires' => $derniersQuestionnaires,
            
            // Données pour graphiques
            'evolution_data' => $evolutionData,
            'scores_moyens' => $scoresMoyens,
            'repartition_types' => $repartitionTypes,
            'types_labels' => array_map('strtolower', $types),
            
            // Autres
            'taux_completion' => $questionnairesCount > 0 ? 
                round((($questionnairesCount - count($questionnairesIncomplets)) / $questionnairesCount) * 100) : 0,
        ]);
    }
    
    #[Route('/statistiques/export', name: 'admin_statistiques_export')]
    public function export(EntityManagerInterface $em): Response
    {
        // Logique d'export des statistiques (CSV, Excel, etc.)
        // À implémenter selon vos besoins
        
        return $this->redirectToRoute('admin_statistiques');
    }
}