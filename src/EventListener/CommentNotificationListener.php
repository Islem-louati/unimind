<?php

namespace App\EventListener;

use App\Entity\Commentaire;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CommentNotificationListener
{
    public function __construct(
        private NotifierInterface $notifier,
        private UrlGeneratorInterface $router,
         private LoggerInterface $logger
    ) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $comment = $args->getObject();
        if (!$comment instanceof Commentaire) {
            return;
        }

        $post = $comment->getPost();
        $author = $post->getUser();

        // Éviter de notifier l'auteur du commentaire lui-même
        if ($author->getId() === $comment->getUser()->getId()) {
            return;
        }

        // Générer le lien absolu vers le post (adaptez la route à votre besoin)
        $postUrl = $this->router->generate('etudiant_meditation_index', [
            // Ajoutez les paramètres si nécessaire, par exemple un fragment #post-xxx
            '_fragment' => 'post-' . $post->getPostId()
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $message = sprintf(
            '%s %s a répondu à votre discussion "%s".',
            $comment->getUser()->getPrenom(),
            $comment->getUser()->getNom(),
            $post->getTitre()
        );

        $notification = (new Notification('Nouvelle réponse sur votre discussion', ['email']))
            ->content($message)
            ->importance(Notification::IMPORTANCE_LOW);

        $recipient = new Recipient($author->getEmail());
        try {
        $this->notifier->send($notification, $recipient);
        $this->logger->info('Notification envoyée à ' . $author->getEmail());
    } catch (\Exception $e) {
        $this->logger->error('Erreur envoi notification : ' . $e->getMessage());
        // Ne pas relancer l'exception pour ne pas bloquer l'ajout du commentaire
    }
    }
}