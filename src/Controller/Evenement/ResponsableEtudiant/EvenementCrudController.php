<?php

namespace App\Controller\Evenement\ResponsableEtudiant;

use App\Entity\Evenement;
use App\Entity\User;
use App\Form\Evenement\EvenementType;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * CRUD des événements (Responsable étudiant).
 */
#[Route('/responsable-etudiant/evenements', name: 'app_back_evenement_')]
final class EvenementCrudController extends AbstractController
{
    private SluggerInterface $slugger;

    public function __construct(SluggerInterface $slugger)
    {
        $this->slugger = $slugger;
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EvenementRepository $repository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'dateDebut');
        $order = (string) $request->query->get('order', 'ASC');
        $evenements = $repository->searchAndSort($q, $sort, $order);

        return $this->render('evenement/responsable_etudiant/evenement_index.html.twig', [
            'route_prefix' => 'app_back_',
            'space_label' => 'Responsable étudiant',
            'show_sponsors' => false,
            'show_dashboard' => false,
            'evenements' => $evenements,
            'q' => $q,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        return $this->render('evenement/responsable_etudiant/evenement_show.html.twig', [
            'route_prefix' => 'app_back_',
            'space_label' => 'Responsable étudiant',
            'show_sponsors' => false,
            'show_dashboard' => false,
            'evenement' => $evenement,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $evenement = new Evenement();
        $evenement->setOrganisateur($user);
        $form = $this->createForm(EvenementType::class, $evenement, [
            'validation_groups' => ['Default', 'creation'],
            'allow_organisateur' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/evenements',
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');

                    return $this->render('evenement/responsable_etudiant/evenement_form.html.twig', [
                        'route_prefix' => 'app_back_',
                        'space_label' => 'Responsable étudiant',
                        'show_sponsors' => false,
                        'show_dashboard' => false,
                        'evenement' => $evenement,
                        'form' => $form,
                        'is_edit' => false,
                    ]);
                }

                $evenement->setImage($newFilename);
            }

            $em->persist($evenement);
            $em->flush();
            $this->addFlash('success', 'L\'événement a été créé.');
            return $this->redirectToRoute('app_back_evenement_index');
        }

        return $this->render('evenement/responsable_etudiant/evenement_form.html.twig', [
            'route_prefix' => 'app_back_',
            'space_label' => 'Responsable étudiant',
            'show_sponsors' => false,
            'show_dashboard' => false,
            'evenement' => $evenement,
            'form' => $form,
            'is_edit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($evenement->getOrganisateur() !== $user) {
            $this->addFlash('error', 'Vous ne pouvez modifier que vos propres événements.');
            return $this->redirectToRoute('app_back_evenement_index');
        }

        $form = $this->createForm(EvenementType::class, $evenement, [
            'validation_groups' => ['Default'],
            'allow_organisateur' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                $imageFile->move(
                    $this->getParameter('kernel.project_dir').'/public/uploads/evenements',
                    $newFilename
                );

                $evenement->setImage($newFilename);
            }

            $em->flush();
            $this->addFlash('success', 'L\'événement a été modifié.');
            return $this->redirectToRoute('app_back_evenement_index');
        }

        return $this->render('evenement/responsable_etudiant/evenement_form.html.twig', [
            'route_prefix' => 'app_back_',
            'space_label' => 'Responsable étudiant',
            'show_sponsors' => false,
            'show_dashboard' => false,
            'evenement' => $evenement,
            'form' => $form,
            'is_edit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($evenement->getOrganisateur() !== $user) {
            $this->addFlash('error', 'Vous ne pouvez supprimer que vos propres événements.');
            return $this->redirectToRoute('app_back_evenement_index');
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_evenement_' . $evenement->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_back_evenement_index');
        }

        $em->remove($evenement);
        $em->flush();
        $this->addFlash('success', 'L\'événement a été supprimé.');
        return $this->redirectToRoute('app_back_evenement_index');
    }
}
