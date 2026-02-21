<?php
// src/Repository/TraitementRepository.php

namespace App\Repository;

use App\Entity\Traitement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Traitement>
 */
class TraitementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Traitement::class);
    }

    /**
     * Compte les traitements actifs d'un étudiant
     */
    public function countActifsByEtudiant(User $etudiant): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t)')
            ->where('t.etudiant = :etudiant')
            ->andWhere('t.statut = :statut')
            ->setParameter('etudiant', $etudiant)
            ->setParameter('statut', 'actif')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les traitements actifs d'un étudiant
     */
    public function findActifsByEtudiant(User $etudiant): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.etudiant = :etudiant')
            ->andWhere('t.statut = :statut')
            ->setParameter('etudiant', $etudiant)
            ->setParameter('statut', 'actif')
            ->orderBy('t.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les traitements terminés d'un étudiant
     */
    public function findTerminesByEtudiant(User $etudiant): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.etudiant = :etudiant')
            ->andWhere('t.statut = :statut')
            ->setParameter('etudiant', $etudiant)
            ->setParameter('statut', 'termine')
            ->orderBy('t.dateFin', 'DESC')
            ->getQuery()
            ->getResult();
    }
}