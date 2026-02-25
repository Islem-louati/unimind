<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class TestMailController extends AbstractController
{
    #[Route('/test-mail', name: 'test_mail')]
    // src/Controller/TestMailController.php


public function test(MailerInterface $mailer, LoggerInterface $logger): Response
{
    $logger->info('Début du test mail');
    $email = (new Email())
        ->from('souhakhenissi3@gmail.com')
        ->to('souhakhenissi76@gmail.com')
        ->subject('Test Symfony Mailer')
        ->text('Ceci est un test.');

    try {
        $logger->info('Tentative d\'envoi...');
        $mailer->send($email);
        $logger->info('Envoi réussi');
        return new Response('Email envoyé avec succès !');
    } catch (\Exception $e) {
        $logger->error('Erreur envoi mail : ' . $e->getMessage());
        return new Response('Erreur : ' . $e->getMessage());
    }
}
}