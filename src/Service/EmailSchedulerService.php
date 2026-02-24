<?php

namespace App\Service;

use App\Entity\Traitement;
use App\Entity\SuiviTraitement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class EmailSchedulerService
{
    private EmailService $emailService;
    private EntityManagerInterface $entityManager;

    public function __construct(EmailService $emailService, EntityManagerInterface $entityManager)
    {
        $this->emailService = $emailService;
        $this->entityManager = $entityManager;
    }

    /**
     * Envoie un email de notification lors de la création d'un traitement
     */
    public function onTraitementCreated(Traitement $traitement): void
    {
        try {
            $this->emailService->envoyerNotificationNouveauTraitement($traitement);
        } catch (\Exception $e) {
            error_log('Erreur envoi email création traitement: ' . $e->getMessage());
        }
    }

    /**
     * Envoie un email de notification lors de la fin d'un traitement
     */
    public function onTraitementFinished(Traitement $traitement): void
    {
        try {
            $this->emailService->envoyerNotificationFinTraitement($traitement);
        } catch (\Exception $e) {
            error_log('Erreur envoi email fin traitement: ' . $e->getMessage());
        }
    }

    /**
     * Vérifie et envoie les rappels de suivi automatiques
     * À exécuter quotidiennement via tâche cron
     */
    public function sendAutomaticReminders(): array
    {
        $results = [
            'emails_envoyes' => 0,
            'erreurs' => 0,
            'details' => []
        ];

        try {
            // Récupérer tous les traitements "en cours"
            $traitements = $this->entityManager
                ->getRepository(Traitement::class)
                ->findBy(['statut' => 'en cours']);

            foreach ($traitements as $traitement) {
                try {
                    $dernierSuivi = $this->getDernierSuivi($traitement);
                    
                    // Cas 1: Traitement sans suivi depuis plus de 5 jours
                    if (!$dernierSuivi && $traitement->getDateDebut() < new \DateTime('-5 days')) {
                        $this->emailService->envoyerNotificationSuiviManquant($traitement);
                        $results['emails_envoyes']++;
                        $results['details'][] = [
                            'traitement_id' => $traitement->getTraitementId(),
                            'type' => 'suivi_manquant_sans_suivi',
                            'raison' => 'Traitement sans suivi depuis plus de 5 jours'
                        ];
                    }
                    
                    // Cas 2: Dernier suivi trop ancien
                    if ($dernierSuivi && $dernierSuivi->getDateSuivi() < new \DateTime('-7 days')) {
                        $this->emailService->envoyerNotificationSuiviManquant($traitement);
                        $results['emails_envoyes']++;
                        $results['details'][] = [
                            'traitement_id' => $traitement->getTraitementId(),
                            'type' => 'suivi_manquant_ancien',
                            'raison' => 'Dernier suivi il y a plus de 7 jours'
                        ];
                    }
                } catch (\Exception $e) {
                    $results['erreurs']++;
                    error_log('Erreur traitement ' . $traitement->getTraitementId() . ': ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            $results['erreurs']++;
            error_log('Erreur générale envoi rappels: ' . $e->getMessage());
        }

        return $results;
    }

    /**
     * Envoie les rapports hebdomadaires à tous les psychologues
     * À exécuter chaque dimanche via tâche cron
     */
    public function sendWeeklyReports(): array
    {
        $results = [
            'rapports_envoyes' => 0,
            'erreurs' => 0,
            'details' => []
        ];

        try {
            // Récupérer tous les psychologues
            $psychologues = $this->entityManager
                ->getRepository(\App\Entity\User::class)
                ->findBy(['role' => 'psychologue']);

            foreach ($psychologues as $psychologue) {
                try {
                    if ($this->emailService->envoyerRapportHebdomadaire($psychologue)) {
                        $results['rapports_envoyes']++;
                        $results['details'][] = [
                            'psychologue_id' => $psychologue->getId(),
                            'psychologue_nom' => $psychologue->getFullName(),
                            'statut' => 'envoyé'
                        ];
                    } else {
                        $results['details'][] = [
                            'psychologue_id' => $psychologue->getId(),
                            'psychologue_nom' => $psychologue->getFullName(),
                            'statut' => 'échec'
                        ];
                    }
                } catch (\Exception $e) {
                    $results['erreurs']++;
                    error_log('Erreur rapport psychologue ' . $psychologue->getId() . ': ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            $results['erreurs']++;
            error_log('Erreur générale rapports hebdomadaires: ' . $e->getMessage());
        }

        return $results;
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
