<?php

namespace App\Controller;

use App\Entity\Traitement;
use App\Service\InteractivePDFService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pdf-interactive')]
class InteractivePDFController extends AbstractController
{
    private InteractivePDFService $pdfService;

    public function __construct(InteractivePDFService $pdfService)
    {
        $this->pdfService = $pdfService;
    }

    /**
     * Génère un PDF interactif avec champs de signature
     */
    #[Route('/traitement/{id}', name: 'app_pdf_interactive_traitement', methods: ['GET'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function generateInteractiveTraitementPDF(Traitement $traitement): Response
    {
        try {
            $pdfContent = $this->pdfService->generateInteractiveTraitementPDF($traitement, false);
            
            return new Response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="traitement_interactif_' . $traitement->getTraitementId() . '.pdf"'
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du PDF interactif : ' . $e->getMessage());
            return $this->redirectToRoute('app_traitement_show', ['id' => $traitement->getTraitementId()]);
        }
    }

    /**
     * Génère un PDF interactif pour l'étudiant
     */
    #[Route('/traitement/{id}/etudiant', name: 'app_pdf_interactive_traitement_etudiant', methods: ['GET'])]
    #[IsGranted('ROLE_ETUDIANT')]
    public function generateInteractiveTraitementPDFEtudiant(Traitement $traitement): Response
    {
        // Vérifier que l'étudiant peut accéder à ce traitement
        if ($traitement->getEtudiant()?->getUserId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à ce traitement.');
        }

        try {
            $pdfContent = $this->pdfService->generateInteractiveTraitementPDF($traitement, true);
            
            return new Response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="mon_traitement_interactif_' . $traitement->getTraitementId() . '.pdf"'
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du PDF interactif : ' . $e->getMessage());
            return $this->redirectToRoute('app_traitement_index');
        }
    }

    /**
     * Télécharge un PDF interactif (pour psychologue)
     */
    #[Route('/traitement/{id}/download', name: 'app_pdf_interactive_traitement_download', methods: ['GET'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function downloadInteractiveTraitementPDF(Traitement $traitement): Response
    {
        try {
            $pdfContent = $this->pdfService->generateInteractiveTraitementPDF($traitement, false);
            
            return new Response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="traitement_interactif_' . $traitement->getTraitementId() . '.pdf"'
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du PDF interactif : ' . $e->getMessage());
            return $this->redirectToRoute('app_traitement_show', ['id' => $traitement->getTraitementId()]);
        }
    }
}
