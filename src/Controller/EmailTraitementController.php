<?php

namespace App\Controller;

use App\Entity\Traitement;
use App\Entity\SuiviTraitement;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Security;

#[Route('/email/traitement')]
class EmailTraitementController extends AbstractController
{
    private EmailService $emailService;
    private EntityManagerInterface $entityManager;
    private Security $security;

    public function __construct(EmailService $emailService, EntityManagerInterface $entityManager, Security $security)
    {
        $this->emailService = $emailService;
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    /**
     * Page de test pour les emails de traitement
     */
    #[Route('/test', name: 'app_email_traitement_test', methods: ['GET'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function index(): Response
    {
        $user = $this->security->getUser();
        
        // Récupérer tous les traitements pour les tests
        $traitements = $this->entityManager
            ->getRepository(Traitement::class)
            ->findBy(['psychologue' => $user], ['created_at' => 'DESC']);

        return $this->render('email/traitement_test.html.twig', [
            'traitements' => $traitements,
            'user' => $user
        ]);
    }

    /**
     * Envoyer un email de notification de nouveau traitement
     */
    #[Route('/nouveau-traitement/{id}', name: 'app_email_traitement_nouveau', methods: ['POST'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function envoyerEmailNouveauTraitement(Traitement $traitement): Response
    {
        $this->denyAccessUnlessOwner($traitement);
        
        try {
            $result = $this->emailService->envoyerNotificationNouveauTraitement($traitement);
            
            if ($result) {
                $this->addFlash('success', 'Email de notification de nouveau traitement envoyé avec succès !');
            } else {
                $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email de notification.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_email_traitement_test');
    }

    /**
     * Envoyer un email de rappel de suivi
     */
    #[Route('/rappel-suivi/{id}', name: 'app_email_traitement_rappel_suivi', methods: ['POST'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function envoyerRappelSuivi(Traitement $traitement): Response
    {
        $this->denyAccessUnlessOwner($traitement);
        
        try {
            $result = $this->emailService->envoyerRappelSuivi($traitement);
            
            if ($result) {
                $this->addFlash('success', 'Email de rappel de suivi envoyé avec succès !');
            } else {
                $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email de rappel.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_email_traitement_test');
    }

    /**
     * Envoyer un email de fin de traitement
     */
    #[Route('/fin-traitement/{id}', name: 'app_email_traitement_fin', methods: ['POST'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function envoyerEmailFinTraitement(Traitement $traitement): Response
    {
        $this->denyAccessUnlessOwner($traitement);
        
        try {
            $result = $this->emailService->envoyerNotificationFinTraitement($traitement);
            
            if ($result) {
                $this->addFlash('success', 'Email de notification de fin de traitement envoyé avec succès !');
            } else {
                $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email de notification.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_email_traitement_test');
    }

    /**
     * Envoyer un email de rapport hebdomadaire
     */
    #[Route('/rapport-hebdomadaire', name: 'app_email_traitement_rapport_hebdomadaire', methods: ['POST'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function envoyerRapportHebdomadaire(): Response
    {
        $user = $this->security->getUser();
        
        try {
            $result = $this->emailService->envoyerRapportHebdomadaire($user);
            
            if ($result) {
                $this->addFlash('success', 'Rapport hebdomadaire envoyé avec succès !');
            } else {
                $this->addFlash('error', 'Erreur lors de l\'envoi du rapport hebdomadaire.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_email_traitement_test');
    }

    /**
     * Envoyer un email de suivi manquant
     */
    #[Route('/suivi-manquant/{id}', name: 'app_email_traitement_suivi_manquant', methods: ['POST'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function envoyerEmailSuiviManquant(Traitement $traitement): Response
    {
        $this->denyAccessUnlessOwner($traitement);
        
        try {
            $result = $this->emailService->envoyerNotificationSuiviManquant($traitement);
            
            if ($result) {
                $this->addFlash('success', 'Email de notification de suivi manquant envoyé avec succès !');
            } else {
                $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email de notification.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_email_traitement_test');
    }

    /**
     * Test automatique des emails pour tous les traitements
     */
    #[Route('/test-automatique', name: 'app_email_traitement_test_automatique', methods: ['POST'])]
    #[IsGranted('ROLE_PSYCHOLOGUE')]
    public function testAutomatique(): Response
    {
        $user = $this->security->getUser();
        
        try {
            $traitements = $this->entityManager
                ->getRepository(Traitement::class)
                ->findBy(['psychologue' => $user]);

            $emailsEnvoyes = 0;
            $emailsErreurs = 0;

            foreach ($traitements as $traitement) {
                try {
                    // Envoyer un email selon le statut du traitement
                    if ($traitement->getStatut() === 'en cours') {
                        $dernierSuivi = $this->getDernierSuivi($traitement);
                        
                        // Cas 1: Traitement sans aucun suivi
                        if (!$dernierSuivi) {
                            // Si le traitement a commencé il y a plus de 5 jours sans suivi
                            if ($traitement->getDateDebut() < new \DateTime('-5 days')) {
                                $this->emailService->envoyerNotificationSuiviManquant($traitement);
                                $emailsEnvoyes++;
                            }
                        }
                        
                        // Cas 2: Dernier suivi trop ancien
                        if ($dernierSuivi && $dernierSuivi->getDateSuivi() < new \DateTime('-7 days')) {
                            $this->emailService->envoyerNotificationSuiviManquant($traitement);
                            $emailsEnvoyes++;
                        }
                    }
                } catch (\Exception $e) {
                    $emailsErreurs++;
                }
            }

            $this->addFlash('info', sprintf(
                'Envoi automatique terminé : %d emails envoyés, %d erreurs',
                $emailsEnvoyes,
                $emailsErreurs
            ));

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi automatique : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_email_traitement_test');
    }

    /**
     * Vérifier que l'utilisateur peut accéder à ce traitement
     */
    private function denyAccessUnlessOwner(Traitement $traitement): void
    {
        $user = $this->security->getUser();
        
        // Si l'utilisateur est un psychologue, vérifier que le traitement lui appartient
        if ($this->isGranted('ROLE_PSYCHOLOGUE')) {
            if ($traitement->getPsychologue()?->getId() !== $user->getId()) {
                throw $this->createAccessDeniedException('Vous ne pouvez pas accéder à ce traitement.');
            }
        }
    }

    /**
     * Obtenir le dernier suivi d'un traitement
     */
    private function getDernierSuivi(Traitement $traitement): ?SuiviTraitement
    {
        return $this->entityManager
            ->getRepository(SuiviTraitement::class)
            ->findOneBy(['traitement' => $traitement], ['dateSuivi' => 'DESC']);
    }
}
