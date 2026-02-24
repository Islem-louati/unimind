<?php

namespace App\Controller;

use App\Repository\TraitementRepository;
use App\Repository\SuiviTraitementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

#[Route('/api/statistics')]
class StatisticsController extends AbstractController
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[Route('/', name: 'api_statistics_index', methods: ['GET'])]
    public function index(TraitementRepository $traitementRepository, SuiviTraitementRepository $suiviRepository): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non autorisé'], 401);
        }

        $stats = [];

        // Statistiques générales
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_RESPONSABLE_ETUDIANT')) {
            $stats = [
                'total_traitements' => $traitementRepository->count([]),
                'total_suivis' => $suiviRepository->count([]),
                'traitements_par_statut' => $this->getTraitementsByStatut($traitementRepository),
                'suivis_par_mois' => $this->getSuivisByMonth($suiviRepository),
                'progression_moyenne' => $this->getAverageProgression($traitementRepository),
                'traitements_par_priorite' => $this->getTraitementsByPriorite($traitementRepository),
                'suivis_par_ressenti' => $this->getSuivisByRessenti($suiviRepository)
            ];
        } elseif ($this->isGranted('ROLE_PSYCHOLOGUE')) {
            $stats = [
                'total_traitements' => $traitementRepository->count(['psychologue' => $user]),
                'total_suivis' => $suiviRepository->countByPsychologue($user),
                'traitements_par_statut' => $this->getTraitementsByStatutForPsychologue($traitementRepository, $user),
                'suivis_par_mois' => $this->getSuivisByMonthForPsychologue($suiviRepository, $user),
                'progression_moyenne' => $this->getAverageProgressionForPsychologue($traitementRepository, $user),
                'traitements_par_priorite' => $this->getTraitementsByPrioriteForPsychologue($traitementRepository, $user),
                'suivis_par_ressenti' => $this->getSuivisByRessentiForPsychologue($suiviRepository, $user)
            ];
        } elseif ($this->isGranted('ROLE_ETUDIANT')) {
            $stats = [
                'total_traitements' => $traitementRepository->count(['etudiant' => $user]),
                'total_suivis' => $suiviRepository->countByEtudiant($user),
                'traitements_par_statut' => $this->getTraitementsByStatutForEtudiant($traitementRepository, $user),
                'suivis_par_mois' => $this->getSuivisByMonthForEtudiant($suiviRepository, $user),
                'progression_moyenne' => $this->getAverageProgressionForEtudiant($traitementRepository, $user),
                'suivis_par_ressenti' => $this->getSuivisByRessentiForEtudiant($suiviRepository, $user)
            ];
        }

        return new JsonResponse($stats);
    }

    private function getTraitementsByStatut(TraitementRepository $repository): array
    {
        $qb = $repository->createQueryBuilder('t')
            ->select('t.statut, COUNT(t.id) as count')
            ->groupBy('t.statut')
            ->getQuery();

        $result = $qb->getResult();
        $data = [];
        foreach ($result as $row) {
            $data[$row['statut']] = $row['count'];
        }
        return $data;
    }

    private function getTraitementsByPriorite(TraitementRepository $repository): array
    {
        $qb = $repository->createQueryBuilder('t')
            ->select('t.priorite, COUNT(t.id) as count')
            ->groupBy('t.priorite')
            ->getQuery();

        $result = $qb->getResult();
        $data = [];
        foreach ($result as $row) {
            $data[$row['priorite']] = $row['count'];
        }
        return $data;
    }

    private function getSuivisByMonth(SuiviTraitementRepository $repository): array
    {
        $qb = $repository->createQueryBuilder('s')
            ->select('MONTH(s.dateSuivi) as month, YEAR(s.dateSuivi) as year, COUNT(s.id) as count')
            ->groupBy('year, month')
            ->orderBy('year', 'DESC')
            ->addOrderBy('month', 'DESC')
            ->setMaxResults(12)
            ->getQuery();

        $result = $qb->getResult();
        $data = [];
        foreach ($result as $row) {
            $monthName = date('F', mktime(0, 0, 0, $row['month'], 1));
            $data[$monthName . ' ' . $row['year']] = $row['count'];
        }
        return $data;
    }

    private function getSuivisByRessenti(SuiviTraitementRepository $repository): array
    {
        $qb = $repository->createQueryBuilder('s')
            ->select('s.ressenti, COUNT(s.id) as count')
            ->where('s.ressenti IS NOT NULL')
            ->groupBy('s.ressenti')
            ->getQuery();

        $result = $qb->getResult();
        $data = [];
        foreach ($result as $row) {
            $data[$row['ressenti']] = $row['count'];
        }
        return $data;
    }

    private function getAverageProgression(TraitementRepository $repository): float
    {
        $qb = $repository->createQueryBuilder('t')
            ->select('AVG((SELECT COUNT(s.id) FROM App\Entity\SuiviTraitement s WHERE s.traitement = t.id AND s.effectue = true)) as avg_progression')
            ->getQuery();

        $result = $qb->getSingleResult();
        return (float) $result['avg_progression'] ?? 0;
    }

    // Méthodes pour psychologue
    private function getTraitementsByStatutForPsychologue(TraitementRepository $repository, $user): array
    {
        $qb = $repository->createQueryBuilder('t')
            ->select('t.statut, COUNT(t.id) as count')
            ->where('t.psychologue = :user')
            ->setParameter('user', $user)
            ->groupBy('t.statut')
            ->getQuery();

        $result = $qb->getResult();
        $data = [];
        foreach ($result as $row) {
            $data[$row['statut']] = $row['count'];
        }
        return $data;
    }

    private function getTraitementsByPrioriteForPsychologue(TraitementRepository $repository, $user): array
    {
        $qb = $repository->createQueryBuilder('t')
            ->select('t.priorite, COUNT(t.id) as count')
            ->where('t.psychologue = :user')
            ->setParameter('user', $user)
            ->groupBy('t.priorite')
            ->getQuery();

        $result = $qb->getResult();
        $data = [];
        foreach ($result as $row) {
            $data[$row['priorite']] = $row['count'];
        }
        return $data;
    }

    private function getSuivisByMonthForPsychologue(SuiviTraitementRepository $repository, $user): array
    {
        $qb = $repository->createQueryBuilder('s')
            ->select('MONTH(s.dateSuivi) as month, YEAR(s.dateSuivi) as year, COUNT(s.id) as count')
            ->innerJoin('s.traitement', 't')
            ->where('t.psychologue = :user')
            ->setParameter('user', $user)
            ->groupBy('year, month')
            ->orderBy('year', 'DESC')
            ->addOrderBy('month', 'DESC')
            ->setMaxResults(12)
            ->getQuery();

        $result = $qb->getResult();
        $data = [];
        foreach ($result as $row) {
            $monthName = date('F', mktime(0, 0, 0, $row['month'], 1));
            $data[$monthName . ' ' . $row['year']] = $row['count'];
        }
        return $data;
    }

    private function getSuivisByRessentiForPsychologue(SuiviTraitementRepository $repository, $user): array
    {
        $qb = $repository->createQueryBuilder('s')
            ->select('s.ressenti, COUNT(s.id) as count')
            ->innerJoin('s.traitement', 't')
            ->where('t.psychologue = :user')
            ->andWhere('s.ressenti IS NOT NULL')
            ->setParameter('user', $user)
            ->groupBy('s.ressenti')
            ->getQuery();

        $result = $qb->getResult();
        $data = [];
        foreach ($result as $row) {
            $data[$row['ressenti']] = $row['count'];
        }
        return $data;
    }

    private function getAverageProgressionForPsychologue(TraitementRepository $repository, $user): float
    {
        $qb = $repository->createQueryBuilder('t')
            ->select('AVG((SELECT COUNT(s.id) FROM App\Entity\SuiviTraitement s WHERE s.traitement = t.id AND s.effectue = true)) as avg_progression')
            ->where('t.psychologue = :user')
            ->setParameter('user', $user)
            ->getQuery();

        $result = $qb->getSingleResult();
        return (float) $result['avg_progression'] ?? 0;
    }

    // Méthodes pour étudiant
    private function getTraitementsByStatutForEtudiant(TraitementRepository $repository, $user): array
    {
        $qb = $repository->createQueryBuilder('t')
            ->select('t.statut, COUNT(t.id) as count')
            ->where('t.etudiant = :user')
            ->setParameter('user', $user)
            ->groupBy('t.statut')
            ->getQuery();

        $result = $qb->getResult();
        $data = [];
        foreach ($result as $row) {
            $data[$row['statut']] = $row['count'];
        }
        return $data;
    }

    private function getSuivisByMonthForEtudiant(SuiviTraitementRepository $repository, $user): array
    {
        $qb = $repository->createQueryBuilder('s')
            ->select('MONTH(s.dateSuivi) as month, YEAR(s.dateSuivi) as year, COUNT(s.id) as count')
            ->innerJoin('s.traitement', 't')
            ->where('t.etudiant = :user')
            ->setParameter('user', $user)
            ->groupBy('year, month')
            ->orderBy('year', 'DESC')
            ->addOrderBy('month', 'DESC')
            ->setMaxResults(12)
            ->getQuery();

        $result = $qb->getResult();
        $data = [];
        foreach ($result as $row) {
            $monthName = date('F', mktime(0, 0, 0, $row['month'], 1));
            $data[$monthName . ' ' . $row['year']] = $row['count'];
        }
        return $data;
    }

    private function getSuivisByRessentiForEtudiant(SuiviTraitementRepository $repository, $user): array
    {
        $qb = $repository->createQueryBuilder('s')
            ->select('s.ressenti, COUNT(s.id) as count')
            ->innerJoin('s.traitement', 't')
            ->where('t.etudiant = :user')
            ->andWhere('s.ressenti IS NOT NULL')
            ->setParameter('user', $user)
            ->groupBy('s.ressenti')
            ->getQuery();

        $result = $qb->getResult();
        $data = [];
        foreach ($result as $row) {
            $data[$row['ressenti']] = $row['count'];
        }
        return $data;
    }

    private function getAverageProgressionForEtudiant(TraitementRepository $repository, $user): float
    {
        $qb = $repository->createQueryBuilder('t')
            ->select('AVG((SELECT COUNT(s.id) FROM App\Entity\SuiviTraitement s WHERE s.traitement = t.id AND s.effectue = true)) as avg_progression')
            ->where('t.etudiant = :user')
            ->setParameter('user', $user)
            ->getQuery();

        $result = $qb->getSingleResult();
        return (float) $result['avg_progression'] ?? 0;
    }
}
