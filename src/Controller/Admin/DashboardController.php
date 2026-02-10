<?php

namespace App\Controller\Admin;

use App\Repository\CategorieMeditationRepository;
use App\Repository\SeanceMeditationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'admin_dashboard')]
    public function index(
        CategorieMeditationRepository $categorieRepository,
        SeanceMeditationRepository $seanceRepository
    ): Response {
        // Statistiques principales
        $totalCategories = $categorieRepository->count([]);
        $totalSeances = $seanceRepository->count([]);
        
        // Séances actives
        $seancesActives = $seanceRepository->count(['is_active' => true]);
        $pourcentageActives = $totalSeances > 0 ? round(($seancesActives / $totalSeances) * 100, 1) : 0;
        
        // Durée totale
        $dureeTotale = $seanceRepository->getDureeTotale();
        $dureeMoyenne = $totalSeances > 0 ? round($dureeTotale / $totalSeances / 60, 1) : 0;
        
        // Formatage de la durée totale
        $heures = floor($dureeTotale / 3600);
        $minutes = floor(($dureeTotale % 3600) / 60);
        $secondes = $dureeTotale % 60;
        
        $dureeTotaleFormatee = '';
        if ($heures > 0) {
            $dureeTotaleFormatee .= $heures . 'h ';
        }
        if ($minutes > 0 || $heures > 0) {
            $dureeTotaleFormatee .= $minutes . 'min ';
        }
        $dureeTotaleFormatee .= $secondes . 's';
        
        // Statistiques par catégorie
        $categoriesAvecStats = $categorieRepository->findAllWithStats();
        foreach ($categoriesAvecStats as &$item) {
    $categorie = $item['categorie'];
    $nbSeances = $item['nbSeances'];
    $dureeTotale = $item['dureeTotale'];
    
    // Ajoutez les propriétés directement à l'objet categorie
    $categorie->nbSeances = $nbSeances;
    $categorie->dureeTotale = $dureeTotale;
    $categorie->pourcentage = $totalSeances > 0 ? round(($nbSeances / $totalSeances) * 100, 1) : 0;
    
    // Formatage durée
    $heuresCat = floor($dureeTotale / 3600);
    $minutesCat = floor(($dureeTotale % 3600) / 60);
    $categorie->dureeTotaleFormatee = '';
    if ($heuresCat > 0) {
        $categorie->dureeTotaleFormatee .= $heuresCat . 'h ';
    }
    $categorie->dureeTotaleFormatee .= $minutesCat . 'min';
    
    // Remplacez l'élément par l'objet categorie enrichi
    $item = $categorie;
}
        
        // Statistiques par type
        $statsType = [
            'audio' => $seanceRepository->count(['typeFichier' => 'audio']),
            'video' => $seanceRepository->count(['typeFichier' => 'video']),
        ];
        $statsType['pourcentage_audio'] = $totalSeances > 0 ? round(($statsType['audio'] / $totalSeances) * 100, 1) : 0;
        $statsType['pourcentage_video'] = $totalSeances > 0 ? round(($statsType['video'] / $totalSeances) * 100, 1) : 0;
        
        // Statistiques par niveau
        $statsNiveau = [
            'debutant' => $seanceRepository->count(['niveau' => 'débutant']),
            'intermediaire' => $seanceRepository->count(['niveau' => 'intermédiaire']),
            'avance' => $seanceRepository->count(['niveau' => 'avancé']),
        ];
        
        $totalParNiveau = array_sum($statsNiveau);
        foreach ($statsNiveau as $key => $value) {
            $statsNiveau['pourcentage_' . $key] = $totalParNiveau > 0 ? round(($value / $totalParNiveau) * 100, 1) : 0;
        }
        
        // Évolution sur 6 mois
        $evolution = $seanceRepository->getEvolution6Mois();
        $evolutionLabels = [];
        $evolutionData = [];
        
        foreach ($evolution as $mois) {
            $evolutionLabels[] = $mois['mois'];
            $evolutionData[] = $mois['nbSeances'];
        }
        
        // Top 5 catégories
        $topCategories = array_slice($categoriesAvecStats, 0, 5);
        $topCategoriesLabels = array_column($topCategories, 'nom');
        $topCategoriesData = array_column($topCategories, 'nbSeances');
        
        // Dernières séances
        $dernieresSeances = $seanceRepository->findBy([], ['created_at' => 'DESC'], 10);
        
        // Statistiques du mois
        $dateDebutMois = new \DateTime('first day of this month');
        $dateFinMois = new \DateTime('last day of this month');
        
        $categoriesCeMois = $categorieRepository->createQueryBuilder('c')
            ->select('COUNT(c)')
            ->where('c.dateCreation BETWEEN :debut AND :fin')
            ->setParameter('debut', $dateDebutMois)
            ->setParameter('fin', $dateFinMois)
            ->getQuery()
            ->getSingleScalarResult();
            
        $seancesCeMois = $seanceRepository->createQueryBuilder('s')
            ->select('COUNT(s)')
            ->where('s.created_at BETWEEN :debut AND :fin')
            ->setParameter('debut', $dateDebutMois)
            ->setParameter('fin', $dateFinMois)
            ->getQuery()
            ->getSingleScalarResult();
        
        return $this->render('admin/dashboard/index.html.twig', [
            'total_categories' => $totalCategories,
            'total_seances' => $totalSeances,
            'seances_actives' => $seancesActives,
            'pourcentage_actives' => $pourcentageActives,
            'duree_totale_formatee' => $dureeTotaleFormatee,
            'duree_moyenne' => $dureeMoyenne,
            'categories_avec_stats' => $categoriesAvecStats,
            'stats_type' => $statsType,
            'stats_niveau' => $statsNiveau,
            'evolution_labels' => $evolutionLabels,
            'evolution_data' => $evolutionData,
            'top_categories_labels' => $topCategoriesLabels,
            'top_categories_data' => $topCategoriesData,
            'dernieres_seances' => $dernieresSeances,
            'categories_ce_mois' => $categoriesCeMois,
            'seances_ce_mois' => $seancesCeMois,
        ]);
    }
}