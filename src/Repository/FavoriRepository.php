<?php
namespace App\Repository;

use App\Entity\Favori;
use App\Entity\User;
use App\Entity\SeanceMeditation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FavoriRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favori::class);
    }

    public function findOneByUserAndSeance(User $user, SeanceMeditation $seance): ?Favori
{
    return $this->createQueryBuilder('f')
        ->andWhere('f.user = :user')
        ->andWhere('f.seance = :seance')
        ->setParameter('user', $user)
        ->setParameter('seance', $seance)
        ->getQuery()
        ->getOneOrNullResult();
}

    public function countByUserAndSeance(User $user, SeanceMeditation $seance): int
    {
        return $this->count(['user' => $user, 'seance' => $seance]);
    }
}