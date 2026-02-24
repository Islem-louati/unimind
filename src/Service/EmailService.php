<?php

namespace App\Service;

use App\Entity\Traitement;
use App\Entity\SuiviTraitement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Twig\Environment;

class EmailService
{
    private $mailer;
    private $twig;
    private $entityManager;

    public function __construct(MailerInterface $mailer, Environment $twig, EntityManagerInterface $entityManager)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->entityManager = $entityManager;
    }

    /**
     * Méthode générique pour envoyer un email
     */
    private function sendEmail(string $to, string $subject, string $template, array $context): bool
    {
        try {
            $email = (new Email())
                ->from(new Address('noreply@unimind.com', 'UniMind'))
                ->to($to)
                ->subject($subject)
                ->html($this->twig->render($template, $context));

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            error_log('Erreur envoi email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie un email de rappel pour un suivi
     */
    public function sendRappelSuivi(SuiviTraitement $suivi): bool
    {
        try {
            $email = (new Email())
                ->from(new Address('noreply@unimind.com', 'UniMind'))
                ->to($suivi->getTraitement()->getEtudiant()->getEmail())
                ->subject('Rappel de suivi - ' . $suivi->getTraitement()->getTitre())
                ->html($this->twig->render('email/rappel_suivi.html.twig', [
                    'suivi' => $suivi,
                    'traitement' => $suivi->getTraitement()
                ]));

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Envoie un email de notification pour un nouveau traitement
     */
    public function sendNotificationTraitement(Traitement $traitement): bool
    {
        try {
            $email = (new Email())
                ->from(new Address('noreply@unimind.com', 'UniMind'))
                ->to($traitement->getEtudiant()->getEmail())
                ->subject('Nouveau traitement - ' . $traitement->getTitre())
                ->html($this->twig->render('email/notification_traitement.html.twig', [
                    'traitement' => $traitement
                ]));

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Envoie un email de rapport hebdomadaire
     */
    public function sendRapportHebdomadaire(User $patient, array $suivis): bool
    {
        try {
            $email = (new Email())
                ->from(new Address('noreply@unimind.com', 'UniMind'))
                ->to($patient->getEmail())
                ->subject('Rapport hebdomadaire de vos suivis')
                ->html($this->twig->render('email/rapport_hebdomadaire.html.twig', [
                    'patient' => $patient,
                    'suivis' => $suivis
                ]));

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Envoie un email de confirmation de suivi
     */
    public function sendConfirmationSuivi(SuiviTraitement $suivi): bool
    {
        try {
            $email = (new Email())
                ->from(new Address('noreply@unimind.com', 'UniMind'))
                ->to($suivi->getTraitement()->getEtudiant()->getEmail())
                ->subject('Confirmation de suivi - ' . $suivi->getTraitement()->getTitre())
                ->html($this->twig->render('email/confirmation_suivi.html.twig', [
                    'suivi' => $suivi,
                    'traitement' => $suivi->getTraitement()
                ]));

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Envoie un email de rappel de traitement
     */
    public function sendRappelTraitement(Traitement $traitement): bool
    {
        try {
            $email = (new Email())
                ->from(new Address('noreply@unimind.com', 'UniMind'))
                ->to($traitement->getEtudiant()->getEmail())
                ->subject('Rappel de traitement - ' . $traitement->getTitre())
                ->html($this->twig->render('email/rappel_traitement.html.twig', [
                    'traitement' => $traitement
                ]));

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Envoyer une notification de nouveau traitement à l'étudiant
     */
    public function envoyerNotificationNouveauTraitement(Traitement $traitement): bool
    {
        try {
            $etudiant = $traitement->getEtudiant();
            if (!$etudiant || !$etudiant->getEmail()) {
                return false;
            }

            $subject = '🌟 Nouveau traitement démarré - ' . $traitement->getTitre();
            
            $context = [
                'traitement' => $traitement,
                'etudiant' => $etudiant,
                'psychologue' => $traitement->getPsychologue(),
                'dateDebut' => $traitement->getDateDebut(),
                'objectif' => $traitement->getObjectifTherapeutique(),
                'type' => 'nouveau_traitement'
            ];

            return $this->sendEmail(
                $etudiant->getEmail(),
                $subject,
                'email/traitement_notification.html.twig',
                $context
            );
        } catch (\Exception $e) {
            error_log('Erreur envoi notification nouveau traitement: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer un rappel de suivi à l'étudiant
     */
    public function envoyerRappelSuivi(Traitement $traitement): bool
    {
        try {
            $etudiant = $traitement->getEtudiant();
            if (!$etudiant || !$etudiant->getEmail()) {
                return false;
            }

            $subject = '📅 Rappel de suivi - ' . $traitement->getTitre();
            
            $context = [
                'traitement' => $traitement,
                'etudiant' => $etudiant,
                'psychologue' => $traitement->getPsychologue(),
                'dernierSuivi' => $this->getDernierSuivi($traitement),
                'type' => 'rappel_suivi'
            ];

            return $this->sendEmail(
                $etudiant->getEmail(),
                $subject,
                'email/traitement_notification.html.twig',
                $context
            );
        } catch (\Exception $e) {
            error_log('Erreur envoi rappel suivi: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer une notification de fin de traitement
     */
    public function envoyerNotificationFinTraitement(Traitement $traitement): bool
    {
        try {
            $etudiant = $traitement->getEtudiant();
            if (!$etudiant || !$etudiant->getEmail()) {
                return false;
            }

            $subject = '🎉 Traitement terminé - ' . $traitement->getTitre();
            
            $context = [
                'traitement' => $traitement,
                'etudiant' => $etudiant,
                'psychologue' => $traitement->getPsychologue(),
                'duree' => $traitement->getDureeJours(),
                'progression' => $traitement->getPourcentageCompletion(),
                'type' => 'fin_traitement'
            ];

            return $this->sendEmail(
                $etudiant->getEmail(),
                $subject,
                'email/traitement_notification.html.twig',
                $context
            );
        } catch (\Exception $e) {
            error_log('Erreur envoi notification fin traitement: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer une notification de suivi manquant
     */
    public function envoyerNotificationSuiviManquant(Traitement $traitement): bool
    {
        try {
            $etudiant = $traitement->getEtudiant();
            if (!$etudiant || !$etudiant->getEmail()) {
                return false;
            }

            $subject = '⚠️ Suivi manquant - ' . $traitement->getTitre();
            
            $context = [
                'traitement' => $traitement,
                'etudiant' => $etudiant,
                'psychologue' => $traitement->getPsychologue(),
                'dernierSuivi' => $this->getDernierSuivi($traitement),
                'joursSansSuivi' => $this->calculerJoursSansSuivi($traitement),
                'type' => 'suivi_manquant'
            ];

            return $this->sendEmail(
                $etudiant->getEmail(),
                $subject,
                'email/traitement_notification.html.twig',
                $context
            );
        } catch (\Exception $e) {
            error_log('Erreur envoi notification suivi manquant: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer un rapport hebdomadaire au psychologue
     */
    public function envoyerRapportHebdomadaire($psychologue): bool
    {
        try {
            if (!$psychologue || !$psychologue->getEmail()) {
                return false;
            }

            // Récupérer les statistiques de la semaine
            $debutSemaine = new \DateTime('monday this week');
            $finSemaine = new \DateTime('sunday this week');

            $traitements = $this->entityManager
                ->getRepository(Traitement::class)
                ->createQueryBuilder('t')
                ->where('t.psychologue = :psychologue')
                ->andWhere('t.created_at BETWEEN :debut AND :fin')
                ->setParameter('psychologue', $psychologue)
                ->setParameter('debut', $debutSemaine)
                ->setParameter('fin', $finSemaine)
                ->getQuery()
                ->getResult();

            $suivis = $this->entityManager
                ->getRepository(SuiviTraitement::class)
                ->createQueryBuilder('s')
                ->leftJoin('s.traitement', 't')
                ->where('t.psychologue = :psychologue')
                ->andWhere('s.dateSuivi BETWEEN :debut AND :fin')
                ->setParameter('psychologue', $psychologue)
                ->setParameter('debut', $debutSemaine)
                ->setParameter('fin', $finSemaine)
                ->getQuery()
                ->getResult();

            $subject = '📊 Rapport hebdomadaire - ' . $debutSemaine->format('d/m/Y');
            
            $context = [
                'psychologue' => $psychologue,
                'traitements' => $traitements,
                'suivis' => $suivis,
                'debutSemaine' => $debutSemaine,
                'finSemaine' => $finSemaine,
                'type' => 'rapport_hebdomadaire'
            ];

            return $this->sendEmail(
                $psychologue->getEmail(),
                $subject,
                'email/rapport_hebdomadaire.html.twig',
                $context
            );
        } catch (\Exception $e) {
            error_log('Erreur envoi rapport hebdomadaire: ' . $e->getMessage());
            return false;
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

    /**
     * Calculer le nombre de jours sans suivi
     */
    private function calculerJoursSansSuivi(Traitement $traitement): int
    {
        $dernierSuivi = $this->getDernierSuivi($traitement);
        if (!$dernierSuivi) {
            // Si aucun suivi, calculer depuis le début du traitement
            return (new \DateTime())->diff($traitement->getDateDebut())->days;
        }
        
        return (new \DateTime())->diff($dernierSuivi->getDateSuivi())->days;
    }
}
