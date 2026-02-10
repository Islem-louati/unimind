<?php

namespace App\Controller\Etudiant;

use App\Entity\Post;
use App\Form\PostType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/etudiant/forum')]
class PostController extends AbstractController
{
    #[Route('/post/{id}/edit', name: 'etudiant_forum_post_edit', methods: ['POST'])]
    public function editPost(
        Request $request,
        Post $post,
        EntityManagerInterface $entityManager
    ): Response
    {
        // COMMENTER ou SUPPRIMER la vérification d'utilisateur
        // if ($post->getUser() !== $security->getUser()) {
        //     $this->addFlash('error', 'Vous ne pouvez pas modifier cette discussion.');
        //     return $this->redirectToRoute('etudiant_meditation_index');
        // }
        
        // Récupérer les données du formulaire modal
        $titre = $request->request->get('titre');
        $contenu = $request->request->get('contenu');
        $isAnonyme = $request->request->get('is_anonyme') === 'on';
        
        if (!$titre || !$contenu) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Le titre et le contenu sont requis.'
                ], 400);
            }
            
            $this->addFlash('error', 'Le titre et le contenu sont requis.');
            return $this->redirectToRoute('etudiant_meditation_index');
        }
        
        // Mettre à jour le post
        $post->setTitre($titre);
        $post->setContenu($contenu);
        $post->setIsAnonyme($isAnonyme);
        $post->setUpdatedAt(new \DateTime());
        
        $entityManager->flush();
        
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Post modifié avec succès.'
            ]);
        }
        
        $this->addFlash('success', 'Discussion modifiée avec succès.');
        return $this->redirectToRoute('etudiant_meditation_index');
    }
    
    #[Route('/post/{id}', name: 'etudiant_forum_post_delete', methods: ['POST'])]
    public function deletePost(
        Request $request,
        Post $post,
        EntityManagerInterface $entityManager
    ): Response
    {
        // COMMENTER ou SUPPRIMER la vérification d'utilisateur
        // if ($post->getUser() !== $security->getUser()) {
        //     $this->addFlash('error', 'Vous ne pouvez pas supprimer cette discussion.');
        //     return $this->redirectToRoute('etudiant_meditation_index');
        // }
        
        if ($this->isCsrfTokenValid('delete'.$post->getPostId(), $request->request->get('_token'))) {
            $entityManager->remove($post);
            $entityManager->flush();
            
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Post supprimé avec succès.'
                ]);
            }
            
            $this->addFlash('success', 'Discussion supprimée avec succès.');
        }
        
        return $this->redirectToRoute('etudiant_meditation_index');
    }
}