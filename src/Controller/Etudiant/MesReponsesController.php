<?php
// src/Controller/Etudiant/MesReponsesController.php

namespace App\Controller\Etudiant;

use App\Entity\ReponseQuestionnaire;
use App\Entity\Questionnaire;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/etudiant/mes-reponses')]
class MesReponsesController extends AbstractController
{
    #[Route('', name: 'etudiant_mes_reponses')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $etudiant = $this->getUser();
        
        if (!$etudiant) {
            return $this->render('etudiant/mes_reponses/index.html.twig', [
                'reponses' => [],
                'dashboard_stats' => $this->getEmptyDashboardStats(),
                'filters' => $this->getDefaultFilters($request),
                'types_questionnaire' => [],
                'current_page' => 1,
                'total_pages' => 1,
            ]);
        }
        
        // Récupérer tous les types de questionnaires disponibles
        $typesQuestionnaire = $em->createQuery(
            'SELECT DISTINCT q.type FROM App\Entity\Questionnaire q WHERE q.type IS NOT NULL ORDER BY q.type ASC'
        )->getResult();
        $typesQuestionnaire = array_column($typesQuestionnaire, 'type');
        
        // Récupérer les paramètres de filtrage
        $filters = $this->getDefaultFilters($request);
        
        // Construire et exécuter la requête principale
        $qb = $this->buildQuery($em, $etudiant, $filters);
        $reponses = $qb->getQuery()->getResult();
        
        // Calculer les statistiques du dashboard
        $dashboardStats = $this->calculateDashboardStats($reponses);
        
        // Pagination
        $totalPages = ceil(count($reponses) / $filters['limit']);
        
        return $this->render('etudiant/mes_reponses/index.html.twig', [
            'reponses' => $this->paginateResults($reponses, $filters['page'], $filters['limit']),
            'dashboard_stats' => $dashboardStats,
            'filters' => $filters,
            'types_questionnaire' => $typesQuestionnaire,
            'current_page' => $filters['page'],
            'total_pages' => $totalPages,
            'total_items' => count($reponses),
        ]);
    }
    
    // ... (gardez les autres méthodes existantes : exportCsv, show, delete)
    
    /**
     * Calcule les statistiques du dashboard
     */
    private function calculateDashboardStats(array $reponses): array
    {
        if (empty($reponses)) {
            return $this->getEmptyDashboardStats();
        }
        
        $totalScore = 0;
        $meilleurScore = 0;
        $typesComplets = [];
        $statsParType = [];
        $evolutionScores = [];
        $recentResponses = [];
        
        // Trier par date pour les réponses récentes
        usort($reponses, function($a, $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });
        
        // Prendre les 5 dernières réponses
        $recentResponses = array_slice($reponses, 0, 5);
        
        foreach ($reponses as $reponse) {
            $score = $reponse->getScoreTotal();
            $questionnaire = $reponse->getQuestionnaire();
            $totalScore += $score;
            
            // Meilleur score global
            if ($score > $meilleurScore) {
                $meilleurScore = $score;
            }
            
            if ($questionnaire) {
                $type = $questionnaire->getType();
                $scoreMax = $questionnaire->getScoreMax();
                
                // Pourcentage pour ce questionnaire
                $pourcentage = $scoreMax > 0 ? round(($score / $scoreMax) * 100, 1) : 0;
                
                // Types complets (questionnaires avec score max)
                if ($scoreMax > 0) {
                    if (!isset($typesComplets[$type])) {
                        $typesComplets[$type] = [
                            'count' => 0,
                            'total_score' => 0,
                            'total_max' => 0,
                        ];
                    }
                    $typesComplets[$type]['count']++;
                    $typesComplets[$type]['total_score'] += $score;
                    $typesComplets[$type]['total_max'] += $scoreMax;
                }
                
                // Statistiques par type
                if (!isset($statsParType[$type])) {
                    $statsParType[$type] = [
                        'count' => 0,
                        'scores' => [],
                        'pourcentages' => [],
                    ];
                }
                $statsParType[$type]['count']++;
                $statsParType[$type]['scores'][] = $score;
                $statsParType[$type]['pourcentages'][] = $pourcentage;
                
                // Évolution mensuelle (pour graphique)
                $mois = $reponse->getCreatedAt()->format('Y-m');
                if (!isset($evolutionScores[$mois])) {
                    $evolutionScores[$mois] = [
                        'mois' => $reponse->getCreatedAt()->format('M Y'),
                        'total_score' => 0,
                        'count' => 0,
                    ];
                }
                $evolutionScores[$mois]['total_score'] += $score;
                $evolutionScores[$mois]['count']++;
            }
        }
        
        // Calculer les moyennes par type
        $typesAvecStats = [];
        foreach ($statsParType as $type => $data) {
            $moyenneScore = count($data['scores']) > 0 
                ? round(array_sum($data['scores']) / count($data['scores']), 1) 
                : 0;
            $moyennePourcentage = count($data['pourcentages']) > 0 
                ? round(array_sum($data['pourcentages']) / count($data['pourcentages']), 1) 
                : 0;
            
            $typesAvecStats[] = [
                'type' => $type,
                'count' => $data['count'],
                'score_moyen' => $moyenneScore,
                'pourcentage_moyen' => $moyennePourcentage,
                'niveau' => $this->getInterpretation($moyennePourcentage),
            ];
        }
        
        // Calculer les pourcentages pour les types complets
        $pourcentagesParType = [];
        foreach ($typesComplets as $type => $data) {
            $pourcentageMoyen = $data['total_max'] > 0 
                ? round(($data['total_score'] / $data['total_max']) * 100, 1) 
                : 0;
            $pourcentagesParType[$type] = $pourcentageMoyen;
        }
        
        // Préparer les données d'évolution pour le graphique
        $evolutionData = [];
        ksort($evolutionScores);
        foreach ($evolutionScores as $moisData) {
            $moyenneMois = $moisData['count'] > 0 
                ? round($moisData['total_score'] / $moisData['count'], 1) 
                : 0;
            $evolutionData[] = [
                'mois' => $moisData['mois'],
                'score_moyen' => $moyenneMois,
            ];
        }
        
        // Calculer le score moyen global
        $scoreMoyenGlobal = count($reponses) > 0 ? round($totalScore / count($reponses), 1) : 0;
        
        // Meilleur type (par pourcentage moyen)
        $meilleurType = '';
        $meilleurTypePourcentage = 0;
        if (!empty($pourcentagesParType)) {
            arsort($pourcentagesParType);
            $meilleurType = array_key_first($pourcentagesParType);
            $meilleurTypePourcentage = $pourcentagesParType[$meilleurType];
        }
        
        return [
            'total_reponses' => count($reponses),
            'score_moyen_global' => $scoreMoyenGlobal,
            'meilleur_score' => $meilleurScore,
            'types_differents' => count($statsParType),
            'meilleur_type' => $meilleurType,
            'meilleur_type_pourcentage' => $meilleurTypePourcentage,
            'reponses_recentes' => $recentResponses,
            'types_avec_stats' => $typesAvecStats,
            'evolution_scores' => $evolutionData,
            'tendance' => $this->calculateTendance($evolutionData),
            'niveau_global' => $this->getInterpretation($scoreMoyenGlobal),
        ];
    }
    
    /**
     * Calcule la tendance (amélioration/stagnation/dégradation)
     */
    private function calculateTendance(array $evolutionData): string
    {
        if (count($evolutionData) < 2) {
            return 'stable';
        }
        
        $recent = array_slice($evolutionData, -3); // 3 derniers mois
        if (count($recent) < 2) {
            return 'stable';
        }
        
        $premier = $recent[0]['score_moyen'];
        $dernier = end($recent)['score_moyen'];
        
        if ($dernier > $premier + 5) {
            return 'amélioration';
        } elseif ($dernier < $premier - 5) {
            return 'dégradation';
        } else {
            return 'stable';
        }
    }
    
    private function getEmptyDashboardStats(): array
    {
        return [
            'total_reponses' => 0,
            'score_moyen_global' => 0,
            'meilleur_score' => 0,
            'types_differents' => 0,
            'meilleur_type' => '',
            'meilleur_type_pourcentage' => 0,
            'reponses_recentes' => [],
            'types_avec_stats' => [],
            'evolution_scores' => [],
            'tendance' => 'stable',
            'niveau_global' => 'Faible',
        ];
    }
    
    private function getDefaultFilters(Request $request): array
    {
        return [
            'type' => $request->query->get('type'),
            'date_from' => $request->query->get('date_from'),
            'date_to' => $request->query->get('date_to'),
            'score_min' => $request->query->get('score_min'),
            'score_max' => $request->query->get('score_max'),
            'search' => $request->query->get('search'),
            'sort' => $request->query->get('sort', 'created_at_desc'),
            'page' => $request->query->getInt('page', 1),
            'limit' => 10,
        ];
    }
    
    private function buildQuery(EntityManagerInterface $em, $etudiant, array $filters)
    {
        $qb = $em->getRepository(ReponseQuestionnaire::class)
            ->createQueryBuilder('r')
            ->leftJoin('r.questionnaire', 'q')
            ->where('r.etudiant = :etudiant')
            ->setParameter('etudiant', $etudiant);
        
        // Appliquer les filtres (identique à votre code existant)
        if ($filters['type']) {
            $qb->andWhere('q.type = :type')->setParameter('type', $filters['type']);
        }
        if ($filters['date_from']) {
            $qb->andWhere('r.created_at >= :date_from')
               ->setParameter('date_from', new \DateTime($filters['date_from']));
        }
        if ($filters['date_to']) {
            $qb->andWhere('r.created_at <= :date_to')
               ->setParameter('date_to', new \DateTime($filters['date_to'] . ' 23:59:59'));
        }
        if ($filters['score_min']) {
            $qb->andWhere('r.score_total >= :score_min')
               ->setParameter('score_min', (float) $filters['score_min']);
        }
        if ($filters['score_max']) {
            $qb->andWhere('r.score_total <= :score_max')
               ->setParameter('score_max', (float) $filters['score_max']);
        }
        if ($filters['search']) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('q.titre', ':search'),
                $qb->expr()->like('q.description', ':search')
            ))->setParameter('search', '%' . $filters['search'] . '%');
        }
        
        // Tri
        switch ($filters['sort']) {
            case 'created_at_asc': $qb->orderBy('r.created_at', 'ASC'); break;
            case 'score_desc': $qb->orderBy('r.score_total', 'DESC'); break;
            case 'score_asc': $qb->orderBy('r.score_total', 'ASC'); break;
            default: $qb->orderBy('r.created_at', 'DESC'); break;
        }
        
        return $qb;
    }
    
    private function paginateResults(array $results, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        return array_slice($results, $offset, $limit);
    }
    
    private function getInterpretation(float $pourcentage): string
    {
        if ($pourcentage < 33) return 'Faible';
        if ($pourcentage < 66) return 'Modéré';
        return 'Élevé';
    }
}