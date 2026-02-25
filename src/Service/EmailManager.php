<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;

class EmailManager
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        private string $mailerFrom,
        private string $mailerFromName,
        private string $adminEmail
    ) {}

    /**
     * Test direct de l'envoi d'email avec diagnostic complet
     */
    public function testDirectEmail(string $to): array
{
    $result = [
        'success' => false,
        'message' => '',
        'exception' => null,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    try {
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, $this->mailerFromName))
            ->replyTo(new Address($this->adminEmail, $this->mailerFromName)) // ✅ Ajout Reply-To
            ->to($to)
            ->subject('🔧 Test Mailjet - ' . date('Y-m-d H:i:s'))
            ->html('<h1>Test Mailjet</h1><p>Test envoyé le ' . date('Y-m-d H:i:s') . '</p>');

        $this->mailer->send($email);
        
        $result['success'] = true;
        $result['message'] = '✅ Email envoyé avec succès via Mailjet';
        
    } catch (TransportExceptionInterface $e) {
        $result['message'] = '❌ Erreur de transport: ' . $e->getMessage();
        $result['exception'] = [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode()
        ];
    } catch (\Exception $e) {
        $result['message'] = '❌ Erreur générale: ' . $e->getMessage();
        $result['exception'] = [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode()
        ];
    }

    return $result;
}
    /**
     * Envoie l'email de vérification
     */
    public function sendVerificationEmail(User $user): void
{
    try {
        $verificationUrl = $this->urlGenerator->generate('app_verify_email', [
            'token' => $user->getVerificationToken()
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, $this->mailerFromName))
            ->replyTo(new Address($this->adminEmail, $this->mailerFromName)) // ✅ Ajout Reply-To
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('✅ Vérification de votre email - UniMind')
            ->htmlTemplate('security/verification_email.html.twig')
            ->context([
                'user' => $user,
                'verificationUrl' => $verificationUrl,
                'expiresAt' => $user->getTokenExpiresAt(),
                'current_year' => date('Y')
            ]);

        $this->mailer->send($email);
        $this->logger->info('✅ Email de vérification envoyé à ' . $user->getEmail());
        
    } catch (TransportExceptionInterface $e) {
        $this->logger->error('❌ ERREUR TRANSPORT vérification: ' . $e->getMessage());
    } catch (\Exception $e) {
        $this->logger->error('❌ ERREUR vérification: ' . $e->getMessage());
    }
}

public function sendResetPasswordEmail(User $user): void
{
    try {
        $resetUrl = $this->urlGenerator->generate('app_reset_password', [
            'token' => $user->getResetToken()
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, $this->mailerFromName))
            ->replyTo(new Address($this->adminEmail, $this->mailerFromName)) // ✅ Ajout Reply-To
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('🔐 Réinitialisation de votre mot de passe - UniMind')
            ->htmlTemplate('security/reset_password_email.html.twig')
            ->context([
                'user' => $user,
                'resetUrl' => $resetUrl,
                'expiresAt' => $user->getResetTokenExpiresAt(),
                'current_year' => date('Y')
            ]);

        $this->mailer->send($email);
        $this->logger->info('✅ Email de réinitialisation envoyé à ' . $user->getEmail());
        
    } catch (TransportExceptionInterface $e) {
        $this->logger->error('❌ ERREUR TRANSPORT réinitialisation: ' . $e->getMessage());
    } catch (\Exception $e) {
        $this->logger->error('❌ ERREUR réinitialisation: ' . $e->getMessage());
    }
}

public function notifyAdminNewPendingUser(User $newUser): void
{
    try {
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, $this->mailerFromName))
            ->replyTo(new Address($this->adminEmail, $this->mailerFromName)) // ✅ Ajout Reply-To
            ->to($this->adminEmail)
            ->subject('👤 Nouvelle inscription en attente - UniMind')
            ->htmlTemplate('security/admin_new_pending_user.html.twig')
            ->context([
                'user' => $newUser,
                'current_year' => date('Y')
            ]);

        $this->mailer->send($email);
        $this->logger->info('✅ Notification admin envoyée pour ' . $newUser->getEmail());
        
    } catch (TransportExceptionInterface $e) {
        $this->logger->error('❌ ERREUR TRANSPORT notification admin: ' . $e->getMessage());
    } catch (\Exception $e) {
        $this->logger->error('❌ ERREUR notification admin: ' . $e->getMessage());
    }
}

   
}