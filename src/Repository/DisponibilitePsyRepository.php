<?php
// src/Repository/DisponibilitePsyRepository.php

namespace App\Repository;

use App\Entity\DisponibilitePsy;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DisponibilitePsy>
 */
class DisponibilitePsyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DisponibilitePsy::class);
    }

    /**
     * Trouve les disponibilités d'un psychologue
     */
    public function findByPsy(User $psy)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :psy')
            ->setParameter('psy', $psy)
            ->orderBy('d.date_dispo', 'DESC')
            ->addOrderBy('d.heure_debut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les disponibilités futures d'un psychologue
     */
    public function findFuturesByPsy(User $psy)
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :psy')
            ->andWhere('d.date_dispo >= :today OR (d.date_dispo = :today_date AND d.heure_fin > :now_time)')
            ->andWhere('d.statut = :statut')
            ->setParameter('psy', $psy)
            ->setParameter('today', $now->format('Y-m-d'))
            ->setParameter('today_date', $now->format('Y-m-d'))
            ->setParameter('now_time', $now->format('H:i:s'))
            ->setParameter('statut', 'disponible')
            ->orderBy('d.date_dispo', 'ASC')
            ->addOrderBy('d.heure_debut', 'ASC')
            ->getQuery()
            ->getResult();
    }



    /**
     * Trouve les disponibilités d'un jour spécifique
     */
    public function findByPsyAndDate(User $psy, \DateTimeInterface $date)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :psy')
            ->andWhere('d.date_dispo = :date')
            ->setParameter('psy', $psy)
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('d.heure_debut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un créneau est déjà pris
     */
    public function isCreneauDisponible(User $psy, \DateTimeInterface $date, \DateTimeInterface $heureDebut, \DateTimeInterface $heureFin): bool
    {
        $result = $this->createQueryBuilder('d')
            ->andWhere('d.user = :psy')
            ->andWhere('d.date_dispo = :date')
            ->andWhere('d.statut = :statut')
            ->andWhere('NOT (d.heure_fin <= :debut OR d.heure_debut >= :fin)')
            ->setParameter('psy', $psy)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('statut', 'disponible')
            ->setParameter('debut', $heureDebut->format('H:i:s'))
            ->setParameter('fin', $heureFin->format('H:i:s'))
            ->getQuery()
            ->getResult();

        return empty($result);
    }

     public function findDisponibilitesDisponibles(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.statut = :statut')
            ->andWhere('d.date_dispo >= :date OR (d.date_dispo = :date AND d.heure_fin >= :time)')
            ->setParameter('statut', 'disponible')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('time', $date->format('H:i:s'))
            ->orderBy('d.date_dispo', 'ASC')
            ->addOrderBy('d.heure_debut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    
}