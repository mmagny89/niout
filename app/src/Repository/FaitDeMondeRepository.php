<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FaitDeMonde;
use App\Entity\GameSave;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FaitDeMonde>
 */
class FaitDeMondeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FaitDeMonde::class);
    }

    /**
     * @return list<FaitDeMonde> dans l'ordre où ils ont été déduits
     */
    public function deLaQuinzaine(GameSave $partie, int $quinzaine): array
    {
        return $this->findBy(['partie' => $partie, 'quinzaine' => $quinzaine], ['id' => 'ASC']);
    }

    public function effacerLaQuinzaine(GameSave $partie, int $quinzaine): void
    {
        $this->createQueryBuilder('f')
            ->delete()
            ->where('f.partie = :partie')
            ->andWhere('f.quinzaine = :quinzaine')
            ->setParameter('partie', $partie)
            ->setParameter('quinzaine', $quinzaine)
            ->getQuery()
            ->execute();
    }

    public function purgerAvant(GameSave $partie, int $quinzaine): void
    {
        $this->createQueryBuilder('f')
            ->delete()
            ->where('f.partie = :partie')
            ->andWhere('f.quinzaine < :quinzaine')
            ->setParameter('partie', $partie)
            ->setParameter('quinzaine', $quinzaine)
            ->getQuery()
            ->execute();
    }
}
