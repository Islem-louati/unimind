<?php

namespace App\Controller\Evenement\Responsable;

use App\Entity\Sponsor;
use App\Entity\EvenementSponsor;
use App\Form\Evenement\SponsorType;
use App\Form\Evenement\EvenementSponsorType;
use App\Form\Evenement\SponsorContributionType;
use App\Repository\SponsorRepository;
use App\Repository\EvenementSponsorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * CRUD des sponsors
 */
#[Route('/responsable-etudiant/sponsors', name: 'app_back_sponsor_')]
final class SponsorCrudController extends AbstractController
{
    private SluggerInterface $slugger;

    public function __construct(SluggerInterface $slugger)
    {
        $this->slugger = $slugger;
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, SponsorRepository $sponsorRepository, EvenementSponsorRepository $evenementSponsorRepository): Response
    {
        // Recherche et tri sur la table unifiée (contributions)
        $q = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'dateContribution');
        $order = (string) $request->query->get('order', 'DESC');

        $evenementSponsors = $evenementSponsorRepository->searchAndSort($q, $sort, $order);

        $sponsorsSansContribution = $sponsorRepository->findSponsorsWithoutContribution();

        return $this->render('evenement/responsable/sponsor_index.html.twig', [
            'evenement_sponsors' => $evenementSponsors,
            'sponsors_sans_contribution' => $sponsorsSansContribution,
            'q' => $q,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Sponsor $sponsor): Response
    {
        return $this->render('evenement/responsable/sponsor_show.html.twig', [
            'sponsor' => $sponsor,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $sponsor = new Sponsor();
        $form = $this->createForm(SponsorType::class, $sponsor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $logoFile */
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile) {
                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $logoFile->guessExtension();
                $logoFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/sponsors',
                    $newFilename
                );
                $sponsor->setLogo($newFilename);
            }

            $em->persist($sponsor);
            $em->flush();
            $this->addFlash('success', 'Le sponsor a été créé.');
            return $this->redirectToRoute('app_back_sponsor_index');
        }

        return $this->render('evenement/responsable/sponsor_form.html.twig', [
            'sponsor' => $sponsor,
            'form' => $form,
            'is_edit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Sponsor $sponsor, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SponsorType::class, $sponsor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $logoFile */
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile) {
                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $logoFile->guessExtension();
                $logoFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/sponsors',
                    $newFilename
                );
                $sponsor->setLogo($newFilename);
            }

            $em->flush();
            $this->addFlash('success', 'Le sponsor a été modifié.');
            return $this->redirectToRoute('app_back_sponsor_index');
        }

        return $this->render('evenement/responsable/sponsor_form.html.twig', [
            'sponsor' => $sponsor,
            'form' => $form,
            'is_edit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Sponsor $sponsor, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_sponsor_' . $sponsor->getSponsorId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_back_sponsor_index');
        }
        $em->remove($sponsor);
        $em->flush();
        $this->addFlash('success', 'Le sponsor a été supprimé.');
        return $this->redirectToRoute('app_back_sponsor_index');
    }

    // --- Gestion des EvenementSponsor (liens événement-sponsor) ---

    #[Route('/contributions/new', name: 'contribution_new', methods: ['GET', 'POST'])]
    public function contributionNew(Request $request, EntityManagerInterface $em): Response
    {
        $evenementSponsor = new EvenementSponsor();
        $form = $this->createForm(EvenementSponsorType::class, $evenementSponsor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($evenementSponsor);
            $em->flush();
            $this->addFlash('success', 'La contribution a été enregistrée.');
            return $this->redirectToRoute('app_back_sponsor_index');
        }

        return $this->render('evenement/responsable/evenement_sponsor_form.html.twig', [
            'evenement_sponsor' => $evenementSponsor,
            'form' => $form,
            'is_edit' => false,
        ]);
    }

    #[Route('/contributions/{id}/edit', name: 'contribution_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function contributionEdit(Request $request, EvenementSponsor $evenementSponsor, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EvenementSponsorType::class, $evenementSponsor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'La contribution a été modifiée.');
            return $this->redirectToRoute('app_back_sponsor_index');
        }

        return $this->render('evenement/responsable/evenement_sponsor_form.html.twig', [
            'evenement_sponsor' => $evenementSponsor,
            'form' => $form,
            'is_edit' => true,
        ]);
    }

    #[Route('/contributions/{id}/delete', name: 'contribution_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function contributionDelete(Request $request, EvenementSponsor $evenementSponsor, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_evenement_sponsor_' . $evenementSponsor->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_back_sponsor_index');
        }
        $em->remove($evenementSponsor);
        $em->flush();
        $this->addFlash('success', 'La contribution a été supprimée.');
        return $this->redirectToRoute('app_back_sponsor_index');
    }

    // === Formulaire combiné Sponsor + Contribution ===

    #[Route('/add-sponsor-contribution', name: 'sponsor_contribution_new', methods: ['GET', 'POST'])]
    public function sponsorContributionNew(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SponsorContributionType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Créer le sponsor
            $sponsor = new Sponsor();
            $sponsor->setNomSponsor($data['nomSponsor']);
            $sponsor->setTypeSponsor(\App\Enum\TypeSponsor::from($data['typeSponsor']));
            $sponsor->setSiteWeb($data['siteWeb']);
            $sponsor->setEmailContact($data['emailContact']);
            $sponsor->setTelephone($data['telephone']);
            $sponsor->setAdresse($data['adresse']);
            $sponsor->setDomaineActivite($data['domaineActivite']);

            /** @var UploadedFile|null $logoFile */
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile) {
                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $logoFile->guessExtension();
                $logoFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/sponsors',
                    $newFilename
                );
                $sponsor->setLogo($newFilename);
            }
            $em->persist($sponsor);

            // Si un événement est sélectionné, créer la contribution
            if (!empty($data['evenement'])) {
                $contribution = new EvenementSponsor();
                $contribution->setEvenement($data['evenement']);
                $contribution->setSponsor($sponsor);
                $contribution->setMontantContribution($data['montantContribution'] ?? '0.00');
                $contribution->setTypeContribution(\App\Enum\TypeContribution::from($data['typeContribution'] ?? \App\Enum\TypeContribution::FINANCIER->value));
                $contribution->setDescriptionContribution($data['descriptionContribution']);
                $contribution->setDateContribution($data['dateContribution'] ?? new \DateTime());
                $contribution->setStatut(\App\Enum\StatutSponsor::from($data['statut'] ?? \App\Enum\StatutSponsor::EN_ATTENTE->value));
                $em->persist($contribution);
            }

            $em->flush();

            $this->addFlash('success', 'Le sponsor a été créé' . (!empty($data['evenement']) ? ' et la contribution a été enregistrée.' : '.'));
            return $this->redirectToRoute('app_back_sponsor_index');
        }

        return $this->render('evenement/responsable/sponsor_contribution_form.html.twig', [
            'form' => $form,
        ]);
    }

    // === Édition unifiée Sponsor + Contribution ===

    #[Route('/edit-unified/{id}', name: 'edit_unified', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editUnified(Sponsor $sponsor, Request $request, EntityManagerInterface $em): Response
    {
        // Récupérer la première contribution si elle existe
        $contribution = null;
        foreach ($sponsor->getEvenementSponsors() as $es) {
            $contribution = $es;
            break; // On prend la première contribution pour l'édition unifiée
        }

        // Préparer les données initiales pour le formulaire
        $initialData = [
            'nomSponsor' => $sponsor->getNomSponsor(),
            'typeSponsor' => $sponsor->getTypeSponsor()?->value,
            'siteWeb' => $sponsor->getSiteWeb(),
            'emailContact' => $sponsor->getEmailContact(),
            'telephone' => $sponsor->getTelephone(),
            'adresse' => $sponsor->getAdresse(),
            'domaineActivite' => $sponsor->getDomaineActivite(),
        ];

        if ($contribution) {
            $initialData['evenement'] = $contribution->getEvenement();
            $initialData['montantContribution'] = $contribution->getMontantContribution();
            $initialData['typeContribution'] = $contribution->getTypeContribution()?->value;
            $initialData['descriptionContribution'] = $contribution->getDescriptionContribution();
            $initialData['dateContribution'] = $contribution->getDateContribution();
            $initialData['statut'] = $contribution->getStatut()?->value;
        }

        $form = $this->createForm(SponsorContributionType::class, null, [
            'data' => $initialData,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Mettre à jour le sponsor
            $sponsor->setNomSponsor($data['nomSponsor']);
            $sponsor->setTypeSponsor(\App\Enum\TypeSponsor::from($data['typeSponsor']));
            $sponsor->setSiteWeb($data['siteWeb']);
            $sponsor->setEmailContact($data['emailContact']);
            $sponsor->setTelephone($data['telephone']);
            $sponsor->setAdresse($data['adresse']);
            $sponsor->setDomaineActivite($data['domaineActivite']);

            /** @var UploadedFile|null $logoFile */
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile) {
                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $logoFile->guessExtension();
                $logoFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/sponsors',
                    $newFilename
                );
                $sponsor->setLogo($newFilename);
            }

            // Gérer la contribution
            if (!empty($data['evenement'])) {
                if (!$contribution) {
                    // Créer une nouvelle contribution
                    $contribution = new EvenementSponsor();
                    $contribution->setSponsor($sponsor);
                    $em->persist($contribution);
                }
                $contribution->setEvenement($data['evenement']);
                $contribution->setMontantContribution($data['montantContribution'] ?? '0.00');
                $contribution->setTypeContribution(\App\Enum\TypeContribution::from($data['typeContribution'] ?? \App\Enum\TypeContribution::FINANCIER->value));
                $contribution->setDescriptionContribution($data['descriptionContribution']);
                $contribution->setDateContribution($data['dateContribution'] ?? new \DateTime());
                $contribution->setStatut(\App\Enum\StatutSponsor::from($data['statut'] ?? \App\Enum\StatutSponsor::EN_ATTENTE->value));
            } elseif ($contribution) {
                // Supprimer la contribution si l'événement est vidé
                $em->remove($contribution);
            }

            $em->flush();

            $this->addFlash('success', 'Le sponsor a été modifié' . (!empty($data['evenement']) ? ' et la contribution a été mise à jour.' : '.'));
            return $this->redirectToRoute('app_back_sponsor_index');
        }

        return $this->render('evenement/responsable/sponsor_contribution_form.html.twig', [
            'form' => $form,
            'sponsor' => $sponsor,
            'contribution' => $contribution,
        ]);
    }
}
