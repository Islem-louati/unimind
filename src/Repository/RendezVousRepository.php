<?php

namespace App\Repository;

use App\Entity\RendezVous;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RendezVous>
 */
class RendezVousRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RendezVous::class);
    }

    /**
     * Récupère les rendez-vous d'un psychologue triés par date de début
     */
    public function findByPsyOrderedByDate(User $psy, string $order = 'ASC', ?int $limit = null)
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.disponibilite', 'd')
            ->andWhere('r.psy = :psy')
            ->setParameter('psy', $psy)
            ->orderBy('d.date_dispo', $order)
            ->addOrderBy('d.heure_debut', $order);

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les rendez-vous d'un utilisateur (étudiant) triés par date de début
     */
    public function findByEtudiantOrderedByDate(User $etudiant, string $order = 'DESC')
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.disponibilite', 'd')
            ->andWhere('r.etudiant = :etudiant')
            ->setParameter('etudiant', $etudiant)
            ->orderBy('d.date_dispo', $order)
            ->addOrderBy('d.heure_debut', $order)
            ->getQuery()
            ->getResult();
    }


    // Dans src/Repository/RendezVousRepository.php
public function findProchainsRendezVous(User $etudiant, int $limit = 5): array
{
    $now = new \DateTime();
    
    return $this->createQueryBuilder('r')
        ->leftJoin('r.disponibilite', 'd')
        ->andWhere('r.etudiant = :etudiant')
        ->andWhere('(d.date_dispo > :now OR (d.date_dispo = :date AND d.heure_fin > :time))')
        ->andWhere('r.statut IN (:statuts)')
        ->setParameter('etudiant', $etudiant)
        ->setParameter('now', $now->format('Y-m-d'))
        ->setParameter('date', $now->format('Y-m-d'))
        ->setParameter('time', $now->format('H:i:s'))
        ->setParameter('statuts', ['demande', 'confirme', 'en-cours'])
        ->orderBy('d.date_dispo', 'ASC')
        ->addOrderBy('d.heure_debut', 'ASC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}
}
