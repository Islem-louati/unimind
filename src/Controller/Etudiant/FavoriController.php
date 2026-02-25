<?php
namespace App\Controller\Etudiant;

use App\Entity\SeanceMeditation;
use App\Entity\Favori;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/favori')]
class FavoriController extends AbstractController
{
    #[Route('/toggle/{id}', name: 'favori_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggle(SeanceMeditation $seance, EntityManagerInterface $em, Request $request): JsonResponse
    {
        // Vérification CSRF (optionnelle mais recommandée)
        if (!$this->isCsrfTokenValid('favori', $request->request->get('_token'))) {
            return $this->json(['error' => 'Token invalide'], 400);
        }

        $user = $this->getUser();
        $favoriRepo = $em->getRepository(Favori::class);
        $existing = $favoriRepo->findOneByUserAndSeance($user, $seance);

        try {
    if ($existing) {
        $em->remove($existing);
        $isFavori = false;
    } else {
        $favori = new Favori();
        $favori->setUser($user);
        $favori->setSeance($seance);
        $em->persist($favori);
        $isFavori = true;
    }
    $em->flush();
} catch (\Exception $e) {
    return $this->json([
        'success' => false,
        'message' => 'Erreur base de données : ' . $e->getMessage()
    ], 500);
}

        return $this->json([
            'success' => true,
            'isFavori' => $isFavori,
            'message' => $isFavori ? 'Ajouté aux favoris' : 'Retiré des favoris'
        ]);
    }

    #[Route('/liste', name: 'favori_liste')]
    #[IsGranted('ROLE_USER')]
    public function liste(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $favoris = $em->getRepository(Favori::class)->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('etudiant/favori/liste.html.twig', [
            'favoris' => $favoris,
        ]);
    }
}