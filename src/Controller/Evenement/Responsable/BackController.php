<?php

namespace App\Controller\Evenement\Responsable;

use App\Enum\StatutEvenement;
use App\Repository\EvenementRepository;
use App\Repository\EvenementSponsorRepository;
use App\Repository\ParticipationRepository;
use App\Repository\SponsorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Dashboard responsable– Module Événements.
 */
#[Route('/responsable-etudiant', name: 'app_back_')]
final class BackController extends AbstractController
{
    /**
     * Tableau de bord de l'espace responsable étudiant.
     */
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(
        EvenementRepository $evenementRepository,
        ParticipationRepository $participationRepository,
        SponsorRepository $sponsorRepository,
        EvenementSponsorRepository $evenementSponsorRepository
    ): Response
    {
        $now = new \DateTimeImmutable();

        $totalEvenements = $evenementRepository->count([]);
        $evenementsAVenirCount = $evenementRepository->count(['statut' => StatutEvenement::A_VENIR->value]);
        $totalParticipations = $participationRepository->count([]);
        $totalSponsors = $sponsorRepository->count([]);
        $totalContributions = $evenementSponsorRepository->count([]);

        $prochainsEvenements = $evenementRepository->createQueryBuilder('e')
            ->andWhere('e.dateDebut >= :now')
            ->setParameter('now', $now)
            ->orderBy('e.dateDebut', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $dernieresParticipations = $participationRepository->createQueryBuilder('p')
            ->leftJoin('p.evenement', 'e')
            ->leftJoin('p.etudiant', 'u')
            ->addSelect('e', 'u')
            ->orderBy('p.date_inscription', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return $this->render('evenement/responsable/dashboard.html.twig', [
            'kpis' => [
                'total_evenements' => $totalEvenements,
                'evenements_a_venir' => $evenementsAVenirCount,
                'total_participations' => $totalParticipations,
                'total_sponsors' => $totalSponsors,
                'total_contributions' => $totalContributions,
            ],
            'prochains_evenements' => $prochainsEvenements,
            'dernieres_participations' => $dernieresParticipations,
        ]);
    }

}
