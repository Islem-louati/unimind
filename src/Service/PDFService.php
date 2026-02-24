<?php

namespace App\Service;

use App\Entity\Traitement;
use App\Entity\SuiviTraitement;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class PDFService
{
    private $twig;
    private $entityManager;

    public function __construct(Environment $twig, EntityManagerInterface $entityManager)
    {
        $this->twig = $twig;
        $this->entityManager = $entityManager;
    }

    /**
     * Génère un PDF pour une fiche traitement
     */
    public function generateTraitementPDF(Traitement $traitement, $isEtudiant = false): string
    {
        // Récupérer les suivis du traitement
        $suivis = $this->entityManager
            ->getRepository(SuiviTraitement::class)
            ->findBy(['traitement' => $traitement], ['dateSuivi' => 'ASC']);

        // Configuration DomPDF
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);

        // Choisir le template selon le rôle
        $template = $isEtudiant ? 'pdf/traitement_etudiant.html.twig' : 'pdf/traitement.html.twig';

        // Génération du HTML
        $html = $this->twig->render($template, [
            'traitement' => $traitement,
            'suivis' => $suivis,
            'dateGeneration' => new \DateTime(),
            'isEtudiant' => $isEtudiant
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Génère un PDF pour un rapport patient
     */
    public function generatePatientPDF($patientId): string
    {
        // Récupérer tous les traitements et suivis du patient
        $traitements = $this->entityManager
            ->getRepository(Traitement::class)
            ->findBy(['etudiant' => $patientId], ['date_debut' => 'DESC']);

        $allSuivis = [];
        foreach ($traitements as $traitement) {
            $suivis = $this->entityManager
                ->getRepository(SuiviTraitement::class)
                ->findBy(['traitement' => $traitement], ['dateSuivi' => 'ASC']);
            $allSuivis = array_merge($allSuivis, $suivis);
        }

        // Configuration DomPDF
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);

        // Génération du HTML
        $html = $this->twig->render('pdf/patient.html.twig', [
            'traitements' => $traitements,
            'suivis' => $allSuivis,
            'dateGeneration' => new \DateTime()
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Génère un PDF pour un rapport statistique
     */
    public function generateStatistiquePDF($period = 'month'): string
    {
        // Logique de récupération des statistiques
        $stats = $this->getStatistiques($period);

        // Configuration DomPDF
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);

        // Génération du HTML
        $html = $this->twig->render('pdf/statistique.html.twig', [
            'stats' => $stats,
            'period' => $period,
            'dateGeneration' => new \DateTime()
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function getStatistiques($period): array
    {
        // Logique de récupération des statistiques
        $qbTraitements = $this->entityManager->createQueryBuilder();
        $qbSuivis = $this->entityManager->createQueryBuilder();
        
        switch ($period) {
            case 'month':
                $dateStart = new \DateTime('first day of this month');
                break;
            case 'year':
                $dateStart = new \DateTime('first day of January this year');
                break;
            default:
                $dateStart = new \DateTime('first day of this month');
        }

        // Requête pour les traitements
        $qbTraitements->select('COUNT(t) as totalTraitements')
           ->from('App\Entity\Traitement', 't')
           ->where('t.date_debut >= :dateStart')
           ->setParameter('dateStart', $dateStart);

        $totalTraitements = $qbTraitements->getQuery()->getSingleScalarResult();

        // Requête pour les suivis
        $qbSuivis->select('COUNT(s) as totalSuivis')
           ->from('App\Entity\SuiviTraitement', 's')
           ->where('s.dateSuivi >= :dateStart')
           ->setParameter('dateStart', $dateStart);

        $totalSuivis = $qbSuivis->getQuery()->getSingleScalarResult();

        return [
            'totalTraitements' => (int)$totalTraitements,
            'totalSuivis' => (int)$totalSuivis,
            'period' => $period
        ];
    }
}
