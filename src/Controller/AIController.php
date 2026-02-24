<?php

namespace App\Controller;

use App\Entity\SuiviTraitement;
use App\Service\SimpleAIService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/ai')]
class AIController extends AbstractController
{
    private SimpleAIService $aiService;

    public function __construct(SimpleAIService $aiService)
    {
        $this->aiService = $aiService;
    }

    #[Route('/analyze-suivi/{id}', name: 'ai_analyze_suivi', methods: ['GET', 'POST'])]
    public function analyzeSuivi(SuiviTraitement $suivi, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifier les droits d'accès
        if (!$this->canAccessSuivi($suivi, $user)) {
            throw $this->createAccessDeniedException('Accès refusé');
        }

        $analysis = null;
        $error = null;

        if ($request->isMethod('POST')) {
            $observations = $suivi->getObservations() ?: $suivi->getObservationsPsy();
            
            if (empty($observations)) {
                $error = 'Aucune observation à analyser.';
            } else {
                try {
                    $analysis = $this->aiService->analyzeObservations($observations);
                } catch (\Exception $e) {
                    $error = 'Erreur lors de l\'analyse: ' . $e->getMessage();
                }
            }
        }

        return $this->render('ai/analyze_suivi.html.twig', [
            'suivi' => $suivi,
            'analysis' => $analysis,
            'error' => $error
        ]);
    }

    #[Route('/treatment-advice', name: 'ai_treatment_advice', methods: ['GET', 'POST'])]
    public function treatmentAdvice(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $advice = null;
        $error = null;

        if ($request->isMethod('POST')) {
            $traitementType = $request->request->get('traitement_type');
            $patientProfile = $request->request->get('patient_profile');

            if (empty($traitementType) || empty($patientProfile)) {
                $error = 'Veuillez remplir tous les champs.';
            } else {
                try {
                    $advice = $this->aiService->generateTreatmentAdvice($traitementType, $patientProfile);
                } catch (\Exception $e) {
                    $error = 'Erreur lors de la génération du conseil: ' . $e->getMessage();
                }
            }
        }

        return $this->render('ai/treatment_advice.html.twig', [
            'advice' => $advice,
            'error' => $error
        ]);
    }

    private function canAccessSuivi(SuiviTraitement $suivi, $user): bool
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_RESPONSABLE_ETUDIANT')) {
            return true;
        }

        if ($this->isGranted('ROLE_PSYCHOLOGUE') && $suivi->getTraitement()->getPsychologue() === $user) {
            return true;
        }

        if ($this->isGranted('ROLE_ETUDIANT') && $suivi->getTraitement()->getEtudiant() === $user) {
            return true;
        }

        return false;
    }
}
