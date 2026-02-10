<?php

namespace App\Controller\Etudiant;

use App\Entity\Commentaire;
use App\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/etudiant/forum')]
class CommentaireController extends AbstractController
{
    #[Route('/post/{id}/comment', name: 'etudiant_forum_comment_new', methods: ['POST'])]
    public function newComment(
        Request $request,
        Post $post,
        EntityManagerInterface $entityManager
    ): Response
    {
        $contenu = $request->request->get('contenu');
        $isAnonyme = $request->request->get('is_anonyme') === 'on';
        
        if (empty(trim($contenu))) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Le commentaire ne peut pas être vide.'
                ], 400);
            }
            
            $this->addFlash('error', 'Le commentaire ne peut pas être vide.');
            return $this->redirectToRoute('etudiant_meditation_index');
        }
        
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('comment_' . $post->getPostId(), $token)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Token CSRF invalide.'
                ], 400);
            }
            
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('etudiant_meditation_index');
        }
        
        $commentaire = new Commentaire();
        $commentaire->setContenu($contenu);
        $commentaire->setIsAnonyme($isAnonyme);
        $commentaire->setPost($post);
        
        // Gérer l'utilisateur (simplifié pour le test)
        $user = $this->getUser();
        if ($user) {
            $commentaire->setUser($user);
        }
        
        $commentaire->setCreatedAt(new \DateTime());
        
        $entityManager->persist($commentaire);
        $entityManager->flush();
        
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Commentaire ajouté avec succès.',
                'commentaireId' => $commentaire->getCommentaireId()
            ]);
        }
        
        $this->addFlash('success', 'Votre commentaire a été ajouté.');
        return $this->redirectToRoute('etudiant_meditation_index');
    }
    
    #[Route('/comment/{id}/edit', name: 'etudiant_forum_comment_edit', methods: ['POST'])]
    public function editComment(
        Request $request,
        Commentaire $commentaire,
        EntityManagerInterface $entityManager
    ): Response
    {
        // COMMENTER la vérification d'utilisateur
        // if ($commentaire->getUser() !== $security->getUser()) {
        //     $this->addFlash('error', 'Vous ne pouvez pas modifier ce commentaire.');
        //     return $this->redirectToRoute('etudiant_meditation_index');
        // }
        
        $contenu = $request->request->get('contenu');
        $isAnonyme = $request->request->get('is_anonyme') === 'on';
        
        if (!$contenu) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Le contenu est requis.'
                ], 400);
            }
            
            $this->addFlash('error', 'Le contenu est requis.');
            return $this->redirectToRoute('etudiant_meditation_index');
        }
        
        $commentaire->setContenu($contenu);
        $commentaire->setIsAnonyme($isAnonyme);
        
        $entityManager->flush();
        
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Commentaire modifié avec succès.'
            ]);
        }
        
        $this->addFlash('success', 'Commentaire modifié avec succès.');
        return $this->redirectToRoute('etudiant_meditation_index');
    }
    
    #[Route('/comment/{id}', name: 'etudiant_forum_comment_delete', methods: ['POST'])]
    public function deleteComment(
        Request $request,
        Commentaire $commentaire,
        EntityManagerInterface $entityManager
    ): Response
    {
        // COMMENTER la vérification d'utilisateur
        // if ($commentaire->getUser() !== $security->getUser()) {
        //     $this->addFlash('error', 'Vous ne pouvez pas supprimer ce commentaire.');
        //     return $this->redirectToRoute('etudiant_meditation_index');
        // }
        
        if ($this->isCsrfTokenValid('delete'.$commentaire->getCommentaireId(), $request->request->get('_token'))) {
            $entityManager->remove($commentaire);
            $entityManager->flush();
            
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Commentaire supprimé avec succès.'
                ]);
            }
            
            $this->addFlash('success', 'Commentaire supprimé avec succès.');
        }
        
        return $this->redirectToRoute('etudiant_meditation_index');
    }
}