<?php

namespace App\Controller\Admin;

use App\Entity\Questionnaire;
use App\Form\QuestionnaireType;
use App\Repository\QuestionnaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/questionnaire')]
class QuestionnaireController extends AbstractController
{
    #[Route('/', name: 'admin_questionnaire_index', methods: ['GET'])]
    public function index(QuestionnaireRepository $questionnaireRepository): Response
    {
        return $this->render('admin/questionnaire/index.html.twig', [
            'questionnaires' => $questionnaireRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_questionnaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $questionnaire = new Questionnaire();
        $form = $this->createForm(QuestionnaireType::class, $questionnaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ✅ CORRECTION : Assigner l'admin connecté avant de persister
            $questionnaire->setAdmin($this->getUser());

            $entityManager->persist($questionnaire);
            $entityManager->flush();

            $this->addFlash('success', 'Questionnaire créé avec succès !');
            return $this->redirectToRoute('admin_questionnaire_index');
        }

        return $this->render('admin/questionnaire/new.html.twig', [
            'questionnaire' => $questionnaire,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_questionnaire_show', methods: ['GET'])]
    public function show(Questionnaire $questionnaire): Response
    {
        return $this->render('admin/questionnaire/show.html.twig', [
            'questionnaire' => $questionnaire,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_questionnaire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Questionnaire $questionnaire, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(QuestionnaireType::class, $questionnaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Questionnaire modifié avec succès !');
            return $this->redirectToRoute('admin_questionnaire_index');
        }

        return $this->render('admin/questionnaire/edit.html.twig', [
            'questionnaire' => $questionnaire,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_questionnaire_delete', methods: ['POST'])]
    public function delete(Request $request, Questionnaire $questionnaire, EntityManagerInterface $entityManager): Response
    {
        // ✅ Fonctionne correctement maintenant grâce à getId() dans l'entité
        if ($this->isCsrfTokenValid('delete' . $questionnaire->getId(), $request->request->get('_token'))) {
            $entityManager->remove($questionnaire);
            $entityManager->flush();
            $this->addFlash('success', 'Questionnaire supprimé avec succès !');
        }

        return $this->redirectToRoute('admin_questionnaire_index');
    }

    #[Route('/{id}/questions', name: 'admin_questionnaire_questions', methods: ['GET'])]
    public function questions(Questionnaire $questionnaire): Response
    {
        return $this->render('admin/questionnaire/questions.html.twig', [
            'questionnaire' => $questionnaire,
            'questions' => $questionnaire->getQuestions(),
        ]);
    }
}