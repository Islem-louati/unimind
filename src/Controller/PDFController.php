<?php

namespace App\Controller;

use App\Entity\Traitement;
use App\Service\PDFService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pdf')]
class PDFController extends AbstractController
{
    private PDFService $pdfService;

    public function __construct(PDFService $pdfService)
    {
        $this->pdfService = $pdfService;
    }

    /**
     * Vérifie que l'utilisateur peut accéder à ce traitement
     */
    private function denyAccessUnlessOwner(Traitement $traitement): void
    {
        $user = $this->getUser();
        
        // Si l'utilisateur est un étudiant, vérifier que le traitement lui appartient
        if ($this->isGranted('ROLE_ETUDIANT')) {
            if ($traitement->getEtudiant()?->getUserId() !== $user->getId()) {
                throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à ce traitement.');
            }
        }
    }

    /**
     * Génère un PDF pour un rapport patient
     */
    #[Route('/patient/{id}', name: 'app_pdf_patient', methods: ['GET'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function generatePatientPDF(Request $request): Response
    {
        $patientId = $request->get('id');
        
        try {
            $pdfContent = $this->pdfService->generatePatientPDF($patientId);
            
            return new Response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="patient_' . $patientId . '.pdf"'
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du PDF : ' . $e->getMessage());
            return $this->redirectToRoute('app_traitement_index');
        }
    }

    /**
     * Génère un PDF pour un rapport statistique
     */
    #[Route('/statistiques/{period}', name: 'app_pdf_statistiques', methods: ['GET'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function generateStatistiquePDF(Request $request): Response
    {
        $period = $request->get('period', 'month');
        
        try {
            $pdfContent = $this->pdfService->generateStatistiquePDF($period);
            
            return new Response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="statistiques_' . $period . '.pdf"'
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du PDF : ' . $e->getMessage());
            return $this->redirectToRoute('app_dashboard');
        }
    }
}
