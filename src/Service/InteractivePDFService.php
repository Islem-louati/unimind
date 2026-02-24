<?php

namespace App\Service;

use App\Entity\Traitement;
use App\Entity\SuiviTraitement;
use Doctrine\ORM\EntityManagerInterface;
use TCPDF;
use TCPDF_PARSER;
use TCPDF_STATIC;
use Twig\Environment;

class InteractivePDFService
{
    private $twig;
    private $entityManager;

    public function __construct(Environment $twig, EntityManagerInterface $entityManager)
    {
        $this->twig = $twig;
        $this->entityManager = $entityManager;
    }

    /**
     * Génère un PDF interactif avec champs de signature
     */
    public function generateInteractiveTraitementPDF(Traitement $traitement, $isEtudiant = false): string
    {
        // Récupérer les suivis du traitement
        $suivis = $this->entityManager
            ->getRepository(SuiviTraitement::class)
            ->findBy(['traitement' => $traitement], ['dateSuivi' => 'ASC']);

        // Créer un nouveau PDF TCPDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Métadonnées
        $pdf->SetCreator('UniMind - Suivi Psychologique');
        $pdf->SetAuthor($isEtudiant ? $traitement->getEtudiant()->getFullName() : $traitement->getPsychologue()->getFullName());
        $pdf->SetTitle('Fiche Traitement - ' . $traitement->getTitre());
        $pdf->SetSubject('Traitement Psychologique');

        // Configuration des marges et polices
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $pdf->SetFont('helvetica', '', 10);

        // Ajouter une page
        $pdf->AddPage();

        // Contenu du PDF
        $this->addHeader($pdf, $traitement, $isEtudiant);
        $this->addTraitementInfo($pdf, $traitement);
        $this->addSuivisTable($pdf, $suivis);
        $this->addSignatureFields($pdf, $traitement, $isEtudiant);

        return $pdf->Output('', 'S');
    }

    /**
     * Ajoute l'en-tête du PDF
     */
    private function addHeader(TCPDF $pdf, Traitement $traitement, bool $isEtudiant): void
    {
        $title = $isEtudiant ? 'MON TRAITEMENT' : 'FICHE TRAITEMENT';
        $subtitle = $traitement->getTitre();
        
        // Style de l'en-tête
        $pdf->SetFillColor($isEtudiant ? 40 : 0, $isEtudiant ? 167 : 123, 69);
        $pdf->SetTextColor(255);
        $pdf->SetFont('helvetica', 'B', 24);
        
        // Rectangle en-tête
        $pdf->Rect(10, 10, 190, 40, 'F');
        
        // Titre
        $pdf->SetXY(10, 20);
        $pdf->Cell(190, 10, $title, 0, 1, 'C', false);
        
        // Sous-titre
        $pdf->SetFont('helvetica', '', 14);
        $pdf->SetXY(10, 35);
        $pdf->Cell(190, 10, $subtitle, 0, 1, 'C', false);
        
        // Réinitialiser les couleurs
        $pdf->SetTextColor(0);
        $pdf->SetY(60);
    }

    /**
     * Ajoute les informations du traitement
     */
    private function addTraitementInfo(TCPDF $pdf, Traitement $traitement): void
    {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Informations du Traitement', 0, 1, 'L');
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', '', 11);
        
        // Informations dans un tableau
        $info = [
            'Patient' => $traitement->getEtudiant()->getFullName(),
            'Psychologue' => $traitement->getPsychologue()->getFullName(),
            'Date début' => $traitement->getDateDebut()->format('d/m/Y'),
            'Date fin' => $traitement->getDateFin() ? $traitement->getDateFin()->format('d/m/Y') : 'Non définie',
            'Statut' => $traitement->getStatut(),
            'Type' => $traitement->getType(),
            'Catégorie' => $traitement->getCategorie(),
        ];

        foreach ($info as $label => $value) {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(40, 8, $label . ':', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell(150, 8, $value, 0, 1, 'L');
        }

        $pdf->Ln(5);

        // Objectif thérapeutique
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Objectif Thérapeutique:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 11);
        $pdf->MultiCell(0, 8, $traitement->getObjectifTherapeutique() ?: 'Non défini', 0, 'L');
        
        $pdf->Ln(10);
    }

    /**
     * Ajoute le tableau des suivis
     */
    private function addSuivisTable(TCPDF $pdf, array $suivis): void
    {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Historique des Suivis', 0, 1, 'L');
        $pdf->Ln(5);

        if (empty($suivis)) {
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell(0, 8, 'Aucun suivi enregistré', 0, 1, 'L');
            $pdf->Ln(10);
            return;
        }

        // En-têtes du tableau
        $headers = ['Date', 'Type', 'Notes', 'Progression'];
        $widths = [30, 30, 100, 30];
        
        $pdf->SetFillColor(200, 200, 200);
        $pdf->SetFont('helvetica', 'B', 10);
        
        foreach ($headers as $i => $header) {
            $pdf->Cell($widths[$i], 8, $header, 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Données du tableau
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('helvetica', '', 9);
        
        foreach ($suivis as $i => $suivi) {
            $fill = $i % 2 == 0;
            
            $pdf->Cell($widths[0], 8, $suivi->getDateSuivi()->format('d/m/Y'), 1, 0, 'C', $fill);
            $pdf->Cell($widths[1], 8, $this->getSuiviType($suivi), 1, 0, 'C', $fill);
            $pdf->Cell($widths[2], 8, substr($this->getSuiviNotes($suivi), 0, 60), 1, 0, 'L', $fill);
            $pdf->Cell($widths[3], 8, $this->getSuiviProgression($suivi) . '%', 1, 1, 'C', $fill);
        }
        
        $pdf->Ln(10);
    }

    /**
     * Ajoute les champs de signature
     */
    private function addSignatureFields(TCPDF $pdf, Traitement $traitement, bool $isEtudiant): void
    {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Validation et Signatures', 0, 1, 'L');
        $pdf->Ln(5);

        // Champ de signature pour le patient
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Signature du Patient:', 0, 1, 'L');
        
        // Ligne de signature
        $pdf->Line(20, $pdf->GetY() + 15, 90, $pdf->GetY() + 15);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(70, 8, $traitement->getEtudiant()->getFullName(), 0, 0, 'C');
        $pdf->Cell(20, 8, 'Date:', 0, 0, 'R');
        
        // Champ de date
        $pdf->Rect(110, $pdf->GetY() + 10, 30, 8);
        $pdf->Cell(30, 8, '', 0, 1, 'L');
        
        $pdf->Ln(15);

        // Champ de signature pour le psychologue
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Signature du Psychologue:', 0, 1, 'L');
        
        // Ligne de signature
        $pdf->Line(20, $pdf->GetY() + 15, 90, $pdf->GetY() + 15);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(70, 8, $traitement->getPsychologue()->getFullName(), 0, 0, 'C');
        $pdf->Cell(20, 8, 'Date:', 0, 0, 'R');
        
        // Champ de date
        $pdf->Rect(110, $pdf->GetY() + 10, 30, 8);
        $pdf->Cell(30, 8, '', 0, 1, 'L');
        
        $pdf->Ln(15);

        // Section de validation
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Validation du Traitement:', 0, 1, 'L');
        
        // Cases à cocher pour validation
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(10, 8, '', 1, 0, 'C');
        $pdf->Cell(80, 8, ' Traitement terminé avec succès', 0, 0, 'L');
        $pdf->Cell(10, 8, '', 1, 0, 'C');
        $pdf->Cell(80, 8, ' Traitement en cours', 0, 1, 'L');
        
        $pdf->Cell(10, 8, '', 1, 0, 'C');
        $pdf->Cell(80, 8, ' Objectifs atteints', 0, 0, 'L');
        $pdf->Cell(10, 8, '', 1, 0, 'C');
        $pdf->Cell(80, 8, ' Objectifs partiels', 0, 1, 'L');
        
        $pdf->Ln(10);

        // Champ d'observations
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Observations:', 0, 1, 'L');
        
        // Zone de texte pour observations
        $pdf->Rect(20, $pdf->GetY(), 170, 40);
        $pdf->Ln(45);
        
        // Pied de page
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 5, 'Document généré le ' . date('d/m/Y H:i:s') . ' par UniMind - Suivi Psychologique', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Ce document est confidentiel et ne peut être utilisé que par les personnes autorisées', 0, 1, 'C');
    }

    /**
     * Génère un PDF avec formulaire interactif pour signature numérique
     */
    public function generateSignableTraitementPDF(Traitement $traitement): string
    {
        // Créer un nouveau PDF avec champs de formulaire
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Métadonnées
        $pdf->SetCreator('UniMind - Suivi Psychologique');
        $pdf->SetAuthor($traitement->getPsychologue()->getFullName());
        $pdf->SetTitle('Fiche Traitement Signable - ' . $traitement->getTitre());

        $pdf->AddPage();

        // Ajouter le contenu
        $this->addHeader($pdf, $traitement, false);
        $this->addTraitementInfo($pdf, $traitement);
        $this->addSuivisTable($pdf, $this->getSuivis($traitement));
        $this->addSimpleSignatureFields($pdf, $traitement);

        return $pdf->Output('', 'S');
    }

    /**
     * Ajoute des champs de signature simples (sans formulaire interactif)
     */
    private function addSimpleSignatureFields(TCPDF $pdf, Traitement $traitement): void
    {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Signature Électronique', 0, 1, 'L');
        $pdf->Ln(5);

        // Champ de signature pour le patient
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(80, 15, '', 1, 0, 'L');
        $pdf->Cell(30, 8, 'Patient:', 0, 0, 'L');
        $pdf->Cell(80, 8, $traitement->getEtudiant()->getFullName(), 0, 1, 'L');

        // Champ de date pour le patient
        $pdf->Cell(30, 8, 'Date:', 0, 0, 'L');
        $pdf->Cell(80, 8, '', 1, 0, 'L');
        $pdf->Ln(10);

        // Champ de signature pour le psychologue
        $pdf->Cell(80, 15, '', 1, 0, 'L');
        $pdf->Cell(30, 8, 'Psychologue:', 0, 0, 'L');
        $pdf->Cell(80, 8, $traitement->getPsychologue()->getFullName(), 0, 1, 'L');

        // Champ de date pour le psychologue
        $pdf->Cell(30, 8, 'Date:', 0, 0, 'L');
        $pdf->Cell(80, 8, '', 1, 0, 'L');
        $pdf->Ln(10);

        // Cases à cocher (dessinées manuellement)
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Validation:', 0, 1, 'L');

        // Dessiner les cases à cocher manuellement
        $pdf->Rect(20, $pdf->GetY(), 5, 5);
        $pdf->Cell(5, 8, '', 0, 0, 'L');
        $pdf->Cell(80, 8, 'Traitement terminé', 0, 1, 'L');

        $pdf->Rect(20, $pdf->GetY(), 5, 5);
        $pdf->Cell(5, 8, '', 0, 0, 'L');
        $pdf->Cell(80, 8, 'Objectifs atteints', 0, 1, 'L');

        $pdf->Rect(20, $pdf->GetY(), 5, 5);
        $pdf->Cell(5, 8, '', 0, 0, 'L');
        $pdf->Cell(80, 8, 'Suivi satisfaisant', 0, 1, 'L');

        // Zone de texte pour observations
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Observations:', 0, 1, 'L');
        
        $pdf->Rect(20, $pdf->GetY(), 170, 40);
        $pdf->Ln(45);
    }

    private function getSuivis(Traitement $traitement): array
    {
        return $this->entityManager
            ->getRepository(SuiviTraitement::class)
            ->findBy(['traitement' => $traitement], ['dateSuivi' => 'ASC']);
    }

    /**
     * Récupère le type de suivi selon le statut
     */
    private function getSuiviType(SuiviTraitement $suivi): string
    {
        if ($suivi->isValide()) {
            return 'Validé';
        } elseif ($suivi->isEffectue()) {
            return 'Effectué';
        } elseif ($suivi->isEnRetard()) {
            return 'En retard';
        } elseif ($suivi->isAujourdhui()) {
            return "Aujourd'hui";
        } elseif ($suivi->isAVenir()) {
            return 'À venir';
        }
        
        return 'Standard';
    }

    /**
     * Récupère les notes combinées du suivi
     */
    private function getSuiviNotes(SuiviTraitement $suivi): string
    {
        $notes = [];
        
        if ($suivi->getObservations()) {
            $notes[] = 'Patient: ' . substr($suivi->getObservations(), 0, 30);
        }
        
        if ($suivi->getObservationsPsy()) {
            $notes[] = 'Psy: ' . substr($suivi->getObservationsPsy(), 0, 30);
        }
        
        if ($suivi->getRessenti()) {
            $notes[] = 'Ressenti: ' . $suivi->getRessenti();
        }
        
        return implode(' | ', $notes) ?: 'Aucune note';
    }

    /**
     * Calcule la progression du suivi
     */
    private function getSuiviProgression(SuiviTraitement $suivi): int
    {
        // Utiliser l'évaluation si disponible
        if ($suivi->getEvaluation()) {
            return $suivi->getEvaluation() * 10; // Convertir 1-10 en 10-100%
        }
        
        // Sinon baser sur le statut
        if ($suivi->isValide()) {
            return 100;
        } elseif ($suivi->isEffectue()) {
            return 80;
        } elseif ($suivi->isEnRetard()) {
            return 20;
        }
        
        return 50; // Progression par défaut
    }
}
