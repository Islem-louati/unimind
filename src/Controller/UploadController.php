<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UploadController extends AbstractController
{
    #[Route('/upload/image', name: 'upload_image', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function uploadImage(Request $request, SluggerInterface $slugger): JsonResponse
    {
        $uploadedFile = $request->files->get('upload');
        if (!$uploadedFile) {
            return new JsonResponse(['error' => 'No file uploaded'], 400);
        }

        // Vérifications de sécurité
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($uploadedFile->getMimeType(), $allowedMimeTypes)) {
            return new JsonResponse(['error' => 'Invalid file type'], 400);
        }

        if ($uploadedFile->getSize() > 5 * 1024 * 1024) { // 5 Mo
            return new JsonResponse(['error' => 'File too large'], 400);
        }

        // Générer un nom unique
        $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$uploadedFile->guessExtension();

        // Déplacer le fichier
        try {
            $uploadedFile->move(
                $this->getParameter('kernel.project_dir').'/public/uploads/images',
                $newFilename
            );
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to move file'], 500);
        }

        // Retourner l'URL
        return new JsonResponse(['url' => '/uploads/images/'.$newFilename]);
    }
}