<?php

namespace App\Controller;

use App\Repository\EvenementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_root', methods: ['GET'])]
    #[Route('/home', name: 'app_home', methods: ['GET'])]
    public function index(EvenementRepository $evenementRepository): Response
    {
        $evenements = $evenementRepository->findBy([], ['dateDebut' => 'ASC'], 6);

        return $this->render('home/acceuil.html.twig', [
            'evenements' => $evenements,
        ]);
    }
}
