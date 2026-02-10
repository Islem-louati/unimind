<?php

namespace App\Controller\Evenement\Responsable;

use App\Entity\EvenementSponsor;
use App\Form\Evenement\EvenementSponsorType;
use App\Repository\EvenementSponsorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * CRUD des liens Événement–Sponsor (Back Office).
 */
#[Route('/responsable-etudiant/evenement-sponsors', name: 'app_back_evenement_sponsor_')]
final class EvenementSponsorCrudController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_back_sponsor_index');
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $evenementSponsor = new EvenementSponsor();
        $form = $this->createForm(EvenementSponsorType::class, $evenementSponsor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($evenementSponsor);
            $em->flush();
            $this->addFlash('success', 'La contribution a été enregistrée.');
            return $this->redirectToRoute('app_back_evenement_sponsor_index');
        }

        return $this->render('evenement/responsable/evenement_sponsor_form.html.twig', [
            'evenement_sponsor' => $evenementSponsor,
            'form' => $form,
            'is_edit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, EvenementSponsor $evenementSponsor, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EvenementSponsorType::class, $evenementSponsor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'La contribution a été modifiée.');
            return $this->redirectToRoute('app_back_evenement_sponsor_index');
        }

        return $this->render('evenement/responsable/evenement_sponsor_form.html.twig', [
            'evenement_sponsor' => $evenementSponsor,
            'form' => $form,
            'is_edit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, EvenementSponsor $evenementSponsor, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_evenement_sponsor_' . $evenementSponsor->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_back_evenement_sponsor_index');
        }
        $em->remove($evenementSponsor);
        $em->flush();
        $this->addFlash('success', 'La contribution a été supprimée.');
        return $this->redirectToRoute('app_back_evenement_sponsor_index');
    }
}
