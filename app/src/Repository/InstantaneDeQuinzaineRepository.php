<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GameSave;
use App\Entity\InstantaneDeQuinzaine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InstantaneDeQuinzaine>
 */
class InstantaneDeQuinzaineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstantaneDeQuinzaine::class);
    }

    public function dernier(GameSave $partie): ?InstantaneDeQuinzaine
    {
        return $this->findOneBy(['partie' => $partie], ['cycle' => 'DESC']);
    }

    public function deLaQuinzaine(GameSave $partie, int $cycle): ?InstantaneDeQuinzaine
    {
        return $this->findOneBy(['partie' => $partie, 'cycle' => $cycle]);
    }

    /**
     * Efface ce qui est plus ancien que la quinzaine donnée : la trace ne garde
     * que les dernières.
     */
    public function purgerAvant(GameSave $partie, int $cycle): void
    {
        $this->createQueryBuilder('i')
            ->delete()
            ->where('i.partie = :partie')
            ->andWhere('i.cycle < :cycle')
            ->setParameter('partie', $partie)
            ->setParameter('cycle', $cycle)
            ->getQuery()
            ->execute();
    }
}
